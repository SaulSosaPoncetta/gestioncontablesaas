<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Plan;
use App\Models\User;
use App\Services\AltaEmpresa;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Planes (precios de ejemplo: ajustarlos desde Admin > Planes)
        $planes = [
            ['nombre' => 'Inicial', 'descripcion' => 'Contabilidad completa para monotributistas y pequeñas empresas',
             'precio_mensual' => 25000, 'max_usuarios' => 2, 'modulo_iva' => false, 'modulo_retenciones' => false, 'modulo_integraciones' => false, 'orden' => 1],
            ['nombre' => 'Profesional', 'descripcion' => 'Contabilidad + Libros IVA y Libro IVA Digital',
             'precio_mensual' => 45000, 'max_usuarios' => 5, 'modulo_iva' => true, 'modulo_retenciones' => true, 'modulo_integraciones' => false, 'orden' => 2],
            ['nombre' => 'Full', 'descripcion' => 'Todo incluido: retenciones, percepciones, importación ARCA e integraciones',
             'precio_mensual' => 70000, 'max_usuarios' => null, 'modulo_iva' => true, 'modulo_retenciones' => true, 'modulo_integraciones' => true, 'orden' => 3],
        ];

        foreach ($planes as $p) {
            Plan::updateOrCreate(['nombre' => $p['nombre']], $p + ['activo' => true]);
        }

        // Superadmin (dueño del SaaS): no pertenece a ninguna empresa
        User::updateOrCreate(
            ['email' => 'admin@migestion.com.ar'],
            ['name' => 'Administrador MiGestión', 'password' => 'admin1234', 'es_superadmin' => true, 'activo' => true]
        );

        // Empresa demo con su usuario cliente
        if (! Empresa::where('cuit', '30712345671')->exists()) {
            $demo = User::create([
                'name'     => 'Usuario Demo',
                'email'    => 'demo@migestion.com.ar',
                'password' => 'demo1234',
            ]);

            AltaEmpresa::crear(
                [
                    'razon_social'   => 'Empresa Demo S.A.',
                    'cuit'           => '30712345671',
                    'condicion_iva'  => 'responsable_inscripto',
                    'condicion_iibb' => 'local',
                    'provincia'      => 'Buenos Aires',
                    'mes_cierre'     => 12,
                ],
                Plan::where('nombre', 'Profesional')->first(),
                $demo
            );
        }
    }
}
