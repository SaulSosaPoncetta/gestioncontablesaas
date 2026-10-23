<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    public const MEDIOS = [
        'transferencia' => 'Transferencia',
        'mercadopago'   => 'Mercado Pago',
        'efectivo'      => 'Efectivo',
        'otro'          => 'Otro',
    ];

    protected $fillable = [
        'empresa_id', 'plan_id', 'fecha', 'periodo_desde', 'periodo_hasta',
        'importe', 'medio', 'referencia', 'observaciones', 'registrado_por',
    ];

    protected $casts = [
        'fecha'         => 'date',
        'periodo_desde' => 'date',
        'periodo_hasta' => 'date',
        'importe'       => 'decimal:2',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
