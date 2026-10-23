<?php

namespace App\Http\Controllers;

use App\Http\Requests\RetencionRequest;
use App\Models\Comprobante;
use App\Models\RegimenRetencion;
use App\Models\Retencion;
use App\Models\Tercero;
use App\Services\AsientoService;
use App\Services\RetencionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Retenciones practicadas (/retenciones/practicada) y sufridas (/retenciones/sufrida).
 */
class RetencionController extends Controller
{
    public function __construct(private RetencionService $service)
    {
    }

    public function index(Request $request, string $tipo)
    {
        $query = Retencion::with(['tercero:id,razon_social,nro_documento,tipo_documento', 'regimen'])
            ->where('tipo', $tipo)
            ->when($request->periodo, fn ($q, $p) => $q->where('periodo', $p))
            ->when($request->impuesto, fn ($q, $i) => $q->where('impuesto', $i))
            ->when($request->tercero, fn ($q, $t) => $q->where('tercero_id', $t))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado));

        $totales = (clone $query)->where('estado', 'registrado')
            ->selectRaw('impuesto, COUNT(*) AS cantidad, SUM(importe) AS importe, SUM(base_imponible) AS base')
            ->groupBy('impuesto')->get()->keyBy('impuesto');

        return view('retenciones.index', [
            'tipo'        => $tipo,
            'retenciones' => $query->orderByDesc('fecha')->orderByDesc('id')->paginate(40)->withQueryString(),
            'totales'     => $totales,
            'terceros'    => Tercero::delCircuito($tipo === 'practicada' ? 'compra' : 'venta')->orderBy('razon_social')->get(['id', 'razon_social']),
            'sinRegimenes'=> $tipo === 'practicada' && ! RegimenRetencion::exists(),
        ]);
    }

    public function create(Request $request, string $tipo)
    {
        abort_unless($request->user()->puede('operador'), 403);

        if ($tipo === 'practicada' && ! RegimenRetencion::where('activo', true)->exists()) {
            return redirect()->route('regimenes.index')->with('warning', 'Antes de cargar retenciones practicadas, configure los regímenes en los que actúa como agente.');
        }

        $retencion = new Retencion([
            'tipo'           => $tipo,
            'fecha'          => today(),
            'condicion'      => 'inscripto',
            'tercero_id'     => $request->tercero,
            'comprobante_id' => $request->comprobante,
            'impuesto'       => $tipo === 'sufrida' ? 'iva' : null,
        ]);

        // Desde un comprobante: propone tercero, importe y base (neto gravado)
        if ($request->comprobante && $comp = Comprobante::find($request->comprobante)) {
            $retencion->tercero_id = $comp->tercero_id;
            $retencion->importe_comprobante = $comp->total;
            $retencion->base_imponible = (float) $comp->neto_gravado + (float) $comp->no_gravado + (float) $comp->exento;
        }

        return view('retenciones.create', $this->datosFormulario($tipo, $retencion));
    }

    public function store(RetencionRequest $request, string $tipo)
    {
        $retencion = $this->service->guardar($this->datos($request, $tipo), $tipo);

        $mensaje = (float) $retencion->importe > 0
            ? "Retención registrada" . ($retencion->numero_certificado ? " (certificado {$retencion->numero_certificado})" : '') . '.'
            : 'Pago registrado sin retención (se computa para la acumulación mensual).';

        return redirect()->route('retenciones.show', [$tipo, $retencion])->with('success', $mensaje);
    }

    public function show(string $tipo, Retencion $retencion)
    {
        abort_unless($retencion->tipo === $tipo, 404);
        $retencion->load(['tercero', 'regimen', 'comprobante', 'asiento.lineas.cuenta']);

        return view('retenciones.show', compact('tipo', 'retencion'));
    }

    public function edit(Request $request, string $tipo, Retencion $retencion)
    {
        abort_unless($request->user()->puede('operador') && $retencion->tipo === $tipo, 403);

        if (! $retencion->esEditable()) {
            return redirect()->route('retenciones.show', [$tipo, $retencion])->with('warning', 'La retención no se puede modificar.');
        }

        return view('retenciones.edit', $this->datosFormulario($tipo, $retencion));
    }

    public function update(RetencionRequest $request, string $tipo, Retencion $retencion)
    {
        abort_unless($retencion->tipo === $tipo && $retencion->esEditable(), 403);

        $retencion = $this->service->guardar($this->datos($request, $tipo, $retencion), $tipo, $retencion);

        return redirect()->route('retenciones.show', [$tipo, $retencion])->with('success', 'Retención actualizada.');
    }

    public function anular(Request $request, string $tipo, Retencion $retencion)
    {
        abort_unless($request->user()->puede('contador') && $retencion->tipo === $tipo, 403);
        $request->validate(['motivo' => ['required', 'string', 'max:150']]);

        if (! $retencion->esEditable()) {
            return back()->with('error', 'La retención no se puede anular.');
        }

        $this->service->anular($retencion, $request->motivo);

        return redirect()->route('retenciones.show', [$tipo, $retencion])->with('success', 'Retención anulada junto con su asiento.');
    }

    /** Certificado de retención (practicadas) */
    public function certificado(string $tipo, Retencion $retencion)
    {
        abort_unless($retencion->tipo === 'practicada' && $tipo === 'practicada' && (float) $retencion->importe > 0, 404);
        $retencion->load(['tercero', 'regimen', 'comprobante']);

        return Pdf::loadView('pdf.certificado-retencion', ['r' => $retencion, 'empresa' => app('empresa_activa')])
            ->setPaper('a4', 'portrait')
            ->stream('certificado_' . $retencion->numero_certificado . '.pdf');
    }

    /** Cálculo en vivo para el formulario (JSON) */
    public function calcular(Request $request)
    {
        $regimen = RegimenRetencion::find($request->regimen);
        if (! $regimen || ! $request->tercero) {
            return response()->json(['importe' => 0, 'alicuota' => 0, 'detalle' => 'Elija régimen y proveedor.']);
        }

        $r = $this->service->calcular(
            $regimen, (int) $request->tercero,
            AsientoService::aCentavos($request->input('base', '')) / 100,
            $request->fecha ? Carbon::parse($request->fecha) : today(),
            $request->condicion === 'no_inscripto' ? 'no_inscripto' : 'inscripto',
            $request->excluir ? (int) $request->excluir : null
        );

        return response()->json($r);
    }

    /* ---------------------------------------------------------------- */

    /** En las practicadas, salvo carga manual, el importe y la alícuota los calcula el sistema. */
    private function datos(RetencionRequest $request, string $tipo, ?Retencion $actual = null): array
    {
        $d = $request->validated();

        if ($tipo === 'practicada') {
            $regimen = RegimenRetencion::findOrFail($d['regimen_id']);
            $d['impuesto'] = $regimen->impuesto;

            if (! $request->boolean('importe_manual')) {
                $calc = $this->service->calcular($regimen, (int) $d['tercero_id'], (float) $d['base_imponible'],
                    Carbon::parse($d['fecha']), $d['condicion'], $actual?->id);
                $d['importe'] = $calc['importe'];
                $d['alicuota'] = $calc['alicuota'];
            }
        }

        return $d;
    }

    private function datosFormulario(string $tipo, Retencion $retencion): array
    {
        $circuito = $tipo === 'practicada' ? 'compra' : 'venta';

        return [
            'tipo'         => $tipo,
            'retencion'    => $retencion,
            'regimenes'    => RegimenRetencion::where('activo', true)->orWhere('id', $retencion->regimen_id)->orderBy('impuesto')->orderBy('codigo')->get(),
            'terceros'     => Tercero::delCircuito($circuito)->where('activo', true)->orWhere('id', $retencion->tercero_id)->orderBy('razon_social')->get(),
            'comprobantes' => Comprobante::where('circuito', $circuito)->where('estado', 'registrado')
                ->where(fn ($q) => $q->where('fecha', '>=', today()->subMonths(6))->orWhere('id', $retencion->comprobante_id))
                ->with('tercero:id,razon_social')->orderByDesc('fecha')->limit(500)->get(),
        ];
    }
}
