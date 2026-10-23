<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('es_superadmin')->default(false)->after('password');
            $table->foreignId('empresa_actual_id')->nullable()->after('es_superadmin')
                ->constrained('empresas')->nullOnDelete();
            $table->boolean('activo')->default(true)->after('empresa_actual_id');
        });

        Schema::create('empresa_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('rol', ['administrador', 'contador', 'operador', 'consulta'])->default('operador');
            $table->timestamps();
            $table->unique(['empresa_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_user');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_actual_id');
            $table->dropColumn(['es_superadmin', 'activo']);
        });
    }
};
