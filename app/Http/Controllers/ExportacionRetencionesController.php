<?php

namespace App\Http\Controllers;

use App\Models\Comprobante;
use App\Models\Retencion;
use App\Support\Csv;
use Illuminate\Http\Request;
use ZipArchive;

/**
 * Resumen del período y archivos para los aplicativos:
 *  - SICORE (ARCA): retenciones de Ganancias practicadas.  ⚠ Validar en la primera importación.
 *  - ARBA A-122R (lotes, diseño vigente desde 03/2026): retenciones de IIBB practicadas.
 *  - IVA (SIRE) y AGIP: por ahora se exporta el listado para la carga.
 */
class ExportacionRetencionesController extends Controller
{
    public function index(Request $request)
    {
        $periodo = $this->periodo($request);

        $resumen = Retencion::where('periodo', $periodo)->where('estado', 'registrado')->where('importe', '>', 0)
            ->selectRaw('tipo, impuesto, COUNT(*) AS cantidad, SUM(importe) AS importe')
            ->groupBy('tipo', 'impuesto')->get();

        $percepciones = Comprobante::where('circuito', 'venta')->where('periodo_iva', $periodo)->where('estado', 'registrado')
            ->get(['tipo_comprobante', 'percepcion_iva', 'percepcion_iibb'])
            ->reduce(fn ($a, $c) => [
                'iva'  => $a['iva'] + $c->signo() * (float) $c->percepcion_iva,
                'iibb' => $a['iibb'] + $c->signo() * (float) $c->percepcion_iibb,
            ], ['iva' => 0.0, 'iibb' => 0.0]);

        return view('retenciones.exportar', [
            'periodo'      => $periodo,
            'practicadas'  => $resumen->where('tipo', 'practicada')->keyBy('impuesto'),
            'sufridas'     => $resumen->where('tipo', 'sufrida')->keyBy('impuesto'),
            'percepciones' => $percepciones,
            'sicore'       => count($this->lineasSicore($periodo)),
            'arba'         => count($this->lineasArba($periodo)),
        ]);
    }

    public function sicore(Request $request)
    {
        $periodo = $this->periodo($request);
        $contenido = implode("\r\n", $this->lineasSicore($periodo));

        return response($contenido ? $contenido . "\r\n" : '', 200, [
            'Content-Type'        => 'text/plain; charset=ISO-8859-1',
            'Content-Disposition' => 'attachment; filename="SICORE_GANANCIAS_' . app('empresa_activa')->cuit . '_' . str_replace('-', '', $periodo) . '.txt"',
        ]);
    }

    /** Lote ARBA: ZIP «ER-CUIT-AAAAMMQ-ACTIVIDAD-LOTEnnnnn.zip» con el TXT adentro */
    public function arba(Request $request)
    {
        abort_unless(class_exists(ZipArchive::class), 500, 'La extensión zip de PHP no está habilitada.');

        $periodo = $this->periodo($request);
        $actividad = preg_replace('/\D/', '', (string) $request->input('actividad', '6')) ?: '6';
        $quincena = in_array($request->input('quincena'), ['0', '1', '2'], true) ? $request->input('quincena') : '0';
        $lote = 'LOTE' . str_pad((string) max(1, (int) $request->input('lote', 1)), 5, '0', STR_PAD_LEFT);
        $nombre = 'ER-' . app('empresa_activa')->cuit . '-' . str_replace('-', '', $periodo) . $quincena . '-' . $actividad . '-' . $lote;

        $lineas = $this->lineasArba($periodo, $quincena);

        $tmp = tempnam(sys_get_temp_dir(), 'arba');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString($nombre . '.txt', $lineas ? implode("\r\n", $lineas) . "\r\n" : '');
        $zip->close();

        return response()->download($tmp, $nombre . '.zip')->deleteFileAfterSend();
    }

    /** Listado completo del período (retenciones practicadas, sufridas y percepciones practicadas) */
    public function listado(Request $request)
    {
        $periodo = $this->periodo($request);

        $filas = Retencion::with(['tercero', 'regimen'])->where('periodo', $periodo)->where('estado', 'registrado')
            ->orderBy('tipo')->orderBy('impuesto')->orderBy('fecha')->get()
            ->map(fn ($r) => [
                $r->tipo === 'practicada' ? 'Retención practicada' : 'Retención sufrida',
                $r->impuesto_texto, $r->regimen?->codigo, $r->fecha->format('d/m/Y'), $r->numero_certificado,
                $r->tercero->razon_social, $r->tercero->nro_documento,
                (float) $r->base_imponible, (float) $r->alicuota, (float) $r->importe,
            ])->all();

        $ventas = Comprobante::with('tercero')->where('circuito', 'venta')->where('periodo_iva', $periodo)->where('estado', 'registrado')
            ->where(fn ($q) => $q->where('percepcion_iva', '>', 0)->orWhere('percepcion_iibb', '>', 0))->orderBy('fecha')->get();

        foreach ($ventas as $c) {
            foreach (['percepcion_iva' => 'IVA', 'percepcion_iibb' => 'IIBB'] as $campo => $imp) {
                if ((float) $c->$campo > 0) {
                    $filas[] = ['Percepción practicada', $imp, '', $c->fecha->format('d/m/Y'), $c->descripcion_corta,
                        $c->tercero->razon_social, $c->tercero->nro_documento, (float) $c->neto_gravado, 0.0, $c->signo() * (float) $c->$campo];
                }
            }
        }

        return Csv::descargar('retenciones_percepciones_' . app('empresa_activa')->cuit . '_' . $periodo . '.csv',
            ['Tipo', 'Impuesto', 'Régimen', 'Fecha', 'Certificado / Comprobante', 'Razón social', 'CUIT', 'Base', 'Alícuota', 'Importe'], $filas);
    }

    /* ---------------------------------------------------------------- */

    /**
     * SICORE – importación de retenciones (144 posiciones, decimales con punto).
     * comprobante(2) fecha(10) número(16) importe(16) impuesto(3) régimen(3) operación(1) base(14)
     * fecha retención(10) condición(2) suspendidos(1) importe retención(14) % exclusión(6)
     * fecha boletín(10) tipo doc(2) número doc(20) certificado original(14)
     */
    private function lineasSicore(string $periodo): array
    {
        return Retencion::with(['tercero', 'regimen', 'comprobante'])
            ->where('tipo', 'practicada')->where('impuesto', 'ganancias')
            ->where('periodo', $periodo)->where('estado', 'registrado')->where('importe', '>', 0)
            ->orderBy('fecha')->get()
            ->map(function (Retencion $r) {
                $comp = $r->comprobante;
                $numero = $comp ? preg_replace('/\D/', '', $comp->numero_completo) : preg_replace('/\D/', '', (string) $r->numero_certificado);

                return ($comp ? '01' : '06')
                    . ($comp ? $comp->fecha : $r->fecha)->format('d/m/Y')
                    . str_pad(substr($numero, -16), 16, '0', STR_PAD_LEFT)
                    . $this->dec((float) ($r->importe_comprobante ?: $r->base_imponible), 16)
                    . '217'
                    . str_pad(substr((string) $r->regimen?->codigo, -3), 3, '0', STR_PAD_LEFT)
                    . '1'
                    . $this->dec((float) $r->base_imponible, 14)
                    . $r->fecha->format('d/m/Y')
                    . ($r->condicion === 'no_inscripto' ? '02' : '01')
                    . '0'
                    . $this->dec((float) $r->importe, 14)
                    . '000.00'
                    . str_repeat(' ', 10)
                    . '80'
                    . str_pad(preg_replace('/\D/', '', (string) $r->tercero->nro_documento), 20, ' ', STR_PAD_RIGHT)
                    . str_repeat('0', 14);
            })->all();
    }

    /**
     * ARBA A-122R – alta de retenciones (67 posiciones):
     * nro transacción(20) CUIT(11) sucursal(5) fecha dd/mm/aaaa(10) alícuota 99.99(5) base 9999999999999.99(16)
     */
    private function lineasArba(string $periodo, string $quincena = '0'): array
    {
        return Retencion::with('tercero')
            ->where('tipo', 'practicada')->where('impuesto', 'iibb_arba')
            ->where('periodo', $periodo)->where('estado', 'registrado')->where('importe', '>', 0)
            ->when($quincena === '1', fn ($q) => $q->whereDay('fecha', '<=', 15))
            ->when($quincena === '2', fn ($q) => $q->whereDay('fecha', '>', 15))
            ->orderBy('fecha')->get()
            ->map(fn (Retencion $r) =>
                str_pad((string) $r->id, 20, '0', STR_PAD_LEFT)
                . str_pad(preg_replace('/\D/', '', (string) $r->tercero->nro_documento), 11, '0', STR_PAD_LEFT)
                . '00001'
                . $r->fecha->format('d/m/Y')
                . $this->dec((float) $r->alicuota, 5)
                . $this->dec((float) $r->base_imponible, 16)
            )->all();
    }

    /** Número con 2 decimales y punto, rellenado con ceros a la izquierda */
    private function dec(float $v, int $largo): string
    {
        return str_pad(number_format(abs($v), 2, '.', ''), $largo, '0', STR_PAD_LEFT);
    }

    private function periodo(Request $request): string
    {
        $p = (string) $request->input('periodo');
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p) ? $p : today()->subMonthNoOverflow()->format('Y-m');
    }
}
