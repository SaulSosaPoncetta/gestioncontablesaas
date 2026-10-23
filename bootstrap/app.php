<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'empresa'     => \App\Http\Middleware\EmpresaActiva::class,
            'suscripcion' => \App\Http\Middleware\VerificarSuscripcion::class,
            'superadmin'  => \App\Http\Middleware\SuperAdmin::class,
            'modulo'      => \App\Http\Middleware\ModuloHabilitado::class,
            'integracion' => \App\Http\Middleware\AutenticarIntegracion::class,
            'hub'         => \App\Http\Middleware\VerificarFirmaHub::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
