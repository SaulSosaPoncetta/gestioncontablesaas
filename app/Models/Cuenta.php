<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Cuenta extends Model
{
    use BelongsToEmpresa;

    public const TIPOS = [
        'activo'             => 'Activo',
        'pasivo'             => 'Pasivo',
        'patrimonio_neto'    => 'Patrimonio Neto',
        'resultado_positivo' => 'Resultado Positivo',
        'resultado_negativo' => 'Resultado Negativo',
        'orden'              => 'Cuentas de Orden',
    ];

    /** Formato de código: segmentos numéricos separados por punto (1, 1.1, 1.1.01, 1.1.01.001) */
    public const REGEX_CODIGO = '/^[1-9](\.\d{1,4}){0,6}$/';

    protected $fillable = [
        'empresa_id', 'padre_id', 'codigo', 'nombre', 'nivel', 'tipo',
        'imputable', 'saldo_habitual', 'monetaria', 'ajustable', 'activa',
    ];

    protected $casts = [
        'nivel'     => 'integer',
        'imputable' => 'boolean',
        'monetaria' => 'boolean',
        'ajustable' => 'boolean',
        'activa'    => 'boolean',
    ];

    /* ----------------------------------------------------------------
     | Relaciones y scopes
     * ---------------------------------------------------------------- */

    public function padre(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'padre_id');
    }

    public function hijas(): HasMany
    {
        return $this->hasMany(Cuenta::class, 'padre_id')->orderBy('codigo');
    }

    public function scopeImputables(Builder $q): Builder
    {
        return $q->where('imputable', true)->where('activa', true);
    }

    /** Devuelve todas las cuentas en orden natural de código (1.1.9 antes que 1.1.10). */
    public static function planOrdenado(?Builder $query = null)
    {
        return ($query ?? static::query())->get()->sortBy('codigo', SORT_NATURAL)->values();
    }

    /* ----------------------------------------------------------------
     | Atributos
     * ---------------------------------------------------------------- */

    public function getCodigoNombreAttribute(): string
    {
        return "{$this->codigo} - {$this->nombre}";
    }

    public function getCodigoPadreAttribute(): ?string
    {
        $partes = explode('.', $this->codigo);
        return count($partes) > 1 ? implode('.', array_slice($partes, 0, -1)) : null;
    }

    /* ----------------------------------------------------------------
     | Reglas de negocio
     * ---------------------------------------------------------------- */

    public function tieneHijas(): bool
    {
        return $this->hijas()->exists();
    }

    /** Se completa cuando exista la tabla de líneas de asientos (paso 4). */
    public function tieneMovimientos(): bool
    {
        return Schema::hasTable('asiento_lineas')
            && DB::table('asiento_lineas')->where('cuenta_id', $this->id)->exists();
    }

    /** Tipo y saldo habitual según el rubro (primer dígito del código). */
    public static function tipoPorCodigo(string $codigo): array
    {
        return match ((int) substr($codigo, 0, 1)) {
            1 => ['activo', 'deudor'],
            2 => ['pasivo', 'acreedor'],
            3 => ['patrimonio_neto', 'acreedor'],
            4 => ['resultado_positivo', 'acreedor'],
            5 => ['resultado_negativo', 'deudor'],
            default => ['orden', 'deudor'],
        };
    }

    /**
     * Sugiere el próximo código hijo de una cuenta, respetando el ancho
     * de los hermanos existentes (ej: 1.1.01.007 → 1.1.01.008).
     */
    public static function siguienteCodigo(?Cuenta $padre): string
    {
        if (! $padre) {
            $usados = static::whereNull('padre_id')->pluck('codigo')->map(fn ($c) => (int) $c);
            for ($i = 1; $i <= 9; $i++) {
                if (! $usados->contains($i)) {
                    return (string) $i;
                }
            }
            return '9';
        }

        $hermanos = static::where('padre_id', $padre->id)->pluck('codigo');

        if ($hermanos->isEmpty()) {
            // Ancho por nivel: 1 / 1.1 / 1.1.01 / 1.1.01.001
            $ancho = match ($padre->nivel + 1) { 1, 2 => 1, 3 => 2, default => 3 };
            return $padre->codigo . '.' . str_pad('1', $ancho, '0', STR_PAD_LEFT);
        }

        $ultimos = $hermanos->map(fn ($c) => substr($c, strrpos($c, '.') + 1));
        $ancho = $ultimos->map(fn ($s) => strlen($s))->max();
        $siguiente = $ultimos->map(fn ($s) => (int) $s)->max() + 1;

        return $padre->codigo . '.' . str_pad((string) $siguiente, $ancho, '0', STR_PAD_LEFT);
    }
}
