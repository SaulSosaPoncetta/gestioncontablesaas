<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Posición mensual de IVA cerrada (una por período). Cerrar un período bloquea sus comprobantes.
        Schema::create('iva_posiciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->char('periodo', 7);                                   // AAAA-MM

            $table->decimal('debito_fiscal', 15, 2)->default(0);
            $table->decimal('credito_fiscal', 15, 2)->default(0);
            $table->decimal('saldo_tecnico_anterior', 15, 2)->default(0);
            $table->decimal('saldo_libre_anterior', 15, 2)->default(0);
            $table->decimal('percepciones', 15, 2)->default(0);           // percepciones de IVA sufridas
            $table->decimal('retenciones', 15, 2)->default(0);            // retenciones de IVA sufridas
            $table->decimal('impuesto_determinado', 15, 2)->default(0);
            $table->decimal('saldo_tecnico_favor', 15, 2)->default(0);    // pasa al período siguiente
            $table->decimal('saldo_libre_favor', 15, 2)->default(0);      // pasa al período siguiente
            $table->decimal('a_pagar', 15, 2)->default(0);

            $table->foreignId('asiento_id')->nullable()->constrained('asientos')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['empresa_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iva_posiciones');
    }
};
