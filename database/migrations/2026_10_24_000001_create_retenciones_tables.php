<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Regímenes que aplica la empresa como agente de retención
        Schema::create('regimenes_retencion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->enum('impuesto', ['ganancias', 'iva', 'iibb_arba', 'iibb_agip', 'iibb_otra']);
            $table->string('codigo', 10);                        // código de régimen (SICORE / SIRE / provincial)
            $table->string('descripcion');
            $table->decimal('alicuota_inscripto', 6, 2)->default(0);
            $table->decimal('alicuota_no_inscripto', 6, 2)->default(0);
            $table->decimal('minimo_no_sujeto', 15, 2)->default(0);
            $table->decimal('minimo_retencion', 15, 2)->default(0);
            $table->boolean('acumula_mensual')->default(false); // RG 830: acumula pagos del mes por proveedor
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'impuesto']);
        });

        // Retenciones practicadas (como agente, al pagar) y sufridas (certificados recibidos al cobrar)
        Schema::create('retenciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ejercicio_id')->constrained('ejercicios')->restrictOnDelete();
            $table->enum('tipo', ['practicada', 'sufrida']);
            $table->enum('impuesto', ['ganancias', 'iva', 'iibb_arba', 'iibb_agip', 'iibb_otra', 'suss']);
            $table->foreignId('regimen_id')->nullable()->constrained('regimenes_retencion')->nullOnDelete();
            $table->foreignId('tercero_id')->constrained('terceros')->restrictOnDelete();
            $table->foreignId('comprobante_id')->nullable()->constrained('comprobantes')->nullOnDelete();

            $table->date('fecha');
            $table->char('periodo', 7);                          // AAAA-MM
            $table->string('numero_certificado', 30)->nullable();
            $table->enum('condicion', ['inscripto', 'no_inscripto'])->default('inscripto');
            $table->decimal('importe_comprobante', 15, 2)->default(0);
            $table->decimal('base_imponible', 15, 2)->default(0);
            $table->decimal('alicuota', 6, 2)->default(0);
            $table->decimal('importe', 15, 2)->default(0);

            $table->foreignId('asiento_id')->nullable()->constrained('asientos')->nullOnDelete();
            $table->enum('estado', ['registrado', 'anulado'])->default('registrado');
            $table->string('observaciones')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['empresa_id', 'tipo', 'impuesto', 'periodo']);
            $table->index(['empresa_id', 'tercero_id', 'regimen_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retenciones');
        Schema::dropIfExists('regimenes_retencion');
    }
};
