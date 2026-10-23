<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social');
            $table->string('nombre_fantasia')->nullable();
            $table->string('cuit', 11)->unique();
            $table->enum('condicion_iva', [
                'responsable_inscripto',
                'monotributo',
                'exento',
                'no_alcanzado',
            ])->default('responsable_inscripto');
            $table->enum('condicion_iibb', [
                'local',
                'convenio_multilateral',
                'exento',
                'no_inscripto',
            ])->default('local');
            $table->string('nro_iibb', 20)->nullable();
            $table->date('inicio_actividades')->nullable();
            $table->string('domicilio')->nullable();
            $table->string('localidad', 100)->nullable();
            $table->string('provincia', 60)->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('email')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->unsignedTinyInteger('mes_cierre')->default(12);

            // Agente de retención / percepción (se usan en el módulo de retenciones)
            $table->boolean('agente_retencion_iva')->default(false);
            $table->boolean('agente_retencion_ganancias')->default(false);
            $table->boolean('agente_recaudacion_iibb')->default(false);

            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
