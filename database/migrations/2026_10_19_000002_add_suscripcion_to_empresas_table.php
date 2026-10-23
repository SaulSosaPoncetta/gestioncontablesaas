<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('id')->constrained('planes')->nullOnDelete();
            $table->enum('estado_suscripcion', ['prueba', 'activa', 'suspendida', 'cancelada'])
                ->default('prueba')->after('plan_id');
            $table->date('prueba_hasta')->nullable()->after('estado_suscripcion');
            $table->date('vence_el')->nullable()->after('prueba_hasta');
            $table->text('notas_admin')->nullable()->after('vence_el');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['estado_suscripcion', 'prueba_hasta', 'vence_el', 'notas_admin']);
        });
    }
};
