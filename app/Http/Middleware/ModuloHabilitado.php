<?php

namespace App\Http\Middleware;

use App\Models\Plan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso en rutas: ->middleware('modulo:iva')  |  'modulo:retenciones'  |  'modulo:integraciones'
 */
class ModuloHabilitado
{
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        $empresa = app()->bound('empresa_activa') ? app('empresa_activa') : null;

        if ($request->user()->es_superadmin || $empresa?->tieneModulo($modulo)) {
            return $next($request);
        }

        $nombre = Plan::MODULOS[$modulo] ?? $modulo;

        return redirect()->route('suscripcion.show')
            ->with('warning', "El módulo «{$nombre}» no está incluido en su plan. Puede mejorar el plan para habilitarlo.");
    }
}
