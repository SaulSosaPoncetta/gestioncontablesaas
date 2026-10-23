<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IvaPosicion extends Model
{
    use BelongsToEmpresa;

    protected $table = 'iva_posiciones';

    protected $fillable = [
        'empresa_id', 'periodo', 'debito_fiscal', 'credito_fiscal',
        'saldo_tecnico_anterior', 'saldo_libre_anterior', 'percepciones', 'retenciones',
        'impuesto_determinado', 'saldo_tecnico_favor', 'saldo_libre_favor', 'a_pagar',
        'asiento_id', 'user_id',
    ];

    protected $casts = [
        'debito_fiscal'          => 'decimal:2',
        'credito_fiscal'         => 'decimal:2',
        'saldo_tecnico_anterior' => 'decimal:2',
        'saldo_libre_anterior'   => 'decimal:2',
        'percepciones'           => 'decimal:2',
        'retenciones'            => 'decimal:2',
        'impuesto_determinado'   => 'decimal:2',
        'saldo_tecnico_favor'    => 'decimal:2',
        'saldo_libre_favor'      => 'decimal:2',
        'a_pagar'                => 'decimal:2',
    ];

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function estaCerrado(string $periodo): bool
    {
        return static::where('periodo', $periodo)->exists();
    }

    /** Última posición cerrada anterior al período (de ahí salen los saldos a favor). */
    public static function anteriorA(string $periodo): ?self
    {
        return static::where('periodo', '<', $periodo)->orderByDesc('periodo')->first();
    }

    public function getPeriodoTextoAttribute(): string
    {
        return Carbon::createFromFormat('Y-m-d', $this->periodo . '-01')->format('m/Y');
    }
}
