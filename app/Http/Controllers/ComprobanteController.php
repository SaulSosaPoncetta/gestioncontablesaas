<?php

namespace App\Http\Controllers;

use App\Http\Requests\ComprobanteRequest;
use App\Models\Comprobante;
use App\Models\Cuenta;
use App\Models\CuentaConfig;
use App\Models\Tercero;
use App\Services\ComprobanteService;
use App\Support\Afip;
use Illuminate\Http\Request;

/**
 * Comprobantes de venta y de compra (el circuito llega en la ruta: venta | compra).
 */
class ComprobanteController extends Controller
{
    public function __construct(private ComprobanteService $service)
    {
    }

    public function index(Request $request, string $circuito)
    {
        $query = Comprobante::query()
            ->with('tercero:id,razon_social,nro_documento,tipo_documento')
            ->where('circuito', $circuito)
            ->when($request->periodo, fn ($q, $p) => $q->where('periodo_iva', $p))
            ->when($request->desde, fn ($q, $d) => $q->whereDate('fecha', '>=', $d))
            ->when($request->hasta, fn ($q, $h) => $q->whereDate('fecha', '<=', $h))
            ->when($request->tercero, fn ($q, $t) => $q->where('tercero_id', $t))
            ->when($request->tipo, fn ($q, $t) => $q->where('tipo_comprobante', $t))
            ->when($request->numero, fn ($q, $n) => $q->where('numero', (int) $n))
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado));

        // Totales con signo (las notas de crédito restan)
        $totales = (clone $query)->where('estado', 'registrado')->get(['tipo_comprobante', 'neto_gravado', 'no_gravado', 'exento', 'iva', 'percepcion_iva', 'percepcion_iibb', 'otros_tributos', 'total'])
            ->reduce(function ($acc, $c) {
                $s = $c->signo();
                $acc['netos'] += $s * ((float) $c->neto_gravado + (float) $c->no_gravado + (float) $c->exento);
                $acc['iva'] += $s * (float) $c->iva;
                $acc['percepciones'] += $s * ((float) $c->percepcion_iva + (float) $c->percepcion_iibb + (float) $c->otros_tributos);
                $acc['total'] += $s * (float) $c->total;
                return $acc;
            }, ['netos' => 0, 'iva' => 0, 'percepciones' => 0, 'total' => 0]);

        $comprobantes = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(40)->withQueryString();

        return view('comprobantes.index', [
            'circuito'     => $circuito,
            'comprobantes' => $comprobantes,
            'totales'      => $totales,
            'terceros'     => Tercero::delCircuito($circuito)->orderBy('razon_social')->get(['id', 'razon_social']),
            'tipos'        => Afip::tiposPara($circuito, app('empresa_activa')->condicion_iva),
        ]);
    }

    public function create(Request $request, string $circuito)
    {
        abort_unless($request->user()->puede('operador'), 403);

        $empresa = app('empresa_activa');
        $ultimo = Comprobante::where('circuito', $circuito)->latest('id')->first();

        $comprobante = new Comprobante([
            'circuito'             => $circuito,
            'fecha'                => today(),
            'periodo_iva'          => today()->format('Y-m'),
            'tipo_comprobante'     => $ultimo?->tipo_comprobante ?? ($empresa->condicion_iva === 'responsable_inscripto' ? ($circuito === 'venta' ? 6 : 1) : ($circuito === 'venta' ? 11 : 1)),
            'punto_venta'          => $circuito === 'venta' ? ($ultimo?->punto_venta ?? 1) : null,
            'numero'               => $circuito === 'venta' && $ultimo ? $ultimo->numero + 1 : null,
            'cuenta_imputacion_id' => CuentaConfig::idPara($circuito === 'venta' ? 'ventas' : 'compras'),
        ]);

        return view('comprobantes.create', $this->datosFormulario($circuito, $comprobante) + ['alicuotas' => []]);
    }

    public function store(ComprobanteRequest $request, string $circuito)
    {
        $comprobante = $this->service->guardar($request->validated(), $circuito);

        $mensaje = "{$comprobante->descripcion_corta} registrado (asiento N° {$comprobante->asiento?->numero}).";

        if ($request->input('accion') === 'otro') {
            return redirect()->route('comprobantes.create', $circuito)->with('success', $mensaje);
        }

        return redirect()->route('comprobantes.show', [$circuito, $comprobante])->with('success', $mensaje);
    }

    public function show(string $circuito, Comprobante $comprobante)
    {
        $this->verificarCircuito($circuito, $comprobante);
        $comprobante->load(['tercero', 'alicuotas', 'asiento.lineas.cuenta', 'cuentaImputacion']);

        return view('comprobantes.show', compact('circuito', 'comprobante'));
    }

    public function edit(Request $request, string $circuito, Comprobante $comprobante)
    {
        abort_unless($request->user()->puede('operador'), 403);
        $this->verificarCircuito($circuito, $comprobante);

        if (! $comprobante->esEditable()) {
            return redirect()->route('comprobantes.show', [$circuito, $comprobante])
                ->with('warning', 'El comprobante no se puede modificar (anulado, ejercicio cerrado o período bloqueado).');
        }

        $alicuotas = $comprobante->alicuotas->map(fn ($a) => $a->only(['alicuota_id', 'neto', 'iva']))->all();

        return view('comprobantes.edit', $this->datosFormulario($circuito, $comprobante) + ['alicuotas' => $alicuotas]);
    }

    public function update(ComprobanteRequest $request, string $circuito, Comprobante $comprobante)
    {
        $this->verificarCircuito($circuito, $comprobante);

        if (! $comprobante->esEditable()) {
            return redirect()->route('comprobantes.show', [$circuito, $comprobante])->with('error', 'El comprobante no se puede modificar.');
        }

        $comprobante = $this->service->guardar($request->validated(), $circuito, $comprobante);

        return redirect()->route('comprobantes.show', [$circuito, $comprobante])
            ->with('success', "{$comprobante->descripcion_corta} actualizado. El asiento N° {$comprobante->asiento?->numero} se regeneró.");
    }

    public function anular(Request $request, string $circuito, Comprobante $comprobante)
    {
        abort_unless($request->user()->puede('contador'), 403);
        $this->verificarCircuito($circuito, $comprobante);
        $request->validate(['motivo' => ['required', 'string', 'max:150']], [], ['motivo' => 'motivo']);

        if (! $comprobante->esEditable()) {
            return back()->with('error', 'El comprobante no se puede anular (ya anulado, ejercicio cerrado o período bloqueado).');
        }

        $this->service->anular($comprobante, $request->motivo);

        return redirect()->route('comprobantes.show', [$circuito, $comprobante])
            ->with('success', "{$comprobante->descripcion_corta} anulado junto con su asiento.");
    }

    /* ---------------------------------------------------------------- */

    private function datosFormulario(string $circuito, Comprobante $comprobante): array
    {
        $terceros = Tercero::delCircuito($circuito)
            ->where(fn ($q) => $q->where('activo', true)->when($comprobante->tercero_id, fn ($w) => $w->orWhere('id', $comprobante->tercero_id)))
            ->orderBy('razon_social')
            ->get();

        return [
            'circuito'    => $circuito,
            'comprobante' => $comprobante,
            'terceros'    => $terceros,
            'cuentas'     => Cuenta::planOrdenado(Cuenta::where('imputable', true)->where('activa', true)),
            'tipos'       => Afip::tiposPara($circuito, app('empresa_activa')->condicion_iva),
            'cuentaDefecto' => CuentaConfig::idPara($circuito === 'venta' ? 'ventas' : 'compras'),
        ];
    }

    private function verificarCircuito(string $circuito, Comprobante $comprobante): void
    {
        abort_unless($comprobante->circuito === $circuito, 404);
    }
}
