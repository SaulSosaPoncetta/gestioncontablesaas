<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ejercicio_id')->constrained('ejercicios')->restrictOnDelete();

            // Número correlativo dentro del ejercicio. Los borradores no tienen número.
            $table->unsignedInteger('numero')->nullable();
            $table->date('fecha');
            $table->enum('tipo', ['apertura', 'normal', 'ajuste', 'cierre', 'refundicion'])->default('normal');
            $table->string('concepto');
            $table->enum('estado', ['borrador', 'registrado', 'anulado'])->default('borrador');
            $table->string('origen', 30)->default('manual'); // manual | iva_ventas | iva_compras | importado | sistema
            $table->decimal('total', 15, 2)->default(0);

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('anulado_at')->nullable();
            $table->foreignId('anulado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion')->nullable();
            $table->timestamps();

            $table->unique(['ejercicio_id', 'numero']);
            $table->index(['empresa_id', 'ejercicio_id', 'fecha']);
            $table->index(['empresa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asientos');
    }
};
