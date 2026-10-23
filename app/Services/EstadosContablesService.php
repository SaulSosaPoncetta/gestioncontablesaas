<?php

namespace App\Services;

use App\Models\Cuenta;
use App\Models\Ejercicio;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Estado de Situación Patrimonial y Estado de Resultados (formato RT 8/9 FACPCE)
 * a partir del plan de cuentas:
 *   1 Activo · 2 Pasivo · 3 Patrimonio Neto · 4 Resultados positivos · 5 Resultados negativos
 *
 * No computa asientos de refundición ni de cierre: el resultado del ejercicio
 * se calcula con las cuentas 4 y 5 y se expone dentro del Patrimonio Neto.
 */
class EstadosContablesService
{
    /** Secciones del Estado de Resultados según el código de nivel 2 del plan. */
    public const SECCIONES_ER = [
        ['clave' => 'ventas',           'titulo' => 'Ventas netas de bienes y servicios',   'prefijos' => ['4.1']],
        ['clave' => 'costo',            'titulo' => 'Costo de los bienes vendidos y servicios prestados', 'prefijos' => ['5.1']],
        ['subtotal' => 'Resultado bruto'],
        ['clave' => 'comercializacion', 'titulo' => 'Gastos de comercialización',           'prefijos' => ['5.2']],
        ['clave' => 'administracion',   'titulo' => 'Gastos de administración',             'prefijos' => ['5.3']],
        ['clave' => 'financieros',      'titulo' => 'Resultados financieros y por tenencia', 'prefijos' => ['4.2', '5.4']],
        ['clave' => 'otros',            'titulo' => 'Otros ingresos y egresos',             'prefijos' => []], // lo no clasificado
        ['subtotal' => 'Resultado antes del impuesto a las ganancias'],
        ['clave' => 'impuesto',         'titulo' => 'Impuesto a las ganancias',             'prefijos' => ['5.5']],
        ['resultado' => 'Resultado del ejercicio'],
    ];

    private ?Collection $plan = null;

    /* ----------------------------------------------------------------
     | Estado de Situación Patrimonial
     * ---------------------------------------------------------------- */

    public function situacionPatrimonial(Ejercicio $ejercicio, Carbon $fecha, ?Ejercicio $anterior, int $nivel = 3): array
    {
        $act = $this->saldos($ejercicio, $ejercicio->fecha_inicio, $fecha);
        $ant = $anterior ? $this->saldos($anterior, $anterior->fecha_inicio, $anterior->fecha_cierre) : null;

        $resultado = fn (?array $s) => $s === null ? null : round(-(($s['4'] ?? 0) + ($s['5'] ?? 0)), 2);

        $secciones = [];
        foreach (['1' => 1, '2' => -1, '3' => -1] as $raiz => $factor) {
            $cuentaRaiz = $this->plan()->firstWhere('codigo', $raiz);
            $secciones[$raiz] = [
                'titulo'   => mb_strtoupper($cuentaRaiz?->nombre ?? ['1' => 'Activo', '2' => 'Pasivo', '3' => 'Patrimonio Neto'][$raiz]),
                'filas'    => $cuentaRaiz ? $this->aplanarHijos($cuentaRaiz, $factor, $act, $ant, $nivel) : [],
                'total'    => round($factor * ($act[$raiz] ?? 0), 2),
                'anterior' => $ant !== null ? round($factor * ($ant[$raiz] ?? 0), 2) : null,
            ];
        }

        // El resultado del ejercicio se suma al Patrimonio Neto
        $resAct = $resultado($act);
        $resAnt = $resultado($ant);
        $secciones['3']['filas'][] = $this->fila('linea', null, 'Resultado del ejercicio', 1, $resAct, $resAnt);
        $secciones['3']['total'] = round($secciones['3']['total'] + $resAct, 2);
        if ($ant !== null) {
            $secciones['3']['anterior'] = round($secciones['3']['anterior'] + $resAnt, 2);
        }

        $pasivoMasPn = round($secciones['2']['total'] + $secciones['3']['total'], 2);
        $pasivoMasPnAnt = $ant !== null ? round($secciones['2']['anterior'] + $secciones['3']['anterior'], 2) : null;

        return [
            'secciones'      => $secciones,
            'pasivoMasPn'    => $pasivoMasPn,
            'pasivoMasPnAnt' => $pasivoMasPnAnt,
            'diferencia'     => round($secciones['1']['total'] - $pasivoMasPn, 2),
            'resultado'      => $resAct,
        ];
    }

    /* ----------------------------------------------------------------
     | Estado de Resultados
     * ---------------------------------------------------------------- */

    public function resultados(Ejercicio $ejercicio, Carbon $desde, Carbon $hasta, ?Ejercicio $anterior, int $nivel = 3): array
    {
        $act = $this->saldos($ejercicio, $desde, $hasta);
        $ant = $anterior ? $this->saldos($anterior, $anterior->fecha_inicio, $anterior->fecha_cierre) : null;

        // Cuentas de nivel 2 de los rubros 4 y 5, asignadas a su sección
        $nivel2 = $this->plan()->filter(fn ($c) => $c->nivel === 2 && in_array($c->codigo[0], ['4', '5'], true));
        $asignadas = [];
        foreach ($nivel2 as $c) {
            $clave = 'otros';
            foreach (self::SECCIONES_ER as $s) {
                if (! empty($s['prefijos']) && in_array($c->codigo, $s['prefijos'], true)) {
                    $clave = $s['clave'];
                    break;
                }
            }
            $asignadas[$clave][] = $c;
        }

        $filas = [];
        $acum = 0.0;
        $acumAnt = $ant !== null ? 0.0 : null;

        foreach (self::SECCIONES_ER as $s) {
            if (isset($s['subtotal']) || isset($s['resultado'])) {
                $tipo = isset($s['subtotal']) ? 'subtotal' : 'resultado';
                $filas[] = $this->fila($tipo, null, $s['subtotal'] ?? $s['resultado'], 0, round($acum, 2), $acumAnt !== null ? round($acumAnt, 2) : null);
                continue;
            }

            $cuentas = $asignadas[$s['clave']] ?? [];
            $totalSec = 0.0;
            $totalSecAnt = $ant !== null ? 0.0 : null;
            $lineas = [];

            foreach ($cuentas as $c) {
                // Importe con signo de resultado: ingresos (+), egresos (−)
                $totalSec += -($act[$c->codigo] ?? 0);
                if ($ant !== null) {
                    $totalSecAnt += -($ant[$c->codigo] ?? 0);
                }

                if ($nivel <= 2 || $this->hijos($c)->isEmpty()) {
                    $imp = round(-($act[$c->codigo] ?? 0), 2);
                    $impAnt = $ant !== null ? round(-($ant[$c->codigo] ?? 0), 2) : null;
                    if ($imp != 0 || $impAnt) {
                        $lineas[] = $this->fila('linea', $c, $c->nombre, 1, $imp, $impAnt);
                    }
                } else {
                    array_push($lineas, ...$this->aplanarHijos($c, -1, $act, $ant, $nivel, 1));
                }
            }

            $totalSec = round($totalSec, 2);
            $totalSecAnt = $totalSecAnt !== null ? round($totalSecAnt, 2) : null;

            if ($totalSec == 0 && ! $totalSecAnt && empty($lineas)) {
                continue; // sección sin movimientos
            }

            $filas[] = $this->fila('titulo', null, $s['titulo'], 0, null, null);
            array_push($filas, ...$lineas);
            $filas[] = $this->fila('total', null, 'Total ' . mb_strtolower($s['titulo']), 0, $totalSec, $totalSecAnt);

            $acum += $totalSec;
            if ($acumAnt !== null) {
                $acumAnt += $totalSecAnt;
            }
        }

        return [
            'filas'     => $filas,
            'resultado' => round($acum, 2),
            'resultadoAnt' => $acumAnt !== null ? round($acumAnt, 2) : null,
        ];
    }

    /* ----------------------------------------------------------------
     | Auxiliares
     * ---------------------------------------------------------------- */

    /** Ejercicio inmediato anterior de la misma empresa (para el comparativo). */
    public function ejercicioAnterior(Ejercicio $ejercicio): ?Ejercicio
    {
        return Ejercicio::where('fecha_cierre', '<', $ejercicio->fecha_inicio)->orderByDesc('fecha_cierre')->first();
    }

    /**
     * Saldos (debe − haber) por código, acumulados en cada cuenta y en todos sus ancestros.
     * Excluye refundición y cierre.
     */
    private function saldos(Ejercicio $ejercicio, Carbon $desde, Carbon $hasta): array
    {
        $porCuenta = DB::table('asiento_lineas AS l')
            ->join('asientos AS a', 'a.id', '=', 'l.asiento_id')
            ->where('a.empresa_id', $ejercicio->empresa_id)
            ->where('a.ejercicio_id', $ejercicio->id)
            ->where('a.estado', 'registrado')
            ->whereNotIn('a.tipo', ['refundicion', 'cierre'])
            ->whereBetween('a.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->groupBy('l.cuenta_id')
            ->selectRaw('l.cuenta_id, SUM(l.debe) - SUM(l.haber) AS saldo')
            ->pluck('saldo', 'cuenta_id');

        $codigos = $this->plan()->pluck('codigo', 'id');
        $acum = [];

        foreach ($porCuenta as $cuentaId => $saldo) {
            $codigo = $codigos[$cuentaId] ?? null;
            if (! $codigo) {
                continue;
            }
            $partes = explode('.', $codigo);
            for ($i = 1; $i <= count($partes); $i++) {
                $c = implode('.', array_slice($partes, 0, $i));
                $acum[$c] = ($acum[$c] ?? 0) + (float) $saldo;
            }
        }

        return $acum;
    }

    /**
     * Convierte las subcuentas de $padre en filas: los grupos con hijos visibles
     * llevan título, detalle y "Total …"; el resto es una línea con importe.
     */
    private function aplanarHijos(Cuenta $padre, int $factor, array $act, ?array $ant, int $nivelMax, int $sangria = 1): array
    {
        $filas = [];

        foreach ($this->hijos($padre) as $c) {
            $imp = round($factor * ($act[$c->codigo] ?? 0), 2);
            $impAnt = $ant !== null ? round($factor * ($ant[$c->codigo] ?? 0), 2) : null;

            if ($imp == 0 && ! $impAnt) {
                continue;
            }

            $hijosVisibles = $c->nivel < $nivelMax
                ? $this->hijos($c)->filter(fn ($h) => ($act[$h->codigo] ?? 0) != 0 || ($ant !== null && ($ant[$h->codigo] ?? 0) != 0))
                : collect();

            if ($hijosVisibles->isEmpty()) {
                $filas[] = $this->fila('linea', $c, $c->nombre, $sangria, $imp, $impAnt);
            } else {
                $filas[] = $this->fila('titulo', $c, $c->nombre, $sangria, null, null);
                array_push($filas, ...$this->aplanarHijos($c, $factor, $act, $ant, $nivelMax, $sangria + 1));
                $filas[] = $this->fila('total', $c, 'Total ' . $c->nombre, $sangria, $imp, $impAnt);
            }
        }

        return $filas;
    }

    private function fila(string $tipo, ?Cuenta $cuenta, string $texto, int $sangria, ?float $importe, ?float $anterior): object
    {
        return (object) compact('tipo', 'cuenta', 'texto', 'sangria', 'importe', 'anterior');
    }

    private function hijos(Cuenta $padre): Collection
    {
        return $this->plan()->where('padre_id', $padre->id)->values();
    }

    private function plan(): Collection
    {
        return $this->plan ??= Cuenta::planOrdenado();
    }
}
