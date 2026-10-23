<?php

namespace App\Services;

use App\Models\Comprobante;
use App\Models\Ejercicio;
use App\Models\IvaPosicion;
use App\Models\Tercero;
use App\Support\Afip;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Importa la descarga de «Mis Comprobantes» de ARCA (Emitidos → ventas, Recibidos → compras).
 *
 * Las columnas se reconocen por su encabezado, así funciona con:
 *  - el formato clásico (Imp. Neto Gravado / IVA / Imp. Total), deduciendo la alícuota, y
 *  - el formato nuevo (2025) con neto gravado e IVA por alícuota.
 * Acepta XLSX/XLS (requiere phpoffice/phpspreadsheet) y CSV.
 */
class ImportadorMisComprobantes
{
    /** campo => encabezados posibles (normalizados: minúsculas, sin acentos ni puntuación) */
    private const COLUMNAS = [
        'fecha'          => ['fecha de emision', 'fecha emision', 'fecha'],
        'tipo'           => ['tipo de comprobante', 'tipo comprobante', 'tipo'],
        'pv'             => ['punto de venta', 'pto vta', 'pto de venta', 'pv'],
        'numero_desde'   => ['numero desde', 'nro desde', 'numero de comprobante', 'numero', 'nro'],
        'numero_hasta'   => ['numero hasta', 'nro hasta'],
        'cae'            => ['cod autorizacion', 'codigo de autorizacion', 'codigo autorizacion', 'cae'],
        'tipo_doc'       => ['tipo doc receptor', 'tipo doc emisor', 'tipo documento receptor', 'tipo documento emisor', 'tipo de documento', 'tipo doc'],
        'nro_doc'        => ['nro doc receptor', 'nro doc emisor', 'numero documento receptor', 'numero documento emisor', 'nro documento', 'numero de documento', 'nro doc', 'cuit'],
        'denominacion'   => ['denominacion receptor', 'denominacion emisor', 'denominacion', 'razon social'],
        'tipo_cambio'    => ['tipo cambio', 'tipo de cambio'],
        'moneda'         => ['moneda'],
        'neto_gravado'   => ['imp neto gravado total', 'neto gravado total', 'imp neto gravado', 'importe neto gravado', 'neto gravado'],
        'no_gravado'     => ['imp neto no gravado', 'importe neto no gravado', 'neto no gravado', 'no gravado'],
        'exento'         => ['imp op exentas', 'importe operaciones exentas', 'op exentas', 'exento', 'exentas'],
        'otros_tributos' => ['otros tributos', 'imp otros tributos', 'importe otros tributos'],
        'perc_iva'       => ['percepcion iva', 'percepciones iva', 'perc iva', 'percepciones o pagos a cuenta de iva'],
        'perc_iibb'      => ['percepcion iibb', 'percepciones iibb', 'perc iibb', 'percepciones de ingresos brutos', 'percepcion ingresos brutos'],
        'iva'            => ['total iva', 'iva total', 'imp iva', 'iva'],
        'total'          => ['imp total', 'importe total', 'total'],
    ];

    /** porcentaje => código ARCA */
    private const ALICUOTAS = ['21' => 5, '10.5' => 4, '27' => 6, '5' => 8, '2.5' => 9, '0' => 3];

    public array $errores = [];

    /* ================================================================
     | 1) Lectura del archivo → filas normalizadas
     * ================================================================ */

    public function leer(string $ruta, string $extension): array
    {
        $matriz = in_array(strtolower($extension), ['xlsx', 'xls'], true) ? $this->leerExcel($ruta) : $this->leerCsv($ruta);
        if (! $matriz) {
            $this->errores[] = 'El archivo está vacío o no se pudo leer.';
            return [];
        }

        // Encabezado: la primera de las 6 primeras filas que tenga fecha, tipo y total
        $filaEnc = null;
        $mapa = [];
        foreach (array_slice($matriz, 0, 6, true) as $i => $fila) {
            $mapa = $this->mapearColumnas($fila);
            if (isset($mapa['fecha'], $mapa['tipo'], $mapa['total'])) {
                $filaEnc = $i;
                break;
            }
        }

        if ($filaEnc === null) {
            $this->errores[] = 'No se encontraron los encabezados esperados (Fecha, Tipo, Imp. Total). Use el archivo tal como lo descarga «Mis Comprobantes».';
            return [];
        }

        $filas = [];
        foreach (array_slice($matriz, $filaEnc + 1, null, true) as $i => $valores) {
            if (! array_filter($valores, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }
            $filas[] = $this->normalizarFila($valores, $mapa, $i + 1);
        }

        return $filas;
    }

    /* ================================================================
     | 2) Análisis: estado de cada fila contra la base
     * ================================================================ */

    /** Agrega a cada fila: estado (nuevo|duplicado|error|omitido), mensajes, tercero_id */
    public function analizar(array $filas, string $circuito): array
    {
        $empresa = app('empresa_activa');
        $tiposPermitidos = array_keys(Afip::tiposPara($circuito, $empresa->condicion_iva));
        $terceros = Tercero::whereNotNull('nro_documento')->get()->keyBy(fn ($t) => $t->tipo_documento . '|' . $t->nro_documento);
        $ejercicios = Ejercicio::all();
        $cerrados = IvaPosicion::pluck('periodo')->flip();

        foreach ($filas as &$f) {
            $f['mensajes'] = $f['mensajes'] ?? [];
            $f['estado'] = empty($f['mensajes']) ? 'nuevo' : 'error';

            if ($f['estado'] === 'error') {
                continue;
            }

            if (! isset(Afip::TIPOS_COMPROBANTE[$f['tipo']])) {
                $f['estado'] = 'omitido';
                $f['mensajes'][] = "Tipo de comprobante {$f['tipo']} no soportado.";
                continue;
            }
            if (! in_array($f['tipo'], $tiposPermitidos, true)) {
                $f['estado'] = 'error';
                $f['mensajes'][] = 'Tipo de comprobante no válido para la condición de IVA de la empresa.';
                continue;
            }

            $fecha = Carbon::parse($f['fecha']);
            $ej = $ejercicios->first(fn ($e) => $fecha->between($e->fecha_inicio, $e->fecha_cierre));
            if (! $ej) {
                $f['estado'] = 'error';
                $f['mensajes'][] = 'No hay ejercicio contable para la fecha ' . $fecha->format('d/m/Y') . '.';
                continue;
            }
            if (! $ej->admiteFecha($fecha)) {
                $f['estado'] = 'error';
                $f['mensajes'][] = 'El ejercicio está cerrado o el período bloqueado para esa fecha.';
                continue;
            }
            if (isset($cerrados[$fecha->format('Y-m')])) {
                $f['estado'] = 'error';
                $f['mensajes'][] = 'El período IVA ' . $fecha->format('m/Y') . ' está cerrado.';
                continue;
            }

            // Tercero existente (por tipo y número de documento)
            $tercero = $terceros->get($f['tipo_doc'] . '|' . $f['nro_doc']);
            $f['tercero_id'] = $tercero?->id;
            $f['tercero_nombre'] = $tercero?->razon_social ?? $f['denominacion'];

            // Duplicado
            $dup = Comprobante::where('circuito', $circuito)
                ->where('tipo_comprobante', $f['tipo'])->where('punto_venta', $f['pv'])->where('numero', $f['numero'])
                ->where('estado', 'registrado')
                ->when($circuito === 'compra', fn ($q) => $q->where('tercero_id', $tercero?->id ?? 0))
                ->exists();
            if ($dup) {
                $f['estado'] = 'duplicado';
                $f['mensajes'][] = 'Ya está cargado.';
                continue;
            }

            if (! $tercero) {
                $f['mensajes'][] = 'Se dará de alta ' . ($circuito === 'venta' ? 'el cliente' : 'el proveedor') . '.';
            }
            if ($f['numero_hasta'] && $f['numero_hasta'] != $f['numero']) {
                $f['mensajes'][] = "Rango {$f['numero']} a {$f['numero_hasta']}: se registra como un solo comprobante.";
            }
        }

        return $filas;
    }

    /* ================================================================
     | 3) Importación
     * ================================================================ */

    /**
     * @param array $opciones cuenta_imputacion_id, usar_habitual, operacion_iva, periodo (null = mes de la fecha)
     * @return array ['importados' => int, 'errores' => [linea => mensaje]]
     */
    public function importar(array $filas, string $circuito, array $opciones, ComprobanteService $service): array
    {
        $importados = 0;
        $errores = [];
        $vistos = [];

        foreach ($filas as $f) {
            if ($f['estado'] !== 'nuevo') {
                continue;
            }

            // Comprobante repetido dentro del mismo archivo
            $clave = $f['tipo'] . '|' . $f['pv'] . '|' . $f['numero'] . ($circuito === 'compra' ? '|' . $f['nro_doc'] : '');
            if (isset($vistos[$clave])) {
                $errores[$f['linea']] = 'Comprobante repetido en el archivo (línea ' . $vistos[$clave] . ').';
                continue;
            }
            $vistos[$clave] = $f['linea'];

            try {
                // Comprobantes que en este circuito no discriminan IVA (p. ej. B recibidos): todo a no gravado
                if (! Afip::discriminaIva($f['tipo'], $circuito) && $f['alicuotas']) {
                    $f['no_gravado'] = round($f['no_gravado'] + array_sum(array_map(fn ($a) => $a['neto'] + $a['iva'], $f['alicuotas'])), 2);
                    $f['alicuotas'] = [];
                }

                $tercero = $f['tercero_id'] ? Tercero::find($f['tercero_id']) : $this->crearTercero($f, $circuito);

                // Si ya existía con el otro rol, pasa a «ambos»
                $rol = $circuito === 'venta' ? 'cliente' : 'proveedor';
                if (! in_array($tercero->tipo, [$rol, 'ambos'], true)) {
                    $tercero->update(['tipo' => 'ambos']);
                }

                $cuenta = $opciones['usar_habitual'] && $tercero->cuenta_imputacion_id
                    ? $tercero->cuenta_imputacion_id
                    : $opciones['cuenta_imputacion_id'];

                $operacion = $circuito === 'compra' && $tercero->operacion_iva ? $tercero->operacion_iva : $opciones['operacion_iva'];

                $service->guardar([
                    'tercero_id'           => $tercero->id,
                    'tipo_comprobante'     => $f['tipo'],
                    'punto_venta'          => $f['pv'],
                    'numero'               => $f['numero'],
                    'fecha'                => $f['fecha'],
                    'periodo_iva'          => $opciones['periodo'] ?: substr($f['fecha'], 0, 7),
                    'cae'                  => $f['cae'] ?: null,
                    'cuenta_imputacion_id' => $cuenta,
                    'operacion_iva'        => $operacion,
                    'alicuotas'            => $f['alicuotas'],
                    'no_gravado'           => $f['no_gravado'],
                    'exento'               => $f['exento'],
                    'percepcion_iva'       => $f['perc_iva'],
                    'percepcion_iibb'      => $f['perc_iibb'],
                    'otros_tributos'       => $f['otros_tributos'],
                    'observaciones'        => 'Importado de Mis Comprobantes' . ($f['numero_hasta'] && $f['numero_hasta'] != $f['numero'] ? " (hasta N° {$f['numero_hasta']})" : ''),
                ], $circuito);

                $importados++;
            } catch (\Illuminate\Validation\ValidationException $e) {
                $errores[$f['linea']] = collect($e->errors())->flatten()->first();
            } catch (\Throwable $e) {
                $errores[$f['linea']] = $e->getMessage();
            }
        }

        return compact('importados', 'errores');
    }

    /* ================================================================
     | Auxiliares
     * ================================================================ */

    private function normalizarFila(array $v, array $mapa, int $linea): array
    {
        $g = fn ($campo) => isset($mapa[$campo]) ? ($v[$mapa[$campo]] ?? null) : null;
        $n = fn ($campo) => $this->numero($g($campo));
        $mensajes = [];

        $fecha = $this->fecha($g('fecha'));
        if (! $fecha) {
            $mensajes[] = 'Fecha inválida.';
        }

        $tipo = (int) preg_replace('/^\D*(\d+).*$/s', '$1', (string) $g('tipo'));
        $tc = $n('tipo_cambio') ?: 1.0;
        $moneda = strtoupper(trim((string) $g('moneda')));
        $factor = ($moneda !== '' && ! in_array($moneda, ['PES', '$', 'ARS'], true)) ? $tc : 1.0;
        $x = fn ($campo) => round($n($campo) * $factor, 2);

        $total = $x('total');
        $netoGravado = $x('neto_gravado');
        $iva = $x('iva');

        // Alícuotas: columnas por alícuota (formato nuevo) o deducción desde los totales
        $alicuotas = [];
        foreach ($mapa['_alicuotas'] ?? [] as $pct => $cols) {
            $neto = isset($cols['neto']) ? round($this->numero($v[$cols['neto']] ?? null) * $factor, 2) : 0.0;
            $imp = isset($cols['iva']) ? round($this->numero($v[$cols['iva']] ?? null) * $factor, 2) : 0.0;
            if ($neto != 0 || $imp != 0) {
                $alicuotas[] = ['alicuota_id' => self::ALICUOTAS[$pct], 'neto' => $neto, 'iva' => $imp];
            }
        }

        if (! $alicuotas && $tipo && Afip::letra($tipo) !== 'C' && ($netoGravado > 0 || $iva > 0)) {
            $deducidas = $this->deducirAlicuotas($netoGravado, $iva);
            if ($deducidas === null) {
                $mensajes[] = 'No se pudo deducir la alícuota de IVA (neto ' . number_format($netoGravado, 2, ',', '.') . ', IVA ' . number_format($iva, 2, ',', '.') . ').';
            } else {
                $alicuotas = $deducidas;
            }
        }

        $noGravado = $x('no_gravado');
        $exento = $x('exento');

        // Comprobantes C (y B recibidos): sin IVA discriminado → todo el importe como no gravado
        if ($tipo && Afip::letra($tipo) === 'C') {
            $alicuotas = [];
            $noGravado = round($total - $x('perc_iva') - $x('perc_iibb') - $x('otros_tributos'), 2);
            $exento = 0.0;
        }

        $sumaAli = array_sum(array_map(fn ($a) => $a['neto'] + $a['iva'], $alicuotas));
        $otros = $x('otros_tributos');
        $diferencia = round($total - ($sumaAli + $noGravado + $exento + $x('perc_iva') + $x('perc_iibb') + $otros), 2);

        // Lo que no está discriminado (p. ej. percepciones en el formato viejo) va a otros tributos
        if ($diferencia > 0.01) {
            $otros = round($otros + $diferencia, 2);
        } elseif ($diferencia < -0.01) {
            $mensajes[] = 'Los importes parciales superan el total (diferencia ' . number_format($diferencia, 2, ',', '.') . ').';
        }

        $tipoDoc = (int) preg_replace('/\D/', '', (string) $g('tipo_doc')) ?: 80;
        $nroDoc = preg_replace('/\D/', '', (string) $g('nro_doc'));
        if ($nroDoc === '' || (int) $nroDoc === 0) {
            $tipoDoc = 99;
            $nroDoc = null;
        }

        return [
            'linea'          => $linea,
            'fecha'          => $fecha?->toDateString(),
            'tipo'           => $tipo,
            'pv'             => (int) preg_replace('/\D/', '', (string) $g('pv')),
            'numero'         => (int) preg_replace('/\D/', '', (string) $g('numero_desde')),
            'numero_hasta'   => (int) preg_replace('/\D/', '', (string) $g('numero_hasta')),
            'cae'            => preg_replace('/\D/', '', (string) $g('cae')),
            'tipo_doc'       => $tipoDoc,
            'nro_doc'        => $nroDoc,
            'denominacion'   => trim((string) $g('denominacion')) ?: 'Consumidor final',
            'alicuotas'      => $alicuotas,
            'no_gravado'     => $noGravado,
            'exento'         => $exento,
            'perc_iva'       => $x('perc_iva'),
            'perc_iibb'      => $x('perc_iibb'),
            'otros_tributos' => $otros,
            'iva'            => round(array_sum(array_column($alicuotas, 'iva')), 2),
            'total'          => $total,
            'moneda'         => $moneda ?: 'PES',
            'mensajes'       => $mensajes,
        ];
    }

    /** Una alícuota si el cociente coincide; si no, combinación de dos (21/10,5 primero). */
    private function deducirAlicuotas(float $neto, float $iva): ?array
    {
        if ($neto <= 0) {
            return null;
        }

        foreach (self::ALICUOTAS as $pct => $id) {
            if ($pct !== '0' && abs(round($neto * (float) $pct / 100, 2) - $iva) <= 0.05) {
                return [['alicuota_id' => $id, 'neto' => $neto, 'iva' => $iva]];
            }
        }
        if ($iva == 0) {
            return [['alicuota_id' => 3, 'neto' => $neto, 'iva' => 0.0]];
        }

        $pares = [['21', '10.5'], ['21', '27'], ['21', '5'], ['10.5', '5'], ['21', '2.5'], ['10.5', '2.5']];
        foreach ($pares as [$a, $b]) {
            $pa = (float) $a;
            $pb = (float) $b;
            $netoA = round((100 * $iva - $pb * $neto) / ($pa - $pb), 2);
            $netoB = round($neto - $netoA, 2);
            if ($netoA > 0 && $netoB > 0) {
                $ivaA = round($netoA * $pa / 100, 2);
                return [
                    ['alicuota_id' => self::ALICUOTAS[$a], 'neto' => $netoA, 'iva' => $ivaA],
                    ['alicuota_id' => self::ALICUOTAS[$b], 'neto' => $netoB, 'iva' => round($iva - $ivaA, 2)],
                ];
            }
        }

        return null;
    }

    private function mapearColumnas(array $encabezados): array
    {
        $mapa = [];
        $norm = array_map(fn ($h) => $this->normalizar((string) $h), $encabezados);

        // Columnas por alícuota: «Neto Gravado IVA 21%», «IVA 21%», «Imp. Neto Gravado 10,5%», etc.
        foreach ($norm as $i => $h) {
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*%/', $h, $mm)) {
                $pct = rtrim(rtrim(str_replace(',', '.', $mm[1]), '0'), '.') ?: '0';
                if (! isset(self::ALICUOTAS[$pct])) {
                    continue;
                }
                $clave = str_contains($h, 'neto') ? 'neto' : (str_contains($h, 'iva') ? 'iva' : null);
                if ($clave) {
                    $mapa['_alicuotas'][$pct][$clave] = $i;
                }
                $norm[$i] = '';    // no se usa para los campos generales
            }
        }

        foreach (self::COLUMNAS as $campo => $alias) {
            foreach ($alias as $a) {
                $i = array_search($a, $norm, true);
                if ($i !== false && ! in_array($i, $mapa, true)) {
                    $mapa[$campo] = $i;
                    break;
                }
            }
        }

        return $mapa;
    }

    private function normalizar(string $h): string
    {
        $h = mb_strtolower(Str::ascii(trim($h)));
        $h = preg_replace('/[^a-z0-9%,. ]/', ' ', $h);
        $h = str_replace('.', ' ', preg_replace('/(\d)\.(\d)/', '$1#$2', $h));   // conserva 10.5
        $h = str_replace('#', '.', $h);
        return trim(preg_replace('/\s+/', ' ', $h));
    }

    /** Acepta 1.234,56 · 1234,56 · 1234.56 · 1,234.56 · números de Excel */
    private function numero($v): float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = trim(str_replace(['$', ' '], '', (string) $v));
        if ($s === '' || $s === '-') {
            return 0.0;
        }
        $coma = strrpos($s, ',');
        $punto = strrpos($s, '.');
        if ($coma !== false && ($punto === false || $coma > $punto)) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        } else {
            $s = str_replace(',', '', $s);
        }
        return (float) $s;
    }

    private function fecha($v): ?Carbon
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v) && class_exists(\PhpOffice\PhpSpreadsheet\Shared\Date::class)) {
            return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v))->startOfDay();
        }
        $s = trim((string) $v);
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y', 'Y-m-d H:i:s'] as $formato) {
            try {
                $f = Carbon::createFromFormat($formato, $s);
                if ($f && $f->format($formato) === $s) {
                    return $f->startOfDay();
                }
            } catch (\Throwable) {
            }
        }
        return null;
    }

    private function leerCsv(string $ruta): array
    {
        $contenido = (string) file_get_contents($ruta);
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }
        $lineas = preg_split('/\r\n|\r|\n/', trim($contenido));
        $muestra = implode("\n", array_slice($lineas, 0, 3));
        $sep = substr_count($muestra, ';') >= substr_count($muestra, ',') ? ';' : ',';

        return array_map(fn ($l) => str_getcsv($l, $sep), $lineas);
    }

    private function leerExcel(string $ruta): array
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            $this->errores[] = 'Para leer archivos Excel instale phpoffice/phpspreadsheet (composer require phpoffice/phpspreadsheet) o use la descarga en CSV.';
            return [];
        }

        $libro = \PhpOffice\PhpSpreadsheet\IOFactory::load($ruta);
        return $libro->getActiveSheet()->toArray(null, true, false, false);
    }

    private function crearTercero(array $f, string $circuito): Tercero
    {
        $letra = Afip::letra($f['tipo']);
        $conCuit = in_array($f['tipo_doc'], [80, 86], true);

        // Condición inferida por la letra (se puede corregir después)
        $condicion = $circuito === 'compra'
            ? ($letra === 'C' ? 6 : 1)
            : match ($letra) { 'A', 'M' => 1, 'B' => $conCuit ? 4 : 5, default => $conCuit ? 1 : 5 };

        // Sin documento (consumidor final): se reutiliza un único tercero por denominación
        if (! $f['nro_doc']) {
            return Tercero::firstOrCreate(
                ['tipo_documento' => 99, 'razon_social' => mb_substr($f['denominacion'], 0, 255)],
                ['tipo' => $circuito === 'venta' ? 'cliente' : 'proveedor', 'condicion_iva' => 5, 'activo' => true]
            );
        }

        return Tercero::create([
            'tipo'           => $circuito === 'venta' ? 'cliente' : 'proveedor',
            'razon_social'   => mb_substr($f['denominacion'], 0, 255),
            'tipo_documento' => $f['tipo_doc'],
            'nro_documento'  => $f['nro_doc'],
            'condicion_iva'  => $condicion,
            'observaciones'  => 'Alta automática al importar Mis Comprobantes. Verificar la condición frente al IVA.',
            'activo'         => true,
        ]);
    }
}
