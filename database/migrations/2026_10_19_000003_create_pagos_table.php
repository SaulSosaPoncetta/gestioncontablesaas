<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('planes')->nullOnDelete();
            $table->date('fecha');
            $table->date('periodo_desde');
            $table->date('periodo_hasta');
            $table->decimal('importe', 12, 2);
            $table->enum('medio', ['transferencia', 'mercadopago', 'efectivo', 'otro'])->default('transferencia');
            $table->string('referencia', 100)->nullable();
            $table->string('observaciones')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['empresa_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
