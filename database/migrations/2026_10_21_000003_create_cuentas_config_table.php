<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cuentas contables que usa el sistema para generar asientos automáticos
        Schema::create('cuentas_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('clave', 50);
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_config');
    }
};
