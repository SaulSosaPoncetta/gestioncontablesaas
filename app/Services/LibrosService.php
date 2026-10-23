<?php

namespace App\Services;

use App\Models\Asiento;
use App\Models\Cuenta;
use App\Models\Ejercicio;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consultas de los libros contables. Solo computan asientos REGISTRADOS
 * (los anulados aparecen en el Diario con su leyenda, sin importes).
 */
class LibrosService
{
    /* ----------------------------------------------------------------
     | Libro Diario
     * ---------------------------------------------------------------- */

    public function diarioQuery(Ejercicio $ejercicio, Carbon $desde, Carbon $hasta)
    {
        return Asiento::query()
            ->with(['lineas.cuenta:id,codigo,nombre'])
            ->where('ejercicio_id', $ejercicio->id)
            ->whereIn('estado', ['registrado', 'anulado'])
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('numero');
    }

    /** Totales del período (solo registrados) */
    public function totalesDiario(Ejercicio $ejercicio, Carbon $desde, Carbon $hasta): array
    {
        $t = $this->lineasBase($ejercicio, $desde, $hasta)
            ->selectRaw('COALESCE(SUM(l.debe),0) AS debe, COALESCE(SUM(l.haber),0) AS haber, COUNT(DISTINCT a.id) AS asientos')
            ->first();

        return ['debe' => (float) $t->debe, 'haber' => (float) $t->haber, 'asientos' => (int) $t->asientos];
    }

    /* ----------------------------------------------------------------
     | Libro Mayor
     * ---------------------------------------------------------------- */

    /**
     * Mayor de una cuenta: saldo anterior al período (dentro del ejercicio),
     * movimientos con saldo acumulado y totales.
     */
    public function mayor(Ejercicio $ejercicio, Cuenta $cuenta, Carbon $desde, Carbon $hasta): array
    {
        $anterior = 0.0;
        if ($desde->gt($ejercicio->fecha_inicio)) {
            $r = $this->lineasBase($ejercicio, $ejercicio->fecha_inicio, $desde->copy()->subDay())
                ->where('l.cuenta_id', $cuenta->id)
                ->selectRaw('COALESCE(SUM(l.debe),0) - COALESCE(SUM(l.haber),0) AS saldo')
                ->first();
            $anterior = round((float) $r->saldo, 2);
        }

        $movimientos = $this->lineasBase($ejercicio, $desde, $hasta)
            ->where('l.cuenta_id', $cuenta->id)
            ->select('a.id AS asiento_id', 'a.numero', 'a.fecha', 'a.concepto', 'l.detalle', 'l.debe', 'l.haber')
            ->orderBy('a.fecha')->orderBy('a.numero')->orderBy('l.orden')
            ->get();

        $saldo = $anterior;
        $debe = 0.0;
        $haber = 0.0;
        foreach ($movimientos as $m) {
            $m->debe = (float) $m->debe;
            $m->haber = (float) $m->haber;
            $debe += $m->debe;
            $haber += $m->haber;
            $saldo = round($saldo + $m->debe - $m->haber, 2);
            $m->saldo = $saldo;
            $m->fecha = Carbon::parse($m->fecha);
        }

        return [
            'cuenta'      => $cuenta,
            'anterior'    => $anterior,
            'movimientos' => $movimientos,
            'debe'        => round($debe, 2),
            'haber'       => round($haber, 2),
            'saldo'       => $saldo,
        ];
    }

    /** IDs de las cuentas con movimientos en el período (para el mayor de todas las cuentas). */
    public function cuentasConMovimientos(Ejercicio $ejercicio, Carbon $desde, Carbon $hasta): Collection
    {
        $ids = $this->lineasBase($ejercicio, $ejercicio->fecha_inicio, $hasta)
            ->distinct()->pluck('l.cuenta_id');

        return Cuenta::planOrdenado(Cuenta::whereIn('id', $ids));
    }

    /* ----------------------------------------------------------------
     | Balance de Sumas y Saldos
     * ---------------------------------------------------------------- */

    /**
     * @param int  $nivel             nivel máximo a mostrar (las cuentas imputables de nivel menor también se muestran)
     * @param bool $incluirSinMov     incluir cuentas sin movimientos
     * @return array ['filas' => Collection, 'totales' => [...], 'niveles' => int]
     */
    public function sumasYSaldos(Ejercicio $ejercicio, Carbon $desde, Carbon $hasta, ?int $nivel = null, bool $incluirSinMov = false): array
    {
        // Sumas por cuenta imputable
        $sumas = $this->lineasBase($ejercicio, $desde, $hasta)
            ->groupBy('l.cuenta_id')
            ->selectRaw('l.cuenta_id, SUM(l.debe) AS debe, SUM(l.haber) AS haber')
            ->get()
            ->keyBy('cuenta_id');

        $plan = Cuenta::planOrdenado();
        $maxNivel = (int) ($plan->max('nivel') ?: 1);
        $nivel = $nivel ? min($nivel, $maxNivel) : $maxNivel;

        // Acumula cada cuenta en sí misma y en todos sus ancestros (por prefijo de código)
        $acum = [];
        $porId = $plan->keyBy('id');
        foreach ($sumas as $cuentaId => $s) {
            $cuenta = $porId->get($cuentaId);
            if (! $cuenta) {
                continue;
            }
            $partes = explode('.', $cuenta->codigo);
            for ($i = 1; $i <= count($partes); $i++) {
                $codigo = implode('.', array_slice($partes, 0, $i));
                $acum[$codigo]['debe'] = ($acum[$codigo]['debe'] ?? 0) + (float) $s->debe;
                $acum[$codigo]['haber'] = ($acum[$codigo]['haber'] ?? 0) + (float) $s->haber;
            }
        }

        $filas = collect();
        $totales = ['debe' => 0.0, 'haber' => 0.0, 'deudor' => 0.0, 'acreedor' => 0.0];

        foreach ($plan as $c) {
            if ($c->nivel > $nivel) {
                continue;
            }

            $debe = round($acum[$c->codigo]['debe'] ?? 0, 2);
            $haber = round($acum[$c->codigo]['haber'] ?? 0, 2);
            $tieneMov = $debe != 0 || $haber != 0;

            if (! $tieneMov && ! $incluirSinMov) {
                continue;
            }

            $saldo = round($debe - $haber, 2);
            // Fila "hoja" del nivel elegido: suma en los totales sin duplicar a sus padres
            $hoja = $c->nivel === $nivel || $c->imputable;

            $fila = (object) [
                'cuenta'   => $c,
                'debe'     => $debe,
                'haber'    => $haber,
                'deudor'   => $saldo > 0 ? $saldo : 0.0,
                'acreedor' => $saldo < 0 ? -$saldo : 0.0,
                'hoja'     => $hoja,
            ];
            $filas->push($fila);

            if ($hoja) {
                $totales['debe'] += $debe;
                $totales['haber'] += $haber;
                $totales['deudor'] += $fila->deudor;
                $totales['acreedor'] += $fila->acreedor;
            }
        }

        return [
            'filas'    => $filas,
            'totales'  => array_map(fn ($v) => round($v, 2), $totales),
            'nivel'    => $nivel,
            'maxNivel' => $maxNivel,
        ];
    }

    /* ---------------------------------------------------------------- */

    /** Líneas de asientos registrados del ejercicio en el período. */
    private function lineasBase(Ejercicio $ejercicio, Carbon $desde, Carbon $hasta)
    {
        return DB::table('asiento_lineas AS l')
            ->join('asientos AS a', 'a.id', '=', 'l.asiento_id')
            ->where('a.empresa_id', $ejercicio->empresa_id)
            ->where('a.ejercicio_id', $ejercicio->id)
            ->where('a.estado', 'registrado')
            ->whereBetween('a.fecha', [$desde->toDateString(), $hasta->toDateString()]);
    }
}
