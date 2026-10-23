<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ejercicios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->string('descripcion')->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_cierre');
            $table->enum('estado', ['abierto', 'cerrado'])->default('abierto');
            // Mes hasta el cual no se permiten más asientos (cierre de período mensual)
            $table->date('bloqueado_hasta')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'fecha_inicio', 'fecha_cierre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ejercicios');
    }
};
