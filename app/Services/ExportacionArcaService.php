<?php

namespace App\Services;

use App\Models\Comprobante;
use App\Support\Afip;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Archivos para presentar IVA ante ARCA.
 *
 * 1) IVA Simple (F.2051) – Responsables Inscriptos (desde período 11/2025):
 *    CSV de "Apertura de otros conceptos": Débito Fiscal, Restitución de DF, Crédito Fiscal y Restitución de CF.
 *    Separador ";", decimales con ",", sin encabezado.
 *
 * 2) Libro IVA Digital (RG 4597) – desde 12/2025 solo sujetos exentos en IVA (RG 5707):
 *    TXT de ancho fijo: ventas comprobantes (266), ventas alícuotas (62), compras comprobantes (325), compras alícuotas (84).
 */
class ExportacionArcaService
{
    /* ================================================================
     | IVA SIMPLE
     * ================================================================ */

    /** @return array ['df' => [...], 'rdf' => [...], 'cf' => [...], 'rcf' => [...], 'avisos' => [...], 'resumen' => [...]] */
    public function ivaSimple(string $periodo): array
    {
        $empresa = app('empresa_activa');
        $actividad = (string) $empresa->actividad_afip;
        $avisos = [];

        if (! preg_match('/^\d{6}$/', $actividad)) {
            $avisos[] = 'La empresa no tiene cargado el código de actividad ARCA (6 dígitos). Cárguelo en «Mi empresa»: es obligatorio en los archivos de débito fiscal.';
        }

        $g = ['df' => [], 'rdf' => [], 'cf' => [], 'rcf' => []];

        // ---- Ventas → Débito fiscal / Restitución de débito fiscal
        foreach ($this->comprobantes('venta', $periodo) as $c) {
            $nc = $c->esNotaCredito();
            $sujeto = Afip::tipoSujeto((int) $c->tercero->condicion_iva);

            foreach ($c->alicuotas as $a) {
                // En restitución el tipo 1 incluye bienes de uso
                $tipo = $nc ? 1 : (int) $c->operacion_iva;
                $clave = "{$tipo}|{$sujeto}|{$a->alicuota_id}";
                $destino = $nc ? 'rdf' : 'df';
                $g[$destino][$clave]['neto'] = ($g[$destino][$clave]['neto'] ?? 0) + (float) $a->neto;
                $g[$destino][$clave]['iva'] = ($g[$destino][$clave]['iva'] ?? 0) + (float) $a->iva;
            }

            $exento = (float) $c->exento + (float) $c->no_gravado;
            if ($exento != 0) {
                $tipo = $nc ? 2 : 3;
                $clave = "{$tipo}||";
                $destino = $nc ? 'rdf' : 'df';
                $g[$destino][$clave]['exento'] = ($g[$destino][$clave]['exento'] ?? 0) + $exento;
            }
        }

        // ---- Compras → Crédito fiscal / Restitución (solo comprobantes con IVA discriminado)
        foreach ($this->comprobantes('compra', $periodo) as $c) {
            $nc = $c->esNotaCredito();
            foreach ($c->alicuotas as $a) {
                $clave = (int) $c->operacion_iva . '|' . $a->alicuota_id;
                $destino = $nc ? 'rcf' : 'cf';
                $g[$destino][$clave]['neto'] = ($g[$destino][$clave]['neto'] ?? 0) + (float) $a->neto;
                $g[$destino][$clave]['iva'] = ($g[$destino][$clave]['iva'] ?? 0) + (float) $a->iva;
            }
        }

        $n = fn ($v) => number_format(round((float) $v, 2), 2, ',', '');

        $lineasDf = [];
        foreach ($this->ordenar($g['df']) as $clave => $v) {
            [$tipo, $sujeto, $alic] = explode('|', $clave);
            $lineasDf[] = $tipo === '3'
                ? "{$actividad};3;;;;;;" . $n($v['exento'])
                : "{$actividad};{$tipo};{$sujeto};{$alic};" . $n($v['neto']) . ';' . $n($v['iva']) . ';;';
        }

        $lineasRdf = [];
        foreach ($this->ordenar($g['rdf']) as $clave => $v) {
            [$tipo, $sujeto, $alic] = explode('|', $clave);
            $lineasRdf[] = $tipo === '2'
                ? "{$actividad};2;;;;;;" . $n($v['exento'])
                : "{$actividad};1;{$sujeto};{$alic};" . $n($v['neto']) . ';' . $n($v['iva']) . ';;';
        }

        $lineasCf = [];
        foreach ($this->ordenar($g['cf']) as $clave => $v) {
            [$concepto, $alic] = explode('|', $clave);
            // Crédito computable = facturado (sin prorrateo por operaciones exentas)
            $lineasCf[] = "{$concepto};{$alic};" . $n($v['neto']) . ';' . $n($v['iva']) . ';' . $n($v['iva']) . ';';
        }

        $lineasRcf = [];
        foreach ($this->ordenar($g['rcf']) as $clave => $v) {
            [$concepto, $alic] = explode('|', $clave);
            $lineasRcf[] = "{$concepto};{$alic};" . $n($v['neto']) . ';' . $n($v['iva']) . ';';
        }

        return [
            'df'      => $lineasDf,
            'rdf'     => $lineasRdf,
            'cf'      => $lineasCf,
            'rcf'     => $lineasRcf,
            'avisos'  => $avisos,
            'resumen' => [
                'debito'      => round(collect($g['df'])->sum('iva'), 2),
                'rest_debito' => round(collect($g['rdf'])->sum('iva'), 2),
                'credito'     => round(collect($g['cf'])->sum('iva'), 2),
                'rest_credito'=> round(collect($g['rcf'])->sum('iva'), 2),
                'exento'      => round(collect($g['df'])->sum('exento'), 2),
            ],
        ];
    }

    /* ================================================================
     | LIBRO IVA DIGITAL (sujetos exentos)
     * ================================================================ */

    /** @return array ['ventas_cbte' => string[], 'ventas_alicuotas' => string[], 'compras_cbte' => string[], 'compras_alicuotas' => string[], 'avisos' => string[]] */
    public function libroIvaDigital(string $periodo): array
    {
        $avisos = [];
        $vc = $va = $cc = $ca = [];

        foreach ($this->comprobantes('venta', $periodo) as $c) {
            $doc = $this->documento($c, $avisos);
            $alicuotas = $this->alicuotasLid($c, 'venta');

            $linea = $this->fecha($c->fecha)
                . $this->num($c->tipo_comprobante, 3)
                . $this->num($c->punto_venta, 5)
                . $this->num($c->numero, 20)
                . $this->num($c->numero, 20)
                . $this->num($doc['tipo'], 2)
                . $this->num($doc['numero'], 20)
                . $this->txt($c->tercero->razon_social, 30)
                . $this->imp($c->total)
                . $this->imp(Afip::discriminaIva($c->tipo_comprobante, 'venta') ? $c->no_gravado : 0)
                . $this->imp(0)                                   // percepción a no categorizados
                . $this->imp(Afip::discriminaIva($c->tipo_comprobante, 'venta') ? $c->exento : 0)
                . $this->imp($c->percepcion_iva)                  // percepciones / pagos a cuenta impuestos nacionales
                . $this->imp($c->percepcion_iibb)
                . $this->imp(0)                                   // percepciones municipales
                . $this->imp(0)                                   // impuestos internos
                . 'PES'
                . '0001000000'
                . count($alicuotas)
                . $this->codigoOperacion($c)
                . $this->imp($c->otros_tributos)
                . '00000000';

            $vc[] = $this->verificarLargo($linea, 266, $c, $avisos);

            foreach ($alicuotas as $a) {
                $va[] = $this->verificarLargo(
                    $this->num($c->tipo_comprobante, 3) . $this->num($c->punto_venta, 5) . $this->num($c->numero, 20)
                    . $this->imp($a['neto']) . $this->num($a['alicuota_id'], 4) . $this->imp($a['iva']),
                    62, $c, $avisos);
            }
        }

        foreach ($this->comprobantes('compra', $periodo) as $c) {
            $doc = $this->documento($c, $avisos);
            $alicuotas = $this->alicuotasLid($c, 'compra');

            $linea = $this->fecha($c->fecha)
                . $this->num($c->tipo_comprobante, 3)
                . $this->num($c->punto_venta, 5)
                . $this->num($c->numero, 20)
                . str_repeat(' ', 16)                             // despacho de importación
                . $this->num($doc['tipo'], 2)
                . $this->num($doc['numero'], 20)
                . $this->txt($c->tercero->razon_social, 30)
                . $this->imp($c->total)
                . $this->imp(Afip::discriminaIva($c->tipo_comprobante, 'compra') ? $c->no_gravado : 0)
                . $this->imp(Afip::discriminaIva($c->tipo_comprobante, 'compra') ? $c->exento : 0)
                . $this->imp($c->percepcion_iva)
                . $this->imp(0)                                   // percepciones otros impuestos nacionales
                . $this->imp($c->percepcion_iibb)
                . $this->imp(0)                                   // percepciones municipales
                . $this->imp(0)                                   // impuestos internos
                . 'PES'
                . '0001000000'
                . count($alicuotas)
                . $this->codigoOperacion($c)
                . $this->imp(0)                                   // crédito fiscal computable (el exento no computa)
                . $this->imp($c->otros_tributos)
                . str_repeat('0', 11)                             // CUIT emisor / corredor
                . str_repeat(' ', 30)                             // denominación emisor / corredor
                . $this->imp(0);                                  // IVA comisión

            $cc[] = $this->verificarLargo($linea, 325, $c, $avisos);

            foreach ($alicuotas as $a) {
                $ca[] = $this->verificarLargo(
                    $this->num($c->tipo_comprobante, 3) . $this->num($c->punto_venta, 5) . $this->num($c->numero, 20)
                    . $this->num($doc['tipo'], 2) . $this->num($doc['numero'], 20)
                    . $this->imp($a['neto']) . $this->num($a['alicuota_id'], 4) . $this->imp($a['iva']),
                    84, $c, $avisos);
            }
        }

        return [
            'ventas_cbte'       => $vc,
            'ventas_alicuotas'  => $va,
            'compras_cbte'      => $cc,
            'compras_alicuotas' => $ca,
            'avisos'            => array_values(array_unique($avisos)),
        ];
    }

    /* ---------------------------------------------------------------- */

    private function comprobantes(string $circuito, string $periodo): Collection
    {
        return Comprobante::with(['tercero', 'alicuotas'])
            ->where('circuito', $circuito)
            ->where('periodo_iva', $periodo)
            ->where('estado', 'registrado')
            ->orderBy('fecha')->orderBy('tipo_comprobante')->orderBy('punto_venta')->orderBy('numero')
            ->get();
    }

    private function ordenar(array $grupos): array
    {
        ksort($grupos, SORT_NATURAL);
        return $grupos;
    }

    /** Alícuotas a informar: si discrimina IVA y no tiene ninguna, se informa una al 0 %. */
    private function alicuotasLid(Comprobante $c, string $circuito): array
    {
        if (! Afip::discriminaIva($c->tipo_comprobante, $circuito)) {
            return [];
        }
        $lista = $c->alicuotas->map(fn ($a) => ['alicuota_id' => $a->alicuota_id, 'neto' => $a->neto, 'iva' => $a->iva])->all();

        return $lista ?: [['alicuota_id' => 3, 'neto' => 0, 'iva' => 0]];
    }

    /** Z/X exportaciones · E exento · N no gravado · 0 no corresponde */
    private function codigoOperacion(Comprobante $c): string
    {
        if ((float) $c->iva > 0 || ! Afip::discriminaIva($c->tipo_comprobante, $c->circuito)) {
            return '0';
        }
        if ((float) $c->exento > 0) {
            return 'E';
        }
        if ((float) $c->no_gravado > 0) {
            return 'N';
        }
        return '0';
    }

    private function documento(Comprobante $c, array &$avisos): array
    {
        $t = $c->tercero;
        $tipo = (int) $t->tipo_documento;
        $numero = preg_replace('/\D/', '', (string) $t->nro_documento);

        if ($tipo === 99 || $numero === '') {
            if (in_array(Afip::letra($c->tipo_comprobante), ['A', 'M'], true)) {
                $avisos[] = "{$c->descripcion_corta}: {$t->razon_social} no tiene CUIT cargado (obligatorio en comprobantes A/M).";
            }
            return ['tipo' => 99, 'numero' => '0'];
        }

        return ['tipo' => $tipo, 'numero' => $numero];
    }

    private function verificarLargo(string $linea, int $largo, Comprobante $c, array &$avisos): string
    {
        if (strlen($linea) !== $largo) {
            $avisos[] = "{$c->descripcion_corta}: registro de " . strlen($linea) . " caracteres (se esperaban {$largo}).";
        }
        return $linea;
    }

    private function fecha($fecha): string
    {
        return $fecha->format('Ymd');
    }

    private function num($valor, int $largo): string
    {
        return str_pad(substr(preg_replace('/\D/', '', (string) $valor), -$largo), $largo, '0', STR_PAD_LEFT);
    }

    /** Importe en centavos, sin signo, 15 posiciones */
    private function imp($valor): string
    {
        return str_pad((string) abs(AsientoService::aCentavos((float) $valor)), 15, '0', STR_PAD_LEFT);
    }

    /** Texto ASCII sin acentos, alineado a izquierda */
    private function txt(?string $valor, int $largo): string
    {
        $v = strtoupper(Str::ascii((string) $valor));
        $v = preg_replace('/[^A-Z0-9 .,&\-\/]/', ' ', $v);
        return str_pad(substr($v, 0, $largo), $largo, ' ', STR_PAD_RIGHT);
    }
}
