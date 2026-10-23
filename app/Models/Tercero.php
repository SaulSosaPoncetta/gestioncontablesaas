<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use App\Support\Afip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tercero extends Model
{
    use BelongsToEmpresa;

    public const TIPOS = [
        'cliente'   => 'Cliente',
        'proveedor' => 'Proveedor',
        'ambos'     => 'Cliente y proveedor',
    ];

    protected $fillable = [
        'empresa_id', 'tipo', 'razon_social', 'tipo_documento', 'nro_documento', 'condicion_iva',
        'domicilio', 'localidad', 'provincia', 'email', 'telefono',
        'cuenta_id', 'cuenta_imputacion_id', 'operacion_iva', 'observaciones', 'activo',
    ];

    protected $casts = [
        'tipo_documento' => 'integer',
        'condicion_iva'  => 'integer',
        'activo'         => 'boolean',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class);
    }

    public function cuentaImputacion(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_imputacion_id');
    }

    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class);
    }

    /** Terceros que operan en el circuito (venta → clientes, compra → proveedores) */
    public function scopeDelCircuito(Builder $q, string $circuito): Builder
    {
        return $q->whereIn('tipo', [$circuito === 'venta' ? 'cliente' : 'proveedor', 'ambos']);
    }

    public function getDocumentoFormateadoAttribute(): string
    {
        $n = (string) $this->nro_documento;
        if (in_array($this->tipo_documento, [80, 86], true) && strlen($n) === 11) {
            $n = substr($n, 0, 2) . '-' . substr($n, 2, 8) . '-' . substr($n, 10);
        }
        $tipo = Afip::TIPOS_DOCUMENTO[$this->tipo_documento] ?? '';
        return $n !== '' ? (in_array($this->tipo_documento, [80, 86, 96], true) ? $tipo . ' ' : '') . $n : '—';
    }

    public function getCondicionIvaTextoAttribute(): string
    {
        return Afip::CONDICIONES_IVA[$this->condicion_iva] ?? '';
    }
}
