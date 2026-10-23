<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Índices de precios para el ajuste por inflación (serie FACPCE, Res. JG 539/18).
        // Son comunes a todas las empresas: los carga el superadmin.
        Schema::create('indices_inflacion', function (Blueprint $table) {
            $table->id();
            $table->date('periodo')->unique();          // primer día del mes
            $table->decimal('indice', 18, 6);
            $table->string('fuente', 60)->nullable();   // ej: FACPCE (IPIM / IPC Nacional)
            $table->timestamps();
        });

        // Datos del cierre contable
        Schema::table('ejercicios', function (Blueprint $table) {
            $table->timestamp('cerrado_at')->nullable()->after('bloqueado_hasta');
            $table->foreignId('cerrado_por')->nullable()->after('cerrado_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ejercicios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cerrado_por');
            $table->dropColumn('cerrado_at');
        });

        Schema::dropIfExists('indices_inflacion');
    }
};
