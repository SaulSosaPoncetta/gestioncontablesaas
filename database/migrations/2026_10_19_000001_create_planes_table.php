<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 60);
            $table->string('descripcion')->nullable();
            $table->decimal('precio_mensual', 12, 2)->default(0);
            $table->unsignedSmallInteger('max_usuarios')->nullable(); // null = ilimitado

            // El núcleo contable (plan de cuentas, asientos, libros, balances) está en todos los planes
            $table->boolean('modulo_iva')->default(false);
            $table->boolean('modulo_retenciones')->default(false);
            $table->boolean('modulo_integraciones')->default(false);

            $table->boolean('activo')->default(true);      // visible en el registro
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planes');
    }
};
