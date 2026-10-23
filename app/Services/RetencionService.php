<?php

namespace App\Services;

use App\Models\CuentaConfig;
use App\Models\Ejercicio;
use App\Models\RegimenRetencion;
use App\Models\Retencion;
use App\Models\Tercero;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Retenciones practicadas (como agente) y sufridas (certificados recibidos).
 *
 * Practicada:  D  Proveedor ........................ importe     (reduce lo que se le paga)
 *              H  Retenciones X a depositar ........ importe
 * Sufrida:     D  Retenciones X sufridas ........... importe     (crédito contra el impuesto)
 *              H  Cliente (deudores por ventas) .... importe     (cancela parte de lo que debía)
 */
class RetencionService
{
    public function __construct(private AsientoService $asientos)
    {
    }

    /**
     * Calcula la retención de un pago.
     * Con acumulación mensual (Ganancias RG 830): se suman los pagos del mes al mismo proveedor y régimen,
     * se descuenta el mínimo no sujeto y lo ya retenido en el mes.
     *
     * @return array ['importe', 'alicuota', 'base_sujeta', 'acumulado', 'retenido_mes', 'detalle']
     */
    public function calcular(RegimenRetencion $regimen, int $terceroId, float $base, Carbon $fecha, string $condicion, ?int $excluirId = null): array
    {
        $alicuota = $regimen->alicuotaPara($condicion);
        $mns = (float) $regimen->minimo_no_sujeto;
        $acumulado = 0.0;
        $retenidoMes = 0.0;

        if ($regimen->acumula_mensual) {
            $mes = Retencion::where('tipo', 'practicada')
                ->where('regimen_id', $regimen->id)
                ->where('tercero_id', $terceroId)
                ->where('periodo', $fecha->format('Y-m'))
                ->where('estado', 'registrado')
                ->when($excluirId, fn ($q) => $q->whereKeyNot($excluirId))
                ->selectRaw('COALESCE(SUM(base_imponible),0) AS base, COALESCE(SUM(importe),0) AS retenido')
                ->first();
            $acumulado = (float) $mes->base;
            $retenidoMes = (float) $mes->retenido;
        }

        $baseSujeta = max(0, $acumulado + $base - $mns);
        $importe = round($baseSujeta * $alicuota / 100 - $retenidoMes, 2);
        $importe = max(0, $importe);

        $detalle = $regimen->acumula_mensual
            ? sprintf('(Pagos del mes %s + este pago %s − mínimo no sujeto %s) × %s%% − retenido en el mes %s',
                $this->m($acumulado), $this->m($base), $this->m($mns), $this->m($alicuota), $this->m($retenidoMes))
            : sprintf('(Base %s − mínimo no sujeto %s) × %s%%', $this->m($base), $this->m($mns), $this->m($alicuota));

        if ($importe > 0 && $importe < (float) $regimen->minimo_retencion) {
            $detalle .= ' · No supera el mínimo de retención (' . $this->m((float) $regimen->minimo_retencion) . ').';
            $importe = 0;
        }

        return compact('importe', 'alicuota', 'baseSujeta', 'acumulado', 'retenidoMes', 'detalle');
    }

    public function guardar(array $d, string $tipo, ?Retencion $retencion = null): Retencion
    {
        $fecha = Carbon::parse($d['fecha'])->startOfDay();
        $ejercicio = Ejercicio::where('fecha_inicio', '<=', $fecha)->where('fecha_cierre', '>=', $fecha)->firstOrFail();

        return DB::transaction(function () use ($d, $tipo, $retencion, $fecha, $ejercicio) {
            $retencion ??= new Retencion(['tipo' => $tipo, 'user_id' => auth()->id(), 'estado' => 'registrado']);

            $retencion->fill([
                'ejercicio_id'        => $ejercicio->id,
                'impuesto'            => $d['impuesto'],
                'regimen_id'          => $d['regimen_id'] ?? null,
                'tercero_id'          => $d['tercero_id'],
                'comprobante_id'      => $d['comprobante_id'] ?? null,
                'fecha'               => $fecha,
                'periodo'             => $fecha->format('Y-m'),
                'condicion'           => $d['condicion'] ?? 'inscripto',
                'importe_comprobante' => $d['importe_comprobante'] ?? 0,
                'base_imponible'      => $d['base_imponible'] ?? 0,
                'alicuota'            => $d['alicuota'] ?? 0,
                'importe'             => $d['importe'],
                'observaciones'       => $d['observaciones'] ?? null,
            ]);

            // Número de certificado: el del agente (sufrida) o correlativo propio por impuesto y año (practicada)
            if ($tipo === 'sufrida') {
                $retencion->numero_certificado = $d['numero_certificado'] ?? null;
            } elseif (! $retencion->numero_certificado && (float) $retencion->importe > 0) {
                $retencion->numero_certificado = $this->siguienteCertificado($retencion->impuesto, $fecha->year);
            }

            $retencion->save();

            // Asiento (solo si hay importe)
            if ((float) $retencion->importe > 0) {
                $asiento = $this->asientos->guardar([
                    'fecha'    => $fecha->toDateString(),
                    'tipo'     => 'normal',
                    'concepto' => $this->concepto($retencion),
                    'accion'   => 'registrar',
                    'origen'   => $tipo === 'practicada' ? 'ret_practicada' : 'ret_sufrida',
                    'lineas'   => $this->lineas($retencion),
                ], $ejercicio, $retencion->asiento);
                $retencion->update(['asiento_id' => $asiento->id]);
            } elseif ($retencion->asiento && $retencion->asiento->estado === 'registrado') {
                $this->asientos->anular($retencion->asiento, 'La retención quedó sin importe');
                $retencion->update(['asiento_id' => null]);
            }

            return $retencion->fresh(['tercero', 'regimen', 'asiento']);
        });
    }

    public function anular(Retencion $retencion, string $motivo): void
    {
        DB::transaction(function () use ($retencion, $motivo) {
            $retencion->update([
                'estado'        => 'anulado',
                'observaciones' => trim(($retencion->observaciones ? $retencion->observaciones . ' · ' : '') . 'Anulada: ' . $motivo),
            ]);
            if ($retencion->asiento && $retencion->asiento->estado === 'registrado') {
                $this->asientos->anular($retencion->asiento, 'Anulación de retención: ' . $motivo);
            }
        });
    }

    /** Correlativo AAAA-NNNNNN por impuesto */
    public function siguienteCertificado(string $impuesto, int $anio): string
    {
        $ultimo = Retencion::where('tipo', 'practicada')
            ->where('impuesto', $impuesto)
            ->where('numero_certificado', 'like', $anio . '-%')
            ->lockForUpdate()
            ->pluck('numero_certificado')
            ->map(fn ($n) => (int) substr($n, 5))
            ->max();

        return $anio . '-' . str_pad((string) ((int) $ultimo + 1), 6, '0', STR_PAD_LEFT);
    }

    /* ---------------------------------------------------------------- */

    private function lineas(Retencion $r): array
    {
        $tercero = $r->tercero ?? Tercero::find($r->tercero_id);
        $claveImpuesto = Retencion::CLAVES_CUENTA[$r->tipo][$r->impuesto];
        $cuentaImpuesto = CuentaConfig::idPara($claveImpuesto);
        $cuentaTercero = $tercero->cuenta_id
            ?: CuentaConfig::idPara($r->tipo === 'practicada' ? 'proveedores' : 'deudores_ventas');

        foreach ([$claveImpuesto => $cuentaImpuesto, ($r->tipo === 'practicada' ? 'proveedores' : 'deudores_ventas') => $cuentaTercero] as $clave => $id) {
            if (! $id) {
                throw ValidationException::withMessages([
                    'importe' => 'Falta configurar la cuenta «' . (CuentaConfig::CLAVES[$clave][1] ?? $clave) . '» en Configuración › Cuentas automáticas.',
                ]);
            }
        }

        $importe = (float) $r->importe;

        return $r->tipo === 'practicada'
            ? [
                ['cuenta_id' => $cuentaTercero, 'debe' => $importe, 'haber' => 0, 'detalle' => $tercero->razon_social],
                ['cuenta_id' => $cuentaImpuesto, 'debe' => 0, 'haber' => $importe, 'detalle' => 'Cert. ' . $r->numero_certificado],
            ]
            : [
                ['cuenta_id' => $cuentaImpuesto, 'debe' => $importe, 'haber' => 0, 'detalle' => 'Cert. ' . $r->numero_certificado],
                ['cuenta_id' => $cuentaTercero, 'debe' => 0, 'haber' => $importe, 'detalle' => $tercero->razon_social],
            ];
    }

    private function concepto(Retencion $r): string
    {
        $tercero = $r->tercero ?? Tercero::find($r->tercero_id);
        $texto = ($r->tipo === 'practicada' ? 'Retención practicada ' : 'Retención sufrida ')
            . $r->impuesto_texto . ' - ' . $tercero->razon_social
            . ($r->numero_certificado ? ' - Cert. ' . $r->numero_certificado : '');

        return mb_substr($texto, 0, 255);
    }

    private function m(float $v): string
    {
        return number_format($v, 2, ',', '.');
    }
}
