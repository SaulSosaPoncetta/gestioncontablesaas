<?php

namespace App\Services;

use App\Models\Asiento;
use App\Models\Cuenta;
use App\Models\CuentaConfig;
use App\Models\Ejercicio;
use App\Models\IndiceInflacion;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Ajuste por inflación contable (RT 6 FACPCE, método de reexpresión de partidas no monetarias).
 *
 *  - Se reexpresan las cuentas marcadas como "ajustables" (no monetarias: bienes de cambio, bienes de uso,
 *    patrimonio neto y resultados).
 *  - Cada movimiento se lleva a moneda de cierre con el coeficiente índice(mes de cierre) / índice(mes del movimiento).
 *  - El saldo de apertura se reexpresa desde el mes anterior al inicio del ejercicio (ya estaba en moneda de ese cierre).
 *  - El capital social se mantiene nominal: su reexpresión va a "Ajuste de capital".
 *  - La contrapartida de todos los ajustes es el RECPAM.
 *
 * El asiento se genera al cierre del ejercicio (tipo "ajuste", origen "ajuste_inflacion").
 * Si se vuelve a generar, el anterior se anula.
 */
class AjusteInflacionService
{
    public const ORIGEN = 'ajuste_inflacion';

    public function __construct(private AsientoService $asientos)
    {
    }

    /**
     * @return array{
     *   faltantes: string[], filas: array, recpam: ?Cuenta, total_recpam: float,
     *   coeficiente: ?float, mes_base: string, mes_cierre: string, existente: ?Asiento, errores: string[]
     * }
     */
    public function calcular(Ejercicio $ejercicio): array
    {
        $mesBase = $ejercicio->fecha_inicio->copy()->subMonthNoOverflow()->startOfMonth();
        $mesCierre = $ejercicio->fecha_cierre->copy()->startOfMonth();

        $indices = IndiceInflacion::mapa($mesBase, $mesCierre);
        $faltantes = array_values(array_diff(IndiceInflacion::meses($mesBase, $mesCierre), array_keys($indices)));

        $recpam = CuentaConfig::cuentaPara('recpam');
        $capital = CuentaConfig::cuentaPara('capital_social');
        $ajusteCapital = CuentaConfig::cuentaPara('ajuste_capital');

        $resultado = [
            'faltantes'    => $faltantes,
            'filas'        => [],
            'recpam'       => $recpam,
            'total_recpam' => 0.0,
            'coeficiente'  => null,
            'mes_base'     => $mesBase->format('Y-m'),
            'mes_cierre'   => $mesCierre->format('Y-m'),
            'existente'    => $this->asientoExistente($ejercicio),
            'errores'      => [],
        ];

        if (! $recpam) {
            $resultado['errores'][] = 'Falta configurar la cuenta de RECPAM (Configuración › Cuentas automáticas).';
        }

        if ($faltantes) {
            return $resultado;
        }

        $indiceCierre = $indices[$mesCierre->format('Y-m')];
        $indiceBase = $indices[$mesBase->format('Y-m')];
        $resultado['coeficiente'] = $indiceCierre / $indiceBase;

        $cuentas = Cuenta::where('imputable', true)
            ->where('ajustable', true)
            ->where('tipo', '!=', 'orden')
            ->when($recpam, fn ($q) => $q->where('id', '!=', $recpam->id))
            ->get()
            ->keyBy('id');

        if ($cuentas->isEmpty()) {
            return $resultado;
        }

        // Movimientos netos (debe - haber) por cuenta y mes; la apertura aparte
        $movimientos = DB::table('asiento_lineas AS l')
            ->join('asientos AS a', 'a.id', '=', 'l.asiento_id')
            ->where('a.empresa_id', $ejercicio->empresa_id)
            ->where('a.ejercicio_id', $ejercicio->id)
            ->where('a.estado', 'registrado')
            ->whereNotIn('a.tipo', ['refundicion', 'cierre'])
            ->where('a.origen', '!=', self::ORIGEN)
            ->whereIn('l.cuenta_id', $cuentas->keys())
            ->selectRaw("l.cuenta_id, (a.tipo = 'apertura') AS es_apertura, DATE_FORMAT(a.fecha, '%Y-%m') AS mes, SUM(l.debe) - SUM(l.haber) AS neto")
            ->groupByRaw("l.cuenta_id, (a.tipo = 'apertura'), DATE_FORMAT(a.fecha, '%Y-%m')")
            ->get();

        $porCuenta = [];
        foreach ($movimientos as $m) {
            $indiceOrigen = $m->es_apertura ? $indiceBase : ($indices[$m->mes] ?? null);
            if (! $indiceOrigen) {
                continue; // movimiento fuera del rango del ejercicio (no debería ocurrir)
            }
            $id = (int) $m->cuenta_id;
            $porCuenta[$id]['historico'] = ($porCuenta[$id]['historico'] ?? 0) + (float) $m->neto;
            $porCuenta[$id]['ajustado'] = ($porCuenta[$id]['ajustado'] ?? 0) + (float) $m->neto * $indiceCierre / $indiceOrigen;
        }

        $total = 0.0;
        foreach ($porCuenta as $id => $v) {
            $historico = round($v['historico'], 2);
            $ajustado = round($v['ajustado'], 2);
            $ajuste = round($ajustado - $historico, 2);

            if (abs($ajuste) < 0.01) {
                continue;
            }

            $cuenta = $cuentas[$id];
            // El capital queda nominal: su reexpresión se imputa a "Ajuste de capital"
            $destino = ($capital && $ajusteCapital && $cuenta->id === $capital->id) ? $ajusteCapital : $cuenta;

            $resultado['filas'][] = (object) [
                'cuenta'    => $cuenta,
                'destino'   => $destino,
                'historico' => $historico,
                'ajustado'  => $ajustado,
                'ajuste'    => $ajuste,
            ];
            $total += $ajuste;
        }

        usort($resultado['filas'], fn ($a, $b) => strnatcmp($a->cuenta->codigo, $b->cuenta->codigo));

        // RECPAM: contrapartida (en términos de debe - haber, el opuesto de la suma de ajustes)
        $resultado['total_recpam'] = round(-$total, 2);

        return $resultado;
    }

    /** Genera (o regenera) el asiento de ajuste al cierre del ejercicio. */
    public function generar(Ejercicio $ejercicio): Asiento
    {
        if (! $ejercicio->admiteFecha($ejercicio->fecha_cierre)) {
            throw new RuntimeException('El ejercicio está cerrado o bloqueado a la fecha de cierre: no se puede registrar el ajuste.');
        }

        $calculo = $this->calcular($ejercicio);

        if ($calculo['errores']) {
            throw new RuntimeException(implode(' ', $calculo['errores']));
        }
        if ($calculo['faltantes']) {
            throw new RuntimeException('Faltan índices de: ' . implode(', ', $calculo['faltantes']) . '.');
        }
        if (! $calculo['filas']) {
            throw new RuntimeException('No hay importes para ajustar.');
        }

        // Agrupa por cuenta de destino (capital + ajuste de capital pueden ir a la misma)
        $netos = [];
        foreach ($calculo['filas'] as $f) {
            $netos[$f->destino->id] = ($netos[$f->destino->id] ?? 0) + $f->ajuste;
        }
        $netos[$calculo['recpam']->id] = ($netos[$calculo['recpam']->id] ?? 0) + $calculo['total_recpam'];

        $lineas = [];
        foreach ($netos as $cuentaId => $importe) {
            $importe = round($importe, 2);
            if (abs($importe) < 0.01) {
                continue;
            }
            $lineas[] = [
                'cuenta_id' => $cuentaId,
                'debe'      => $importe > 0 ? $importe : 0,
                'haber'     => $importe < 0 ? -$importe : 0,
                'detalle'   => (int) $cuentaId === (int) $calculo['recpam']->id ? 'RECPAM del ejercicio' : 'Reexpresión a moneda de cierre',
            ];
        }

        return DB::transaction(function () use ($ejercicio, $calculo, $lineas) {
            if ($calculo['existente']) {
                $this->asientos->anular($calculo['existente'], 'Reemplazado por un nuevo cálculo del ajuste por inflación.');
            }

            return $this->asientos->guardar([
                'fecha'    => $ejercicio->fecha_cierre->toDateString(),
                'tipo'     => 'ajuste',
                'origen'   => self::ORIGEN,
                'accion'   => 'registrar',
                'concepto' => "Ajuste por inflación (RT 6) – coeficiente {$calculo['mes_base']} a {$calculo['mes_cierre']}: "
                    . number_format($calculo['coeficiente'], 4, ',', '.'),
                'lineas'   => $lineas,
            ], $ejercicio);
        });
    }

    public function asientoExistente(Ejercicio $ejercicio): ?Asiento
    {
        return Asiento::where('ejercicio_id', $ejercicio->id)
            ->where('origen', self::ORIGEN)
            ->where('estado', 'registrado')
            ->latest('id')
            ->first();
    }
}
