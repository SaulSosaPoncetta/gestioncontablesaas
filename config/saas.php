<?php

return [
    // Días de prueba gratis al registrarse
    'dias_prueba' => env('SAAS_DIAS_PRUEBA', 15),

    // Días después del vencimiento con acceso completo (aviso en pantalla).
    // Pasado ese plazo la cuenta queda en solo lectura.
    'dias_gracia' => env('SAAS_DIAS_GRACIA', 7),

    // Permitir el alta de nuevas empresas desde /register
    'registro_abierto' => env('SAAS_REGISTRO_ABIERTO', true),

    // Datos que ve el cliente para renovar
    'contacto' => [
        'email'    => env('SAAS_CONTACTO_EMAIL', 'ventas@migestion.com.ar'),
        'whatsapp' => env('SAAS_CONTACTO_WHATSAPP', ''),  // ej: 5492231234567
        'datos_transferencia' => env('SAAS_DATOS_TRANSFERENCIA', ''),
    ],
];
