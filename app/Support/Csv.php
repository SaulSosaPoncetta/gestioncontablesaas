<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportación a CSV compatible con Excel en español:
 * separador punto y coma, BOM UTF-8 y números con coma decimal.
 */
class Csv
{
    public static function descargar(string $archivo, array $encabezado, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($encabezado, $filas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $encabezado, ';');
            foreach ($filas as $fila) {
                fputcsv($out, array_map(fn ($v) => is_float($v) ? self::numero($v) : $v, $fila), ';');
            }
            fclose($out);
        }, $archivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** 1234.5 → "1234,50" (sin separador de miles para que Excel lo tome como número) */
    public static function numero(float $v): string
    {
        return number_format($v, 2, ',', '');
    }
}
