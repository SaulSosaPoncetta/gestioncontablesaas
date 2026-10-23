<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ejercicio_id')->constrained('ejercicios')->restrictOnDelete();
            $table->enum('circuito', ['venta', 'compra']);
            $table->foreignId('tercero_id')->constrained('terceros')->restrictOnDelete();

            $table->unsignedSmallInteger('tipo_comprobante');   // código ARCA (1 FA, 6 FB, 11 FC, 3 NCA...)
            $table->unsignedInteger('punto_venta');
            $table->unsignedBigInteger('numero');
            $table->date('fecha');
            $table->char('periodo_iva', 7);                     // AAAA-MM en que se declara
            $table->string('cae', 20)->nullable();
            $table->date('vto_cae')->nullable();

            $table->foreignId('cuenta_imputacion_id')->nullable()->constrained('cuentas')->nullOnDelete();

            $table->decimal('neto_gravado', 15, 2)->default(0);
            $table->decimal('no_gravado', 15, 2)->default(0);
            $table->decimal('exento', 15, 2)->default(0);
            $table->decimal('iva', 15, 2)->default(0);
            $table->decimal('percepcion_iva', 15, 2)->default(0);
            $table->decimal('percepcion_iibb', 15, 2)->default(0);
            $table->decimal('otros_tributos', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->foreignId('asiento_id')->nullable()->constrained('asientos')->nullOnDelete();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->string('observaciones')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['empresa_id', 'circuito', 'periodo_iva']);
            $table->index(['empresa_id', 'circuito', 'tipo_comprobante', 'punto_venta', 'numero'], 'comprobantes_numero_idx');
        });

        Schema::create('comprobante_alicuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('comprobantes')->cascadeOnDelete();
            $table->unsignedTinyInteger('alicuota_id');         // código ARCA: 3 0%, 4 10,5%, 5 21%, 6 27%, 8 5%, 9 2,5%
            $table->decimal('neto', 15, 2);
            $table->decimal('iva', 15, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_alicuotas');
        Schema::dropIfExists('comprobantes');
    }
};
