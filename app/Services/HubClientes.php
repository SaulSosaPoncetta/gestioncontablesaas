<?php

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Da de alta la empresa en MiGestión Panel (el hub). Con el CUIT, el hub la reconoce
 * si ya usa GestiónComercial y vincula los dos sistemas solo.
 * Si el hub no está configurado o no responde, no frena nada (se puede reintentar con
 * php artisan hub:registrar-empresas).
 */
class HubClientes
{
    public function registrar(Empresa $empresa, ?string $emailAdministrador = null): bool
    {
        if (! config('migestion_hub.url') || ! config('migestion_hub.api_key')) {
            return false;
        }

        $email = $emailAdministrador
            ?: $empresa->usuarios()->wherePivot('rol', 'administrador')->value('email')
            ?: $empresa->email;

        if (! $email) {
            return false;
        }

        try {
            Http::withHeaders(['X-Api-Key' => config('migestion_hub.api_key')])
                ->acceptJson()
                ->timeout(8)
                ->post(rtrim(config('migestion_hub.url'), '/') . '/api/registrar-cliente', [
                    'referencia_externa'    => (string) $empresa->id,
                    'nombre'                => $empresa->razon_social,
                    'email'                 => $email,
                    'identificacion_fiscal' => $empresa->cuit,
                    'telefono'              => $empresa->telefono,
                ])
                ->throw();

            return true;
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar la empresa en el hub', ['empresa_id' => $empresa->id, 'error' => $e->getMessage()]);

            return false;
        }
    }
}
