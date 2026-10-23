<?php

namespace App\Services;

use App\Models\Comprobante;
use App\Models\CuentaConfig;
use App\Models\Ejercicio;
use App\Models\IvaPosicion;
use App\Support\Afip;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Libros IVA Ventas / Compras y posición mensual de IVA.
 * Los importes de las notas de crédito se toman con signo negativo.
 */
class LibroIvaService
{
    public function __construct(private AsientoService $asientos)
    {
    }

    /* ----------------------------------------------------------------
     | Libro IVA
     * ---------------------------------------------------------------- */

    /**
     * Filas del libro con importes ya firmados y desglose de IVA por alícuota.
     * @return array ['filas' => Collection, 'totales' => array, 'alicuotas' => array]
     */
    public function libro(string $circuito, string $periodo): array
    {
        $comprobantes = Comprobante::with(['tercero', 'alicuotas'])
            ->where('circuito', $circuito)
            ->where('periodo_iva', $periodo)
            ->where('estado', 'registrado')
            ->when($circuito === 'venta',
                fn ($q) => $q->orderBy('fecha')->orderBy('tipo_comprobante')->orderBy('punto_venta')->orderBy('numero'),
                fn ($q) => $q->orderBy('fecha')->orderBy('id'))
            ->get();

        $totales = array_fill_keys(['neto', 'iva21', 'iva105', 'iva_otras', 'no_gravado', 'exento', 'percepcion_iva', 'percepcion_iibb', 'otros', 'total'], 0.0);
        $porAlicuota = [];

        $filas = $comprobantes->map(function (Comprobante $c) use (&$totales, &$porAlicuota) {
            $s = $c->signo();
            $iva = ['21' => 0.0, '105' => 0.0, 'otras' => 0.0];

            foreach ($c->alicuotas as $a) {
                $clave = match ($a->alicuota_id) { 5 => '21', 4 => '105', default => 'otras' };
                $iva[$clave] += $s * (float) $a->iva;

                $porAlicuota[$a->alicuota_id]['neto'] = ($porAlicuota[$a->alicuota_id]['neto'] ?? 0) + $s * (float) $a->neto;
                $porAlicuota[$a->alicuota_id]['iva'] = ($porAlicuota[$a->alicuota_id]['iva'] ?? 0) + $s * (float) $a->iva;
            }

            $fila = (object) [
                'comprobante'     => $c,
                'neto'            => $s * (float) $c->neto_gravado,
                'iva21'           => $iva['21'],
                'iva105'          => $iva['105'],
                'iva_otras'       => $iva['otras'],
                'no_gravado'      => $s * (float) $c->no_gravado,
                'exento'          => $s * (float) $c->exento,
                'percepcion_iva'  => $s * (float) $c->percepcion_iva,
                'percepcion_iibb' => $s * (float) $c->percepcion_iibb,
                'otros'           => $s * (float) $c->otros_tributos,
                'total'           => $s * (float) $c->total,
            ];

            foreach (array_keys($totales) as $k) {
                $totales[$k] += $fila->$k;
            }

            return $fila;
        });

        ksort($porAlicuota);

        return [
            'filas'     => $filas,
            'totales'   => array_map(fn ($v) => round($v, 2), $totales),
            'alicuotas' => array_map(fn ($a) => ['neto' => round($a['neto'], 2), 'iva' => round($a['iva'], 2)], $porAlicuota),
        ];
    }

    /* ----------------------------------------------------------------
     | Posición mensual
     * ---------------------------------------------------------------- */

    /**
     * Calcula la posición del período (o devuelve la ya cerrada).
     * $manual: saldos anteriores y retenciones cargados a mano (solo si el período está abierto).
     */
    public function posicion(string $periodo, array $manual = []): array
    {
        if ($cerrada = IvaPosicion::where('periodo', $periodo)->first()) {
            return ['cerrada' => $cerrada] + $cerrada->only([
                'debito_fiscal', 'credito_fiscal', 'saldo_tecnico_anterior', 'saldo_libre_anterior', 'percepciones',
                'retenciones', 'impuesto_determinado', 'saldo_tecnico_favor', 'saldo_libre_favor', 'a_pagar',
            ]) + ['anterior' => IvaPosicion::anteriorA($periodo)];
        }

        $sumas = fn (string $circuito) => Comprobante::where('circuito', $circuito)
            ->where('periodo_iva', $periodo)->where('estado', 'registrado')
            ->get(['tipo_comprobante', 'iva', 'percepcion_iva'])
            ->reduce(fn ($acc, $c) => [
                'iva'  => $acc['iva'] + $c->signo() * (float) $c->iva,
                'perc' => $acc['perc'] + $c->signo() * (float) $c->percepcion_iva,
            ], ['iva' => 0.0, 'perc' => 0.0]);

        $ventas = $sumas('venta');
        $compras = $sumas('compra');
        $anterior = IvaPosicion::anteriorA($periodo);

        $tecnicoAnt = $anterior ? (float) $anterior->saldo_tecnico_favor : (float) ($manual['saldo_tecnico_anterior'] ?? 0);
        $libreAnt = $anterior ? (float) $anterior->saldo_libre_favor : (float) ($manual['saldo_libre_anterior'] ?? 0);
        // Retenciones de IVA sufridas: certificados cargados en el período + lo que se agregue a mano
        $certificados = round((float) \App\Models\Retencion::where('tipo', 'sufrida')->where('impuesto', 'iva')
            ->where('periodo', $periodo)->where('estado', 'registrado')->sum('importe'), 2);
        $retenciones = $certificados + (float) ($manual['retenciones'] ?? 0);

        return ['cerrada' => null, 'anterior' => $anterior, 'retenciones_certificados' => $certificados]
            + $this->calcular(round($ventas['iva'], 2), round($compras['iva'], 2), $tecnicoAnt, $libreAnt, round($compras['perc'], 2), $retenciones);
    }

    /** Liquidación: saldo técnico primero, después ingresos a cuenta (percepciones, retenciones, saldo de libre disponibilidad). */
    public function calcular(float $df, float $cf, float $tecnicoAnt, float $libreAnt, float $percepciones, float $retenciones): array
    {
        $c = fn ($v) => AsientoService::aCentavos($v);

        $tecnico = $c($df) - $c($cf) - $c($tecnicoAnt);
        $determinado = max($tecnico, 0);
        $tecnicoFavor = max(-$tecnico, 0);

        $saldo = $determinado - $c($percepciones) - $c($retenciones) - $c($libreAnt);
        $aPagar = max($saldo, 0);
        $libreFavor = max(-$saldo, 0);

        return [
            'debito_fiscal'          => $df,
            'credito_fiscal'         => $cf,
            'saldo_tecnico_anterior' => $tecnicoAnt,
            'saldo_libre_anterior'   => $libreAnt,
            'percepciones'           => $percepciones,
            'retenciones'            => $retenciones,
            'impuesto_determinado'   => $determinado / 100,
            'saldo_tecnico_favor'    => $tecnicoFavor / 100,
            'saldo_libre_favor'      => $libreFavor / 100,
            'a_pagar'                => $aPagar / 100,
        ];
    }

    /**
     * Cierra el período: guarda la posición, genera el asiento de liquidación
     * y bloquea los comprobantes del período.
     */
    public function cerrar(string $periodo, array $manual, bool $generarAsiento): IvaPosicion
    {
        $datos = $this->posicion($periodo, $manual);
        unset($datos['cerrada'], $datos['anterior'], $datos['retenciones_certificados']);

        return DB::transaction(function () use ($periodo, $datos, $generarAsiento) {
            $posicion = IvaPosicion::create($datos + ['periodo' => $periodo, 'user_id' => auth()->id()]);

            if ($generarAsiento) {
                $asiento = $this->generarAsiento($posicion);
                $posicion->update(['asiento_id' => $asiento?->id]);
            }

            return $posicion;
        });
    }

    /** Reabre el último período cerrado: anula su asiento y elimina la posición. */
    public function reabrir(IvaPosicion $posicion): void
    {
        $posterior = IvaPosicion::where('periodo', '>', $posicion->periodo)->exists();
        if ($posterior) {
            throw ValidationException::withMessages(['periodo' => 'Solo se puede reabrir el último período cerrado.']);
        }

        DB::transaction(function () use ($posicion) {
            if ($posicion->asiento && $posicion->asiento->estado === 'registrado') {
                if (! $posicion->asiento->esEditable()) {
                    throw ValidationException::withMessages(['periodo' => 'El asiento de liquidación está en un ejercicio cerrado o período bloqueado.']);
                }
                $this->asientos->anular($posicion->asiento, 'Reapertura del período IVA ' . $posicion->periodo_texto);
            }
            $posicion->delete();
        });
    }

    /**
     * Asiento de liquidación al último día del período:
     *   D IVA Débito Fiscal                 H IVA Crédito Fiscal
     *   D IVA saldo técnico (nuevo)         H IVA saldo técnico (anterior)
     *   D IVA libre disponibilidad (nuevo)  H Percepciones / Retenciones IVA sufridas
     *                                       H IVA libre disponibilidad (anterior)
     *                                       H IVA a pagar
     */
    private function generarAsiento(IvaPosicion $p)
    {
        $fecha = Carbon::createFromFormat('Y-m-d', $p->periodo . '-01')->endOfMonth()->startOfDay();
        $ejercicio = Ejercicio::where('fecha_inicio', '<=', $fecha)->where('fecha_cierre', '>=', $fecha)->first();

        if (! $ejercicio || ! $ejercicio->admiteFecha($fecha)) {
            throw ValidationException::withMessages(['periodo' => 'No hay un ejercicio abierto (y sin bloqueo) para el ' . $fecha->format('d/m/Y') . '. Cierre sin asiento o habilite el período.']);
        }

        // [clave de cuenta, importe con signo: + Debe / − Haber]
        $movs = [
            ['iva_debito', (float) $p->debito_fiscal],
            ['iva_credito', -(float) $p->credito_fiscal],
            ['iva_saldo_tecnico', (float) $p->saldo_tecnico_favor - (float) $p->saldo_tecnico_anterior],
            ['iva_saldo_libre', (float) $p->saldo_libre_favor - (float) $p->saldo_libre_anterior],
            ['perc_iva_sufridas', -(float) $p->percepciones],
            ['ret_iva_sufridas', -(float) $p->retenciones],
            ['iva_a_pagar', -(float) $p->a_pagar],
        ];

        $saldos = [];
        foreach ($movs as [$clave, $importe]) {
            $cent = AsientoService::aCentavos($importe);
            if ($cent === 0) {
                continue;
            }
            $cuentaId = CuentaConfig::idPara($clave);
            if (! $cuentaId) {
                throw ValidationException::withMessages([
                    'periodo' => 'Falta configurar la cuenta «' . (CuentaConfig::CLAVES[$clave][1] ?? $clave) . '» en Configuración › Cuentas automáticas.',
                ]);
            }
            $saldos[$cuentaId] = ($saldos[$cuentaId] ?? 0) + $cent;
        }

        $lineas = collect($saldos)->filter()->map(fn ($s, $id) => [
            'cuenta_id' => $id,
            'debe'      => $s > 0 ? $s / 100 : 0,
            'haber'     => $s < 0 ? -$s / 100 : 0,
            'detalle'   => null,
        ])->sortByDesc(fn ($l) => $l['debe'] > 0)->values()->all();

        if (count($lineas) < 2) {
            return null; // período sin movimientos de IVA
        }

        return $this->asientos->guardar([
            'fecha'    => $fecha->toDateString(),
            'tipo'     => 'normal',
            'concepto' => 'Liquidación de IVA período ' . $p->periodo_texto,
            'accion'   => 'registrar',
            'origen'   => 'iva_posicion',
            'lineas'   => $lineas,
        ], $ejercicio);
    }

    /** Períodos con comprobantes (para el selector) */
    public function periodosConMovimientos(): Collection
    {
        return Comprobante::query()->distinct()->orderByDesc('periodo_iva')->pluck('periodo_iva');
    }
}
