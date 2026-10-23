<?php

use App\Http\Controllers\Api\HubController;
use App\Http\Controllers\Api\IntegracionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de integración (token por empresa, generado en Configuración › Integraciones)
|--------------------------------------------------------------------------
*/
Route::prefix('integracion/v1')->middleware(['integracion', 'throttle:120,1'])->group(function () {
    Route::post('/ping', [IntegracionController::class, 'ping']);
    Route::post('/comprobantes', [IntegracionController::class, 'comprobante']);
    Route::post('/comprobantes/anular', [IntegracionController::class, 'anular']);
});

/*
|--------------------------------------------------------------------------
| Órdenes de MiGestión Panel (firmadas con MIGESTION_HUB_WEBHOOK_SECRET)
|--------------------------------------------------------------------------
*/
Route::post('/hub/integracion', [HubController::class, 'integracion'])->middleware(['hub', 'throttle:30,1']);
