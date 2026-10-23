<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controla el acceso según el estado de la suscripción de la empresa.
 * - bloqueada:    solo puede ver la pantalla "Mi suscripción".
 * - solo_lectura: puede consultar todo pero no grabar (POST/PUT/DELETE).
 * El superadmin nunca queda restringido.
 */
class VerificarSuscripcion
{
    public function handle(Request $request, Closure $next): Response
    {
        $empresa = app()->bound('empresa_activa') ? app('empresa_activa') : null;

        if (! $empresa) {
            return $next($request);
        }

        $estado = $empresa->estadoAcceso();
        View::share('estadoAcceso', $estado);

        if ($request->user()->es_superadmin || $request->routeIs('suscripcion.*')) {
            return $next($request);
        }

        if ($estado === 'bloqueada') {
            return redirect()->route('suscripcion.show');
        }

        if ($estado === 'solo_lectura' && ! $request->isMethodSafe()) {
            return back()->with('error', 'Su suscripción está vencida: la cuenta está en modo solo lectura. Renueve el abono para seguir cargando datos.');
        }

        return $next($request);
    }
}
