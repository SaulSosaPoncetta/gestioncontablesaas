<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsientoLinea extends Model
{
    protected $table = 'asiento_lineas';

    protected $fillable = ['asiento_id', 'cuenta_id', 'debe', 'haber', 'detalle', 'orden'];

    protected $casts = [
        'debe'  => 'decimal:2',
        'haber' => 'decimal:2',
        'orden' => 'integer',
    ];

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class);
    }

    public function cuenta(): BelongsTo
    {
        // Sin el scope de empresa: la línea ya pertenece a un asiento de la empresa
        return $this->belongsTo(Cuenta::class)->withoutGlobalScope('empresa');
    }
}
