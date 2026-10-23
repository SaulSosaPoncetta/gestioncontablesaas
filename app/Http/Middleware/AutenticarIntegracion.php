<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use App\Models\Integracion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API de integración: autentica por token (Authorization: Bearer mgc_...),
 * fija la empresa activa y verifica suscripción y plan.
 */
class AutenticarIntegracion
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        $integracion = $token !== '' ? Integracion::porToken($token) : null;

        if (! $integracion || ! $integracion->activa) {
            return response()->json(['ok' => false, 'error' => 'Token inválido o integración desactivada.'], 401);
        }

        $empresa = Empresa::with('plan')->find($integracion->empresa_id);

        if (! $empresa || in_array($empresa->estadoAcceso(), ['bloqueada', 'solo_lectura'], true)) {
            return response()->json(['ok' => false, 'error' => 'La suscripción de la empresa en el sistema contable no está activa.'], 403);
        }

        if (! $empresa->tieneModulo('integraciones')) {
            return response()->json(['ok' => false, 'error' => 'El plan contratado no incluye integraciones.'], 403);
        }

        app()->instance('empresa_activa', $empresa);
        app()->instance('integracion', $integracion);

        $integracion->forceFill(['ultimo_uso_at' => now()])->saveQuietly();

        return $next($request);
    }
}
