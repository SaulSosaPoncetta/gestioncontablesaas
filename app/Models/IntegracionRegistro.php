<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegracionRegistro extends Model
{
    use BelongsToEmpresa;

    protected $table = 'integracion_registros';

    public const TIPOS_ORIGEN = [
        'factura'            => 'Factura de venta',
        'nota_credito_venta' => 'Nota de crédito de venta',
        'compra'             => 'Compra',
        'nota_credito_compra'=> 'Nota de crédito de compra',
    ];

    protected $fillable = [
        'empresa_id', 'integracion_id', 'tipo_origen', 'id_externo', 'comprobante_id',
        'estado', 'accion', 'mensaje', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function integracion(): BelongsTo
    {
        return $this->belongsTo(Integracion::class);
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }
}
