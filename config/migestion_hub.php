<?php

return [
    // URL de MiGestión Panel (el hub), ej: https://panelgestion.migestion.com.ar
    'url' => env('MIGESTION_HUB_URL'),

    // API key que generó el hub al dar de alta este sistema (Sistemas SaaS › Contabilidad)
    'api_key' => env('MIGESTION_HUB_API_KEY'),

    // Secret con el que el hub firma lo que le manda a este sistema
    'webhook_secret' => env('MIGESTION_HUB_WEBHOOK_SECRET'),

    // Segundos de validez de una orden firmada del hub (evita que se reenvíe más tarde)
    'tolerancia_segundos' => 300,
];
