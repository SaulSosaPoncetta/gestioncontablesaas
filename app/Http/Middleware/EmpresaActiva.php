<?php

namespace App\Http\Middleware;

use App\Models\Ejercicio;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resuelve la empresa (cliente SaaS) y el ejercicio sobre los que trabaja el usuario.
 * - Usuario cliente: siempre su propia empresa.
 * - Superadmin: la empresa en la que "ingresó" desde el panel (o ninguna).
 * Registra 'empresa_activa' y 'ejercicio_activo' en el contenedor y los comparte con las vistas.
 */
class EmpresaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->activo) {
            return $this->expulsar($request, 'Su usuario está inactivo.');
        }

        if ($user->es_superadmin) {
            $empresa = $user->empresaActual;
        } else {
            $empresa = $user->empresas()->with('plan')->first();

            if (! $empresa) {
                return $this->expulsar($request, 'Su usuario no está asociado a ninguna empresa.');
            }
        }

        View::share('empresaActiva', $empresa);
        View::share('ejercicioActivo', null);

        if (! $empresa) {
            // Superadmin sin empresa seleccionada: solo puede usar el panel de administración
            if ($request->routeIs('admin.*')) {
                return $next($request);
            }
            return redirect()->route('admin.suscriptores.index');
        }

        $empresa->loadMissing('plan');
        app()->instance('empresa_activa', $empresa);

        // Ejercicio activo: el elegido en sesión o el último abierto (el scope evita ejercicios de otra empresa)
        $ejercicio = null;
        if ($id = session('ejercicio_id')) {
            $ejercicio = Ejercicio::find($id);
        }
        if (! $ejercicio) {
            $ejercicio = Ejercicio::where('estado', 'abierto')->orderByDesc('fecha_inicio')->first()
                ?? Ejercicio::orderByDesc('fecha_inicio')->first();
            session(['ejercicio_id' => $ejercicio?->id]);
        }

        if ($ejercicio) {
            app()->instance('ejercicio_activo', $ejercicio);
            View::share('ejercicioActivo', $ejercicio);
        }

        return $next($request);
    }

    private function expulsar(Request $request, string $mensaje): Response
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $mensaje]);
    }
}
