<?php

namespace App\Http\Controllers;

use App\Models\IvaPosicion;
use App\Services\ExportacionArcaService;
use Illuminate\Http\Request;
use ZipArchive;

class ExportacionArcaController extends Controller
{
    /** archivo => [régimen, clave del resultado, nombre, extensión] */
    private const ARCHIVOS = [
        'df'                => ['iva_simple', 'df', 'IVASimple_DebitoFiscal', 'csv'],
        'rdf'               => ['iva_simple', 'rdf', 'IVASimple_RestitucionDebitoFiscal', 'csv'],
        'cf'                => ['iva_simple', 'cf', 'IVASimple_CreditoFiscal', 'csv'],
        'rcf'               => ['iva_simple', 'rcf', 'IVASimple_RestitucionCreditoFiscal', 'csv'],
        'ventas_cbte'       => ['lid', 'ventas_cbte', 'LIBRO_IVA_DIGITAL_VENTAS_CBTE', 'txt'],
        'ventas_alicuotas'  => ['lid', 'ventas_alicuotas', 'LIBRO_IVA_DIGITAL_VENTAS_ALICUOTAS', 'txt'],
        'compras_cbte'      => ['lid', 'compras_cbte', 'LIBRO_IVA_DIGITAL_COMPRAS_CBTE', 'txt'],
        'compras_alicuotas' => ['lid', 'compras_alicuotas', 'LIBRO_IVA_DIGITAL_COMPRAS_ALICUOTAS', 'txt'],
    ];

    public function __construct(private ExportacionArcaService $exportacion)
    {
    }

    public function index(Request $request)
    {
        $periodo = $this->periodo($request);
        $empresa = app('empresa_activa');

        $regimen = $request->input('regimen') ?: ($empresa->condicion_iva === 'exento' ? 'lid' : 'iva_simple');

        return view('iva.arca', [
            'periodo'   => $periodo,
            'regimen'   => $regimen,
            'simple'    => $this->exportacion->ivaSimple($periodo),
            'lid'       => $this->exportacion->libroIvaDigital($periodo),
            'cerrado'   => IvaPosicion::estaCerrado($periodo),
            'archivos'  => self::ARCHIVOS,
        ]);
    }

    /** Descarga un archivo suelto o el ZIP de un régimen (zip_iva_simple | zip_lid) */
    public function descargar(Request $request, string $archivo)
    {
        $periodo = $this->periodo($request);
        $simple = null;
        $lid = null;
        $datos = function (string $regimen) use (&$simple, &$lid, $periodo) {
            return $regimen === 'lid'
                ? ($lid ??= $this->exportacion->libroIvaDigital($periodo))
                : ($simple ??= $this->exportacion->ivaSimple($periodo));
        };

        if (str_starts_with($archivo, 'zip_')) {
            $regimen = substr($archivo, 4);
            abort_unless(in_array($regimen, ['iva_simple', 'lid'], true), 404);
            abort_unless(class_exists(ZipArchive::class), 500, 'La extensión zip de PHP no está habilitada.');

            $tmp = tempnam(sys_get_temp_dir(), 'arca');
            $zip = new ZipArchive();
            $zip->open($tmp, ZipArchive::OVERWRITE);
            foreach (self::ARCHIVOS as [$reg, $clave, $nombre, $ext]) {
                if ($reg === $regimen) {
                    $zip->addFromString($this->nombre($nombre, $periodo, $ext), $this->contenido($datos($reg)[$clave], $ext));
                }
            }
            $zip->close();

            return response()->download($tmp, $this->nombre($regimen === 'lid' ? 'LibroIVADigital' : 'IVASimple', $periodo, 'zip'))
                ->deleteFileAfterSend();
        }

        abort_unless(isset(self::ARCHIVOS[$archivo]), 404);
        [$reg, $clave, $nombre, $ext] = self::ARCHIVOS[$archivo];

        return response($this->contenido($datos($reg)[$clave], $ext), 200, [
            'Content-Type'        => $ext === 'csv' ? 'text/csv; charset=UTF-8' : 'text/plain; charset=ISO-8859-1',
            'Content-Disposition' => 'attachment; filename="' . $this->nombre($nombre, $periodo, $ext) . '"',
        ]);
    }

    /* ---------------------------------------------------------------- */

    /** Líneas separadas por CRLF (formato Windows que esperan los aplicativos de ARCA) */
    private function contenido(array $lineas, string $ext): string
    {
        return $lineas ? implode("\r\n", $lineas) . "\r\n" : '';
    }

    private function nombre(string $base, string $periodo, string $ext): string
    {
        return $base . '_' . app('empresa_activa')->cuit . '_' . str_replace('-', '', $periodo) . '.' . $ext;
    }

    private function periodo(Request $request): string
    {
        $p = (string) $request->input('periodo');
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $p) ? $p : today()->subMonthNoOverflow()->format('Y-m');
    }
}
