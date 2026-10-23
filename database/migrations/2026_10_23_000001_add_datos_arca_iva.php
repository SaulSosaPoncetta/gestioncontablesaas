<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Código de actividad (6 dígitos, nomenclador F.883) para la apertura del débito fiscal en IVA Simple
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('actividad_afip', 6)->nullable()->after('nro_iibb');
        });

        // Ventas: 1 = cosas muebles, obras, locaciones y servicios · 2 = venta de bienes de uso
        // Compras: 1 = bienes (excepto bienes de uso) · 2 = locaciones · 3 = prestaciones de servicios · 4 = inversiones en bienes de uso
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->unsignedTinyInteger('operacion_iva')->default(1)->after('cuenta_imputacion_id');
        });

        // Concepto habitual de las compras al proveedor
        Schema::table('terceros', function (Blueprint $table) {
            $table->unsignedTinyInteger('operacion_iva')->nullable()->after('cuenta_imputacion_id');
        });
    }

    public function down(): void
    {
        Schema::table('terceros', fn (Blueprint $t) => $t->dropColumn('operacion_iva'));
        Schema::table('comprobantes', fn (Blueprint $t) => $t->dropColumn('operacion_iva'));
        Schema::table('empresas', fn (Blueprint $t) => $t->dropColumn('actividad_afip'));
    }
};
