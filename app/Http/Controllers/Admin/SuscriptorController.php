<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Pago;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Panel del superadmin: listado de empresas clientes y gestión de su suscripción.
 */
class SuscriptorController extends Controller
{
    public function index(Request $request)
    {
        $empresas = Empresa::with('plan')
            ->withCount('usuarios')
            ->when($request->q, function ($q, $buscar) {
                $cuit = preg_replace('/\D/', '', $buscar);
                $q->where(function ($w) use ($buscar, $cuit) {
                    $w->where('razon_social', 'like', "%{$buscar}%")
                      ->orWhere('nombre_fantasia', 'like', "%{$buscar}%")
                      ->orWhere('email', 'like', "%{$buscar}%");
                    if ($cuit !== '') {
                        $w->orWhere('cuit', 'like', "%{$cuit}%");
                    }
                });
            })
            ->when($request->estado, fn ($q, $estado) => $q->where('estado_suscripcion', $estado))
            ->when($request->plan, fn ($q, $plan) => $q->where('plan_id', $plan))
            ->orderBy('razon_social')
            ->paginate(25)
            ->withQueryString();

        $resumen = [
            'total'      => Empresa::count(),
            'prueba'     => Empresa::where('estado_suscripcion', 'prueba')->count(),
            'activas'    => Empresa::where('estado_suscripcion', 'activa')->count(),
            'vencidas'   => Empresa::where('estado_suscripcion', 'activa')->whereDate('vence_el', '<', today())->count()
                          + Empresa::where('estado_suscripcion', 'prueba')->whereDate('prueba_hasta', '<', today())->count(),
            'ingreso_mes'=> Pago::whereBetween('fecha', [today()->startOfMonth(), today()->endOfMonth()])->sum('importe'),
        ];

        return view('admin.suscriptores.index', [
            'empresas' => $empresas,
            'resumen'  => $resumen,
            'planes'   => Plan::orderBy('orden')->get(),
        ]);
    }

    public function edit(Empresa $empresa)
    {
        $empresa->load(['plan', 'usuarios']);

        return view('admin.suscriptores.edit', [
            'empresa' => $empresa,
            'pagos'   => $empresa->pagos()->with('plan')->get(),
            'planes'  => Plan::orderBy('orden')->get(),
        ]);
    }

    public function update(Request $request, Empresa $empresa)
    {
        $datos = $request->validate([
            'plan_id'            => ['required', 'exists:planes,id'],
            'estado_suscripcion' => ['required', Rule::in(array_keys(Empresa::ESTADOS_SUSCRIPCION))],
            'prueba_hasta'       => ['nullable', 'date'],
            'vence_el'           => ['nullable', 'date'],
            'notas_admin'        => ['nullable', 'string', 'max:2000'],
        ]);

        $datos['activa'] = $request->boolean('activa');

        $empresa->update($datos);

        return redirect()->route('admin.suscriptores.edit', $empresa)
            ->with('success', 'Suscripción actualizada.');
    }

    /** Entra al sistema "como" esa empresa para dar soporte. */
    public function ingresar(Request $request, Empresa $empresa)
    {
        $request->user()->update(['empresa_actual_id' => $empresa->id]);
        session()->forget('ejercicio_id');

        return redirect()->route('dashboard')
            ->with('info', "Ingresó como soporte a {$empresa->razon_social}.");
    }

    public function salir(Request $request)
    {
        $request->user()->update(['empresa_actual_id' => null]);
        session()->forget('ejercicio_id');

        return redirect()->route('admin.suscriptores.index');
    }
}
