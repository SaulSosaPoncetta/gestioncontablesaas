<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('padre_id')->nullable()->constrained('cuentas')->nullOnDelete();

            $table->string('codigo', 30);          // ej: 1.1.01.001
            $table->string('nombre');
            $table->unsignedTinyInteger('nivel');  // 1 = rubro principal
            $table->enum('tipo', [
                'activo',
                'pasivo',
                'patrimonio_neto',
                'resultado_positivo',
                'resultado_negativo',
                'orden',
            ]);
            // Solo las cuentas imputables reciben movimientos en asientos
            $table->boolean('imputable')->default(false);
            $table->enum('saldo_habitual', ['deudor', 'acreedor']);
            // Para ajuste por inflación (RT 6): monetaria / no monetaria
            $table->boolean('monetaria')->default(true);
            $table->boolean('ajustable')->default(false);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo']);
            $table->index(['empresa_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas');
    }
};
