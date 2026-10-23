<?php

namespace App\Services;

use App\Models\Comprobante;
use App\Models\CuentaConfig;
use App\Models\Ejercicio;
use App\Models\Tercero;
use App\Support\Afip;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Graba comprobantes de compra y venta y genera (o actualiza) su asiento contable.
 *
 * Venta (factura / ND):                     Compra (factura / ND):
 *   D  Deudores ............ total            D  Compras / gasto ...... neto + no gravado + exento
 *   H  Ventas .............. netos            D  IVA Crédito Fiscal ... IVA
 *   H  IVA Débito Fiscal ... IVA              D  Percepciones sufridas
 *   H  Percepciones practicadas               H  Proveedores .......... total
 * Las notas de crédito invierten el asiento.
 */
class ComprobanteService
{
    public function __construct(private AsientoService $asientos)
    {
    }

    public function guardar(array $d, string $circuito, ?Comprobante $comprobante = null): Comprobante
    {
        $tipo = (int) $d['tipo_comprobante'];
        $discrimina = Afip::discriminaIva($tipo, $circuito);

        $alicuotas = [];
        $netoGravado = 0;
        $iva = 0;
        if ($discrimina) {
            foreach ($d['alicuotas'] ?? [] as $a) {
                $neto = AsientoService::aCentavos($a['neto']);
                $imp = AsientoService::aCentavos($a['iva']);
                if ($neto === 0 && $imp === 0) {
                    continue;
                }
                $alicuotas[] = ['alicuota_id' => (int) $a['alicuota_id'], 'neto' => $neto / 100, 'iva' => $imp / 100];
                $netoGravado += $neto;
                $iva += $imp;
            }
        }

        $c = fn ($k) => AsientoService::aCentavos($d[$k] ?? 0);
        $total = $netoGravado + $iva + $c('no_gravado') + $c('exento') + $c('percepcion_iva') + $c('percepcion_iibb') + $c('otros_tributos');

        $ejercicio = Ejercicio::where('fecha_inicio', '<=', $d['fecha'])->where('fecha_cierre', '>=', $d['fecha'])->firstOrFail();

        return DB::transaction(function () use ($d, $circuito, $comprobante, $tipo, $alicuotas, $netoGravado, $iva, $c, $total, $ejercicio) {
            $comprobante ??= new Comprobante(['circuito' => $circuito, 'user_id' => auth()->id(), 'estado' => 'registrado']);

            $comprobante->fill([
                'ejercicio_id'         => $ejercicio->id,
                'tercero_id'           => $d['tercero_id'],
                'tipo_comprobante'     => $tipo,
                'punto_venta'          => $d['punto_venta'],
                'numero'               => $d['numero'],
                'fecha'                => $d['fecha'],
                'periodo_iva'          => $d['periodo_iva'],
                'cae'                  => $d['cae'] ?? null,
                'vto_cae'              => $d['vto_cae'] ?? null,
                'cuenta_imputacion_id' => $d['cuenta_imputacion_id'],
                'operacion_iva'        => $d['operacion_iva'] ?? 1,
                'neto_gravado'         => $netoGravado / 100,
                'no_gravado'           => $c('no_gravado') / 100,
                'exento'               => $c('exento') / 100,
                'iva'                  => $iva / 100,
                'percepcion_iva'       => $c('percepcion_iva') / 100,
                'percepcion_iibb'      => $c('percepcion_iibb') / 100,
                'otros_tributos'       => $c('otros_tributos') / 100,
                'total'                => $total / 100,
                'observaciones'        => $d['observaciones'] ?? null,
            ]);
            $comprobante->save();

            $comprobante->alicuotas()->delete();
            $comprobante->alicuotas()->createMany($alicuotas);

            $asiento = $this->asientos->guardar([
                'fecha'    => $comprobante->fecha->toDateString(),
                'tipo'     => 'normal',
                'concepto' => $this->concepto($comprobante),
                'accion'   => 'registrar',
                'origen'   => $circuito === 'venta' ? 'iva_ventas' : 'iva_compras',
                'lineas'   => $this->lineasAsiento($comprobante),
            ], $ejercicio, $comprobante->asiento);

            $comprobante->update(['asiento_id' => $asiento->id]);

            return $comprobante->fresh(['alicuotas', 'tercero', 'asiento']);
        });
    }

    public function anular(Comprobante $comprobante, string $motivo): void
    {
        DB::transaction(function () use ($comprobante, $motivo) {
            $comprobante->update([
                'estado'        => 'anulado',
                'observaciones' => trim(($comprobante->observaciones ? $comprobante->observaciones . ' · ' : '') . 'Anulado: ' . $motivo),
            ]);

            if ($comprobante->asiento && $comprobante->asiento->estado === 'registrado') {
                $this->asientos->anular($comprobante->asiento, "Anulación de {$comprobante->descripcion_corta}: {$motivo}");
            }
        });
    }

    /* ---------------------------------------------------------------- */

    private function concepto(Comprobante $c): string
    {
        $tercero = $c->tercero ?? Tercero::find($c->tercero_id);
        $prefijo = $c->circuito === 'venta' ? 'Venta' : 'Compra';

        return mb_substr("{$prefijo} {$c->descripcion_corta} - {$tercero->razon_social}", 0, 255);
    }

    /** Arma las líneas del asiento en centavos, agrupando por cuenta. */
    private function lineasAsiento(Comprobante $c): array
    {
        $tercero = $c->tercero ?? Tercero::find($c->tercero_id);
        $venta = $c->circuito === 'venta';
        $cent = fn ($v) => AsientoService::aCentavos((float) $v);

        $netos = $cent($c->neto_gravado) + $cent($c->no_gravado) + $cent($c->exento);

        // [clave o id de cuenta, centavos, lado (D|H) para una factura]
        $movimientos = $venta
            ? [
                [$tercero->cuenta_id ? (int) $tercero->cuenta_id : 'deudores_ventas', $cent($c->total), 'D'],
                [(int) $c->cuenta_imputacion_id, $netos, 'H'],
                ['iva_debito', $cent($c->iva), 'H'],
                ['perc_iva_practicadas', $cent($c->percepcion_iva), 'H'],
                ['perc_iibb_practicadas', $cent($c->percepcion_iibb), 'H'],
                ['otros_tributos_ventas', $cent($c->otros_tributos), 'H'],
            ]
            : [
                [(int) $c->cuenta_imputacion_id, $netos, 'D'],
                ['iva_credito', $cent($c->iva), 'D'],
                ['perc_iva_sufridas', $cent($c->percepcion_iva), 'D'],
                ['perc_iibb_sufridas', $cent($c->percepcion_iibb), 'D'],
                ['otros_tributos_compras', $cent($c->otros_tributos), 'D'],
                [$tercero->cuenta_id ? (int) $tercero->cuenta_id : 'proveedores', $cent($c->total), 'H'],
            ];

        $invertir = $c->esNotaCredito();
        $saldos = [];   // cuenta_id => centavos (positivo = Debe)

        foreach ($movimientos as [$cuenta, $importe, $lado]) {
            if ($importe === 0) {
                continue;
            }
            $cuentaId = is_int($cuenta) ? $cuenta : CuentaConfig::idPara($cuenta);
            if (! $cuentaId) {
                $desc = CuentaConfig::CLAVES[$cuenta][1] ?? $cuenta;
                throw ValidationException::withMessages([
                    'cuenta_imputacion_id' => "No está configurada la cuenta «{$desc}». Configúrela en Configuración › Cuentas automáticas.",
                ]);
            }
            $signo = (($lado === 'D') !== $invertir) ? 1 : -1;
            $saldos[$cuentaId] = ($saldos[$cuentaId] ?? 0) + $signo * $importe;
        }

        $lineas = [];
        foreach ($saldos as $cuentaId => $saldo) {
            if ($saldo === 0) {
                continue;
            }
            $lineas[] = [
                'cuenta_id' => $cuentaId,
                'debe'      => $saldo > 0 ? $saldo / 100 : 0,
                'haber'     => $saldo < 0 ? -$saldo / 100 : 0,
                'detalle'   => null,
            ];
        }

        // Debe primero, Haber después (presentación clásica)
        usort($lineas, fn ($a, $b) => ($b['debe'] > 0) <=> ($a['debe'] > 0));

        return $lineas;
    }
}
