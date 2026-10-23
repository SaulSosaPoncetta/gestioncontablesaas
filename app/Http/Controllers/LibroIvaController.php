<?php

namespace App\Http\Controllers;

use App\Models\IvaPosicion;
use App\Services\AsientoService;
use App\Services\LibroIvaService;
use App\Support\Afip;
use App\Support\Csv;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LibroIvaController extends Controller
{
    public function __construct(private LibroIvaService $iva)
    {
    }

    /** Libro IVA Ventas / Compras del período */
    public function libro(Request $request, string $circuito)
    {
        $periodo = $this->periodo($request);
        $datos = ['circuito' => $circuito, 'periodo' => $periodo] + $this->iva->libro($circuito, $periodo);

        if ($request->formato === 'pdf') {
            return Pdf::loadView('pdf.libro-iva', $datos + $this->datosPdf($periodo))
                ->setPaper('a4', 'landscape')
                ->stream($this->archivo('libro_iva_' . ($circuito === 'venta' ? 'ventas' : 'compras') . '_' . $periodo, 'pdf'));
        }

        if ($request->formato === 'csv') {
            $filas = $datos['filas']->map(fn ($f) => [
                $f->comprobante->fecha->format('d/m/Y'),
                Afip::abreviatura($f->comprobante->tipo_comprobante),
                $f->comprobante->numero_completo,
                $f->comprobante->tercero->razon_social,
                $f->comprobante->tercero->nro_documento,
                $f->comprobante->tercero->condicion_iva_texto,
                $f->neto, $f->iva21, $f->iva105, $f->iva_otras, $f->no_gravado, $f->exento,
                $f->percepcion_iva, $f->percepcion_iibb, $f->otros, $f->total,
            ])->all();
            $t = $datos['totales'];
            $filas[] = ['', '', '', 'TOTALES', '', '', $t['neto'], $t['iva21'], $t['iva105'], $t['iva_otras'], $t['no_gravado'], $t['exento'], $t['percepcion_iva'], $t['percepcion_iibb'], $t['otros'], $t['total']];

            return Csv::descargar($this->archivo('libro_iva_' . ($circuito === 'venta' ? 'ventas' : 'compras') . '_' . $periodo, 'csv'),
                ['Fecha', 'Tipo', 'Número', 'Razón social', 'CUIT/Doc.', 'Condición IVA', 'Neto gravado', 'IVA 21%', 'IVA 10,5%', 'IVA otras',
                 'No gravado', 'Exento', 'Percep. IVA', 'Percep. IIBB', 'Otros trib.', 'Total'], $filas);
        }

        return view('iva.libro', $datos + ['cerrado' => IvaPosicion::estaCerrado($periodo)]);
    }

    /** Posición mensual de IVA */
    public function posicion(Request $request)
    {
        $periodo = $this->periodo($request);
        $manual = $this->manual($request);
        $posicion = $this->iva->posicion($periodo, $manual);

        if ($request->formato === 'pdf') {
            return Pdf::loadView('pdf.posicion-iva', ['periodo' => $periodo, 'p' => $posicion] + $this->datosPdf($periodo))
                ->setPaper('a4', 'portrait')
                ->stream($this->archivo('posicion_iva_' . $periodo, 'pdf'));
        }

        return view('iva.posicion', [
            'periodo'  => $periodo,
            'p'        => $posicion,
            'manual'   => $manual,
            'cerradas' => IvaPosicion::orderByDesc('periodo')->limit(12)->get(),
        ]);
    }

    public function cerrar(Request $request)
    {
        abort_unless($request->user()->puede('contador'), 403);

        $periodo = $this->periodo($request);
        if (IvaPosicion::estaCerrado($periodo)) {
            return back()->with('error', 'El período ya está cerrado.');
        }

        $posicion = $this->iva->cerrar($periodo, $this->manual($request), $request->boolean('generar_asiento'));

        return redirect()->route('iva.posicion', ['periodo' => $periodo])->with('success',
            "Período {$posicion->periodo_texto} cerrado." . ($posicion->asiento_id ? " Se registró el asiento de liquidación N° {$posicion->asiento->numero}." : ''));
    }

    public function reabrir(Request $request, IvaPosicion $posicion)
    {
        abort_unless($request->user()->puede('contador'), 403);

        $periodo = $posicion->periodo;
        $this->iva->reabrir($posicion);

        return redirect()->route('iva.posicion', ['periodo' => $periodo])
            ->with('success', 'Período reabierto. Los comprobantes vuelven a poder modificarse y el asiento de liquidación quedó anulado.');
    }

    /* ---------------------------------------------------------------- */

    /** Período AAAA-MM pedido; por defecto el mes actual. */
    private function periodo(Request $request): string
    {
        $p = (string) $request->input('periodo');
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p) ? $p : today()->format('Y-m');
    }

    /** Saldos iniciales y retenciones cargados a mano (importes en formato argentino). */
    private function manual(Request $request): array
    {
        return [
            'saldo_tecnico_anterior' => AsientoService::aCentavos($request->input('saldo_tecnico_anterior', '')) / 100,
            'saldo_libre_anterior'   => AsientoService::aCentavos($request->input('saldo_libre_anterior', '')) / 100,
            'retenciones'            => AsientoService::aCentavos($request->input('retenciones', '')) / 100,
        ];
    }

    private function datosPdf(string $periodo): array
    {
        $inicio = Carbon::createFromFormat('Y-m-d', $periodo . '-01')->startOfDay();

        return [
            'empresa'   => app('empresa_activa'),
            'desde'     => $inicio,
            'hasta'     => $inicio->copy()->endOfMonth(),
            'ejercicio' => app()->bound('ejercicio_activo') ? app('ejercicio_activo') : null,
        ];
    }

    private function archivo(string $base, string $ext): string
    {
        return $base . '_' . app('empresa_activa')->cuit . '.' . $ext;
    }
}
