<?php

namespace App\Models;

use App\Support\Afip;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComprobanteAlicuota extends Model
{
    protected $table = 'comprobante_alicuotas';

    public $timestamps = false;

    protected $fillable = ['comprobante_id', 'alicuota_id', 'neto', 'iva'];

    protected $casts = [
        'alicuota_id' => 'integer',
        'neto'        => 'decimal:2',
        'iva'         => 'decimal:2',
    ];

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function getPorcentajeAttribute(): float
    {
        return Afip::ALICUOTAS[$this->alicuota_id] ?? 0.0;
    }
}
