<?php

namespace App\Services;

use App\Models\Asiento;
use App\Models\Ejercicio;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de grabación de asientos:
 *  - Borrador: puede estar desbalanceado, no tiene número, no impacta en libros.
 *  - Registrado: balanceado (Debe = Haber), con número correlativo dentro del ejercicio.
 *  - Anulado: conserva el número pero queda fuera de los libros.
 */
class AsientoService
{
    /**
     * @param array $datos  fecha, tipo, concepto, accion (borrador|registrar),
     *                      lineas: [[cuenta_id, debe, haber, detalle], ...]
     */
    public function guardar(array $datos, Ejercicio $ejercicio, ?Asiento $asiento = null): Asiento
    {
        return DB::transaction(function () use ($datos, $ejercicio, $asiento) {
            $registrar = ($datos['accion'] ?? 'borrador') === 'registrar'
                || ($asiento && $asiento->estado === 'registrado');

            $lineas = [];
            $totalDebe = 0;
            foreach (array_values($datos['lineas']) as $i => $l) {
                $debe = self::aCentavos($l['debe'] ?? 0);
                $haber = self::aCentavos($l['haber'] ?? 0);
                $totalDebe += $debe;

                $lineas[] = [
                    'cuenta_id' => (int) $l['cuenta_id'],
                    'debe'      => $debe / 100,
                    'haber'     => $haber / 100,
                    'detalle'   => isset($l['detalle']) && trim($l['detalle']) !== '' ? trim($l['detalle']) : null,
                    'orden'     => $i + 1,
                ];
            }

            $asiento ??= new Asiento([
                'ejercicio_id' => $ejercicio->id,
                'user_id'      => auth()->id(),
                'origen'       => $datos['origen'] ?? 'manual',
            ]);

            $asiento->fill([
                'fecha'    => $datos['fecha'],
                'tipo'     => $datos['tipo'] ?? 'normal',
                'concepto' => trim($datos['concepto']),
                'total'    => $totalDebe / 100,
                'estado'   => $registrar ? 'registrado' : 'borrador',
            ]);

            if ($registrar && ! $asiento->numero) {
                $asiento->numero = $this->siguienteNumero($ejercicio);
            }

            $asiento->save();

            $asiento->lineas()->delete();
            $asiento->lineas()->createMany($lineas);

            return $asiento->fresh('lineas.cuenta');
        });
    }

    public function siguienteNumero(Ejercicio $ejercicio): int
    {
        $ultimo = Asiento::where('ejercicio_id', $ejercicio->id)->lockForUpdate()->max('numero');

        return (int) $ultimo + 1;
    }

    public function anular(Asiento $asiento, string $motivo): void
    {
        $asiento->update([
            'estado'           => 'anulado',
            'anulado_at'       => now(),
            'anulado_por'      => auth()->id(),
            'motivo_anulacion' => $motivo,
        ]);
    }

    /**
     * Renumera los asientos registrados y anulados del ejercicio por fecha,
     * con apertura primero y cierre último dentro de cada día.
     * Devuelve la cantidad de asientos renumerados.
     */
    public function renumerar(Ejercicio $ejercicio): int
    {
        return DB::transaction(function () use ($ejercicio) {
            $asientos = Asiento::where('ejercicio_id', $ejercicio->id)
                ->whereIn('estado', ['registrado', 'anulado'])
                ->lockForUpdate()
                ->get()
                ->sort(function ($a, $b) {
                    return [$a->fecha->format('Ymd'), Asiento::PRIORIDAD_TIPO[$a->tipo] ?? 9, $a->numero, $a->id]
                       <=> [$b->fecha->format('Ymd'), Asiento::PRIORIDAD_TIPO[$b->tipo] ?? 9, $b->numero, $b->id];
                })
                ->values();

            // Primero se liberan los números para no chocar con el índice único
            Asiento::whereIn('id', $asientos->pluck('id'))->update(['numero' => null]);

            foreach ($asientos as $i => $asiento) {
                Asiento::whereKey($asiento->id)->update(['numero' => $i + 1]);
            }

            return $asientos->count();
        });
    }

    /** Convierte un importe (acepta "1.234,56", "1234.56" o número) a centavos enteros. */
    public static function aCentavos(mixed $valor): int
    {
        if (is_int($valor) || is_float($valor)) {
            return (int) round($valor * 100);
        }

        $v = trim((string) $valor);
        if ($v === '') {
            return 0;
        }

        // Formato argentino: punto de miles y coma decimal
        if (str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        }

        return (int) round(((float) $v) * 100);
    }
}
