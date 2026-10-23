<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $table = 'planes';

    public const MODULOS = [
        'iva'           => 'Libros IVA y Libro IVA Digital',
        'retenciones'   => 'Retenciones y percepciones (SICORE / SIRE / IIBB)',
        'integraciones' => 'Importación ARCA e integración con GestiónComercial',
    ];

    protected $fillable = [
        'nombre', 'descripcion', 'precio_mensual', 'max_usuarios',
        'modulo_iva', 'modulo_retenciones', 'modulo_integraciones',
        'activo', 'orden',
    ];

    protected $casts = [
        'precio_mensual'       => 'decimal:2',
        'modulo_iva'           => 'boolean',
        'modulo_retenciones'   => 'boolean',
        'modulo_integraciones' => 'boolean',
        'activo'               => 'boolean',
    ];

    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class);
    }

    public function tieneModulo(string $modulo): bool
    {
        return (bool) $this->getAttribute('modulo_' . $modulo);
    }

    public function getPrecioFormateadoAttribute(): string
    {
        return '$ ' . number_format((float) $this->precio_mensual, 2, ',', '.');
    }
}
