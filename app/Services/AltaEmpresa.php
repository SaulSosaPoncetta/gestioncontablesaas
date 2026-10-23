<?php

namespace App\Services;

use App\Models\Ejercicio;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alta completa de un cliente SaaS: empresa + usuario administrador
 * + período de prueba + plan de cuentas modelo + ejercicio vigente.
 */
class AltaEmpresa
{
    public static function crear(array $datosEmpresa, Plan $plan, User $administrador, bool $cargarPlanCuentas = true): Empresa
    {
        return DB::transaction(function () use ($datosEmpresa, $plan, $administrador, $cargarPlanCuentas) {
            $empresa = Empresa::create($datosEmpresa + [
                'plan_id'            => $plan->id,
                'estado_suscripcion' => 'prueba',
                'prueba_hasta'       => today()->addDays((int) config('saas.dias_prueba')),
                'activa'             => true,
            ]);

            $empresa->usuarios()->attach($administrador->id, ['rol' => 'administrador']);

            if ($cargarPlanCuentas) {
                PlanCuentasModelo::aplicar($empresa);
            }

            [$inicio, $cierre] = self::fechasEjercicioVigente((int) $empresa->mes_cierre);

            Ejercicio::withoutGlobalScope('empresa')->create([
                'empresa_id'   => $empresa->id,
                'numero'       => 1,
                'descripcion'  => 'Ejercicio ' . $cierre->year,
                'fecha_inicio' => $inicio,
                'fecha_cierre' => $cierre,
                'estado'       => 'abierto',
            ]);

            return $empresa;
        });
    }

    /** [inicio, cierre] del ejercicio que contiene la fecha de hoy según el mes de cierre. */
    public static function fechasEjercicioVigente(int $mesCierre): array
    {
        $hoy = today();
        $cierre = Carbon::create($hoy->year, $mesCierre, 1)->endOfMonth()->startOfDay();

        if ($cierre->lt($hoy)) {
            $cierre = Carbon::create($hoy->year + 1, $mesCierre, 1)->endOfMonth()->startOfDay();
        }

        $inicio = $cierre->copy()->subYearNoOverflow()->endOfMonth()->addDay()->startOfDay();

        return [$inicio, $cierre];
    }
}
