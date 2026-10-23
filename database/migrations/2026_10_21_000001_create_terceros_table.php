<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clientes y proveedores en una sola tabla
        Schema::create('terceros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->enum('tipo', ['cliente', 'proveedor', 'ambos'])->default('cliente');
            $table->string('razon_social');
            $table->unsignedTinyInteger('tipo_documento')->default(80);   // códigos ARCA: 80 CUIT, 86 CUIL, 96 DNI, 99 sin identificar
            $table->string('nro_documento', 20)->nullable();
            $table->unsignedTinyInteger('condicion_iva')->default(1);      // códigos ARCA: 1 RI, 4 Exento, 5 CF, 6 Monotributo...
            $table->string('domicilio')->nullable();
            $table->string('localidad', 100)->nullable();
            $table->string('provincia', 60)->nullable();
            $table->string('email')->nullable();
            $table->string('telefono', 50)->nullable();

            // Cuenta patrimonial (deudores / proveedores) y cuenta de imputación por defecto (ventas / gasto)
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->nullOnDelete();
            $table->foreignId('cuenta_imputacion_id')->nullable()->constrained('cuentas')->nullOnDelete();

            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'tipo']);
            $table->index(['empresa_id', 'nro_documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terceros');
    }
};
