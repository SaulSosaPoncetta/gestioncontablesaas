<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Órdenes de MiGestión Panel (el hub): el cuerpo viene firmado con HMAC-SHA256
 * (header X-MiGestion-Signature) usando el webhook_secret de este sistema,
 * e incluye "ts" (hora del envío) para que no se pueda reenviar más tarde.
 */
class VerificarFirmaHub
{
    public function handle(Request $request, Closure $next): Response
    {
        $secreto = (string) config('migestion_hub.webhook_secret');
        $firma = (string) $request->header('X-MiGestion-Signature', '');

        if ($secreto === '' || ! hash_equals(hash_hmac('sha256', $request->getContent(), $secreto), $firma)) {
            Log::warning('Orden del hub con firma inválida', ['ip' => $request->ip()]);

            return response()->json(['ok' => false, 'error' => 'firma inválida'], 401);
        }

        if (abs(time() - (int) $request->input('ts')) > (int) config('migestion_hub.tolerancia_segundos', 300)) {
            return response()->json(['ok' => false, 'error' => 'orden vencida (revisar la hora de los servidores)'], 401);
        }

        return $next($request);
    }
}
