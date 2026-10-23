<?php

use App\Models\Empresa;
use App\Services\HubClientes;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| MiGestión Panel (hub)
|--------------------------------------------------------------------------
| php artisan hub:registrar-empresas             → da de alta en el hub todas las empresas (las ya registradas se actualizan)
| php artisan hub:registrar-empresas --empresa=3 → solo una
*/
Artisan::command('hub:registrar-empresas {--empresa=}', function (HubClientes $hub) {
    if (! config('migestion_hub.url') || ! config('migestion_hub.api_key')) {
        $this->error('Falta configurar MIGESTION_HUB_URL y MIGESTION_HUB_API_KEY en el .env');
        return;
    }

    $empresas = Empresa::when($this->option('empresa'), fn ($q, $id) => $q->where('id', $id))->get();

    foreach ($empresas as $empresa) {
        $this->line(($hub->registrar($empresa) ? '✓ ' : '✗ ') . "{$empresa->razon_social} ({$empresa->cuit})");
    }

    $this->info("Listo: {$empresas->count()} empresa(s).");
})->purpose('Registra las empresas en MiGestión Panel para la vinculación automática');
