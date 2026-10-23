<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sistemas externos autorizados a enviar comprobantes (p. ej. GestiónComercial)
        Schema::create('integraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->string('sistema', 30)->default('gestion_comercial');
            $table->string('nombre', 100);
            $table->char('token_hash', 64)->unique();          // sha256 del token (el token se muestra una sola vez)
            $table->string('token_prefijo', 12);                // para identificarlo en pantalla
            $table->boolean('activa')->default(true);
            $table->foreignId('cuenta_ventas_id')->nullable()->constrained('cuentas')->nullOnDelete();
            $table->foreignId('cuenta_compras_id')->nullable()->constrained('cuentas')->nullOnDelete();
            $table->timestamp('ultimo_uso_at')->nullable();
            $table->timestamps();
        });

        // Registro de cada documento recibido (idempotencia: un id externo → un comprobante)
        Schema::create('integracion_registros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('integracion_id')->constrained('integraciones')->cascadeOnDelete();
            $table->string('tipo_origen', 30);                  // factura | nota_credito_venta | compra | nota_credito_compra
            $table->string('id_externo', 50);
            $table->foreignId('comprobante_id')->nullable()->constrained('comprobantes')->nullOnDelete();
            $table->enum('estado', ['ok', 'error'])->default('ok');
            $table->string('accion', 20)->nullable();           // creado | actualizado | vinculado | anulado
            $table->text('mensaje')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['integracion_id', 'tipo_origen', 'id_externo'], 'integracion_registro_unico');
            $table->index(['empresa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integracion_registros');
        Schema::dropIfExists('integraciones');
    }
};
