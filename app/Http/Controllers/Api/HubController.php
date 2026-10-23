<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Integracion;
use Illuminate\Http\Request;

/**
 * Órdenes de MiGestión Panel (firmadas, ver VerificarFirmaHub).
 */
class HubController extends Controller
{
    public const NOMBRE_AUTOMATICA = 'GestiónComercial (vinculada por MiGestión)';

    /**
     * POST /api/hub/integracion
     * { accion: vincular|desvincular, referencia_externa: id de la empresa, origen? }
     *
     * vincular: crea (o renueva) la integración automática de la empresa y devuelve el token en claro
     *           para que el hub se lo entregue a GestiónComercial.
     */
    public function integracion(Request $request)
    {
        $datos = $request->validate([
            'accion'             => 'required|in:vincular,desvincular',
            'referencia_externa' => 'required',
        ]);

        $empresa = Empresa::with('plan')->find($datos['referencia_externa']);

        if (! $empresa) {
            return response()->json(['ok' => false, 'error' => 'la empresa no existe en Contabilidad'], 404);
        }

        $integracion = Integracion::withoutGlobalScope('empresa')->firstOrNew([
            'empresa_id' => $empresa->id,
            'sistema'    => 'gestion_comercial',
            'nombre'     => self::NOMBRE_AUTOMATICA,
        ]);

        if ($datos['accion'] === 'desvincular') {
            if ($integracion->exists) {
                $integracion->update(['activa' => false]);
            }

            return response()->json(['ok' => true]);
        }

        if (! $empresa->tieneModulo('integraciones')) {
            return response()->json(['ok' => false, 'error' => 'el plan contratado en Contabilidad no incluye integraciones'], 422);
        }

        // Token nuevo en cada vinculación: el anterior deja de funcionar
        $token = $integracion->generarToken();
        $integracion->activa = true;
        $integracion->save();

        return response()->json([
            'ok'    => true,
            'token' => $token,
            'url'   => rtrim(config('app.url'), '/'),
        ]);
    }
}
