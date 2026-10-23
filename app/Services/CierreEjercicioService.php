<?php

namespace App\Services;

use App\Models\Asiento;
use App\Models\Cuenta;
use App\Models\CuentaConfig;
use App\Models\Ejercicio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cierre contable del ejercicio:
 *   1. Refundición: las cuentas de resultado se cancelan contra "Resultado del ejercicio".
 *   2. Cierre: se cancelan todas las cuentas patrimoniales (y de orden).
 *   3. El ejercicio queda cerrado.
 *   4. Apertura del ejercicio siguiente (se crea si no existe) con los mismos saldos;
 *      el "Resultado del ejercicio" pasa a "Resultados no asignados".
 *
 * Todo se puede deshacer con reabrir(): anula esos asientos y vuelve a abrir el ejercicio.
 */
class CierreEjercicioService
{
    public const ORIGEN = 'cierre_ejercicio';

    private const TIPOS_RESULTADO = ['resultado_positivo', 'resultado_negativo'];
    private const TIPOS_PATRIMONIALES = ['activo', 'pasivo', 'patrimonio_neto', 'orden'];

    public function __construct(private AsientoService $asientos, private AjusteInflacionService $ajuste)
    {
    }

    /**
     * Controles antes de cerrar.
     * @return array{bloqueos: string[], avisos: string[]}
     */
    public function verificar(Ejercicio $ejercicio): array
    {
        $bloqueos = [];
        $avisos = [];

        if (! $ejercicio->estaAbierto()) {
            $bloqueos[] = 'El ejercicio ya está cerrado.';
        }

        if (today()->lt($ejercicio->fecha_cierre)) {
            $bloqueos[] = 'El ejercicio todavía no terminó (cierra el ' . $ejercicio->fecha_cierre->format('d/m/Y') . ').';
        }

        $borradores = Asiento::where('ejercicio_id', $ejercicio->id)->where('estado', 'borrador')->count();
        if ($borradores) {
            $bloqueos[] = "Hay {$borradores} asiento(s) en borrador: regístrelos o elimínelos.";
        }

        if (! CuentaConfig::cuentaPara('resultado_ejercicio')) {
            $bloqueos[] = 'Falta configurar la cuenta «Resultado del ejercicio» (Configuración › Cuentas automáticas).';
        }

        $siguiente = $ejercicio->siguiente();
        if ($siguiente && ! $siguiente->estaAbierto()) {
            $bloqueos[] = "El ejercicio siguiente (N° {$siguiente->numero}) está cerrado: no se puede registrar la apertura.";
        }

        $diferencia = $this->diferenciaSumas($ejercicio);
        if (abs($diferencia) >= 0.01) {
            $bloqueos[] = 'Las sumas del Debe y el Haber no coinciden (diferencia $ ' . number_format($diferencia, 2, ',', '.') . ').';
        }

        if (! $this->ajuste->asientoExistente($ejercicio)) {
            $avisos[] = 'No se registró el ajuste por inflación del ejercicio (obligatorio para los estados contables en moneda homogénea, RT 6).';
        }

        if (Asiento::where('ejercicio_id', $ejercicio->id)->where('estado', 'registrado')
            ->whereIn('tipo', ['refundicion', 'cierre'])->where('origen', '!=', self::ORIGEN)->exists()) {
            $avisos[] = 'Hay asientos de refundición o cierre cargados a mano: revíselos para no duplicar.';
        }

        if (! CuentaConfig::cuentaPara('resultados_no_asignados')) {
            $avisos[] = 'Sin cuenta de «Resultados no asignados»: el resultado se abrirá en la misma cuenta «Resultado del ejercicio».';
        }

        return compact('bloqueos', 'avisos');
    }

    /** Datos para la pantalla previa al cierre. */
    public function resumen(Ejercicio $ejercicio): array
    {
        $resultados = $this->saldos($ejercicio, self::TIPOS_RESULTADO);
        $patrimoniales = $this->saldos($ejercicio, self::TIPOS_PATRIMONIALES);

        return [
            // Resultado: saldo acreedor neto de las cuentas de resultado (ganancia positiva)
            'resultado'           => round(-$resultados->sum(), 2),
            'cuentas_resultado'   => $resultados->count(),
            'cuentas_patrimonio'  => $patrimoniales->count(),
            'siguiente'           => $ejercicio->siguiente(),
        ];
    }

    /** Cierra el ejercicio. Devuelve [refundición, cierre, apertura, ejercicio siguiente]. */
    public function cerrar(Ejercicio $ejercicio): array
    {
        $verificacion = $this->verificar($ejercicio);
        if ($verificacion['bloqueos']) {
            throw new RuntimeException(implode(' ', $verificacion['bloqueos']));
        }

        return DB::transaction(function () use ($ejercicio) {
            $cuentaResultado = CuentaConfig::cuentaPara('resultado_ejercicio');
            $cuentaRna = CuentaConfig::cuentaPara('resultados_no_asignados') ?? $cuentaResultado;
            $fechaCierre = $ejercicio->fecha_cierre->toDateString();

            // 1) Refundición de resultados
            $refundicion = null;
            $resultados = $this->saldos($ejercicio, self::TIPOS_RESULTADO);
            if ($resultados->isNotEmpty()) {
                $lineas = $this->lineasCancelacion($resultados, 'Refundición');
                $neto = round($resultados->sum(), 2);   // debe - haber de los resultados
                $lineas[] = $this->linea($cuentaResultado->id, $neto, $neto > 0 ? 'Pérdida del ejercicio' : 'Ganancia del ejercicio');

                $refundicion = $this->registrar($ejercicio, $fechaCierre, 'refundicion',
                    "Refundición de cuentas de resultado – ejercicio N° {$ejercicio->numero}", $lineas);
            }

            // 2) Cierre de cuentas patrimoniales (ya incluye el resultado refundido)
            $patrimoniales = $this->saldos($ejercicio, self::TIPOS_PATRIMONIALES);
            $this->comprobarBalanceo($patrimoniales);
            $cierre = $patrimoniales->isNotEmpty()
                ? $this->registrar($ejercicio, $fechaCierre, 'cierre',
                    "Asiento de cierre – ejercicio N° {$ejercicio->numero}", $this->lineasCancelacion($patrimoniales, 'Cierre'))
                : null;

            // 3) Ejercicio cerrado
            $ejercicio->update(['estado' => 'cerrado', 'cerrado_at' => now(), 'cerrado_por' => auth()->id()]);

            // 4) Apertura del siguiente
            $siguiente = $ejercicio->siguiente() ?? $this->crearSiguiente($ejercicio);

            Asiento::where('ejercicio_id', $siguiente->id)->where('origen', self::ORIGEN)->where('estado', 'registrado')
                ->get()->each(fn ($a) => $this->asientos->anular($a, 'Reemplazado por un nuevo cierre del ejercicio anterior.'));

            $apertura = null;
            if ($patrimoniales->isNotEmpty()) {
                // Mismos saldos, con el resultado del ejercicio reclasificado a resultados no asignados
                $saldosApertura = collect();
                foreach ($patrimoniales as $cuentaId => $saldo) {
                    $id = (int) $cuentaId === (int) $cuentaResultado->id ? (int) $cuentaRna->id : (int) $cuentaId;
                    $saldosApertura[$id] = round(($saldosApertura[$id] ?? 0) + $saldo, 2);
                }
                $saldosApertura = $saldosApertura->filter(fn ($s) => abs($s) >= 0.01);

                $lineas = [];
                foreach ($this->ordenar($saldosApertura) as $cuentaId => $saldo) {
                    $lineas[] = $this->linea($cuentaId, $saldo, 'Saldo de apertura');
                }

                $apertura = $this->registrar($siguiente, $siguiente->fecha_inicio->toDateString(), 'apertura',
                    "Asiento de apertura – saldos al cierre del ejercicio N° {$ejercicio->numero}", $lineas);
            }

            return [$refundicion, $cierre, $apertura, $siguiente];
        });
    }

    /** Deshace el cierre: anula refundición, cierre y la apertura del siguiente, y reabre el ejercicio. */
    public function reabrir(Ejercicio $ejercicio): void
    {
        $siguiente = $ejercicio->siguiente();

        if ($siguiente && ! $siguiente->estaAbierto()) {
            throw new RuntimeException("Primero hay que reabrir el ejercicio siguiente (N° {$siguiente->numero}).");
        }

        DB::transaction(function () use ($ejercicio, $siguiente) {
            $motivo = "Reapertura del ejercicio N° {$ejercicio->numero}.";

            Asiento::where('ejercicio_id', $ejercicio->id)->where('origen', self::ORIGEN)->where('estado', 'registrado')
                ->get()->each(fn ($a) => $this->asientos->anular($a, $motivo));

            if ($siguiente) {
                Asiento::where('ejercicio_id', $siguiente->id)->where('origen', self::ORIGEN)
                    ->where('tipo', 'apertura')->where('estado', 'registrado')
                    ->get()->each(fn ($a) => $this->asientos->anular($a, $motivo));
            }

            $ejercicio->update(['estado' => 'abierto', 'cerrado_at' => null, 'cerrado_por' => null]);
        });
    }

    /* ----------------------------------------------------------------
     | Auxiliares
     * ---------------------------------------------------------------- */

    /**
     * Saldos (debe - haber) por cuenta de los tipos indicados, con todos los asientos
     * registrados del ejercicio salvo los de cierre. Solo cuentas con saldo.
     */
    private function saldos(Ejercicio $ejercicio, array $tipos): Collection
    {
        return DB::table('asiento_lineas AS l')
            ->join('asientos AS a', 'a.id', '=', 'l.asiento_id')
            ->join('cuentas AS c', 'c.id', '=', 'l.cuenta_id')
            ->where('a.empresa_id', $ejercicio->empresa_id)
            ->where('a.ejercicio_id', $ejercicio->id)
            ->where('a.estado', 'registrado')
            ->where('a.tipo', '!=', 'cierre')
            ->whereIn('c.tipo', $tipos)
            ->groupBy('l.cuenta_id')
            ->selectRaw('l.cuenta_id, SUM(l.debe) - SUM(l.haber) AS saldo')
            ->pluck('saldo', 'cuenta_id')
            ->map(fn ($s) => round((float) $s, 2))
            ->filter(fn ($s) => abs($s) >= 0.01);
    }

    private function diferenciaSumas(Ejercicio $ejercicio): float
    {
        $r = DB::table('asiento_lineas AS l')
            ->join('asientos AS a', 'a.id', '=', 'l.asiento_id')
            ->where('a.ejercicio_id', $ejercicio->id)
            ->where('a.estado', 'registrado')
            ->selectRaw('COALESCE(SUM(l.debe), 0) - COALESCE(SUM(l.haber), 0) AS dif')
            ->first();

        return round((float) $r->dif, 2);
    }

    /** Líneas que dejan cada saldo en cero (saldo deudor → haber, acreedor → debe). */
    private function lineasCancelacion(Collection $saldos, string $detalle): array
    {
        $lineas = [];
        foreach ($this->ordenar($saldos) as $cuentaId => $saldo) {
            $lineas[] = $this->linea($cuentaId, -$saldo, $detalle);
        }

        return $lineas;
    }

    /** Línea por importe con signo: positivo = Debe, negativo = Haber. */
    private function linea(int $cuentaId, float $importe, string $detalle): array
    {
        return [
            'cuenta_id' => $cuentaId,
            'debe'      => $importe > 0 ? round($importe, 2) : 0,
            'haber'     => $importe < 0 ? round(-$importe, 2) : 0,
            'detalle'   => $detalle,
        ];
    }

    /** Ordena por código de cuenta (1.1.9 antes que 1.1.10). */
    private function ordenar(Collection $saldos): Collection
    {
        $codigos = Cuenta::whereIn('id', $saldos->keys())->pluck('codigo', 'id');

        return $saldos->sortBy(fn ($s, $id) => $codigos[$id] ?? '', SORT_NATURAL);
    }

    private function comprobarBalanceo(Collection $saldos): void
    {
        if (abs(round($saldos->sum(), 2)) >= 0.01) {
            throw new RuntimeException('Los saldos patrimoniales no balancean (diferencia $ '
                . number_format($saldos->sum(), 2, ',', '.') . '). Revise el balance de sumas y saldos.');
        }
    }

    private function registrar(Ejercicio $ejercicio, string $fecha, string $tipo, string $concepto, array $lineas): Asiento
    {
        return $this->asientos->guardar([
            'fecha'    => $fecha,
            'tipo'     => $tipo,
            'origen'   => self::ORIGEN,
            'accion'   => 'registrar',
            'concepto' => $concepto,
            'lineas'   => $lineas,
        ], $ejercicio);
    }

    private function crearSiguiente(Ejercicio $ejercicio): Ejercicio
    {
        $inicio = $ejercicio->fecha_cierre->copy()->addDay();
        $cierre = $inicio->copy()->addYearNoOverflow()->subDay();

        return Ejercicio::create([
            'empresa_id'   => $ejercicio->empresa_id,
            'numero'       => (int) Ejercicio::lockForUpdate()->max('numero') + 1,
            'descripcion'  => 'Ejercicio ' . $cierre->year,
            'fecha_inicio' => $inicio,
            'fecha_cierre' => $cierre,
            'estado'       => 'abierto',
        ]);
    }
}
