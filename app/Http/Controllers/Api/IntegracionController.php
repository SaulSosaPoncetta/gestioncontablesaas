<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IntegracionComprobanteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * API v1 de integración. Autenticación: Authorization: Bearer <token>.
 *
 *  POST /api/integracion/v1/ping                 → prueba de conexión
 *  POST /api/integracion/v1/comprobantes         → alta / actualización (idempotente por tipo_origen + id_externo)
 *  POST /api/integracion/v1/comprobantes/anular  → anulación
 */
class IntegracionController extends Controller
{
    public function __construct(private IntegracionComprobanteService $service)
    {
    }

    public function ping(): JsonResponse
    {
        $empresa = app('empresa_activa');

        return response()->json([
            'ok'           => true,
            'empresa'      => $empresa->razon_social,
            'cuit'         => $empresa->cuit,
            'condicion_iva'=> $empresa->condicion_iva,
            'ejercicio'    => \App\Models\Ejercicio::where('estado', 'abierto')->orderByDesc('fecha_inicio')->first()?->only(['numero', 'fecha_inicio', 'fecha_cierre']),
        ]);
    }

    public function comprobante(Request $request): JsonResponse
    {
        try {
            $r = $this->service->recibir(app('integracion'), $request->all());

            return response()->json([
                'ok'             => true,
                'accion'         => $r['accion'],
                'comprobante_id' => $r['comprobante']->id,
                'comprobante'    => $r['comprobante']->descripcion_corta,
                'asiento_numero' => $r['comprobante']->asiento?->numero,
            ]);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'error' => collect($e->errors())->flatten()->implode(' ')], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'error' => 'Error interno del sistema contable: ' . $e->getMessage()], 500);
        }
    }

    public function anular(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo_origen' => ['required', 'string', 'max:30'],
            'id_externo'  => ['required', 'max:50'],
            'motivo'      => ['nullable', 'string', 'max:150'],
        ]);

        try {
            $c = $this->service->anular(app('integracion'), $datos['tipo_origen'], (string) $datos['id_externo'], $datos['motivo'] ?? 'Anulado en el sistema de origen');

            return $c
                ? response()->json(['ok' => true, 'accion' => 'anulado', 'comprobante' => $c->descripcion_corta])
                : response()->json(['ok' => false, 'error' => 'No se encontró el comprobante en el sistema contable.'], 404);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'error' => collect($e->errors())->flatten()->implode(' ')], 422);
        }
    }
}
