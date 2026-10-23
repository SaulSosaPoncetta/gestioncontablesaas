<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asiento extends Model
{
    use BelongsToEmpresa;

    public const TIPOS = [
        'apertura'    => 'Apertura',
        'normal'      => 'Normal',
        'ajuste'      => 'Ajuste',
        'refundicion' => 'Refundición',
        'cierre'      => 'Cierre',
    ];

    public const ESTADOS = [
        'borrador'   => 'Borrador',
        'registrado' => 'Registrado',
        'anulado'    => 'Anulado',
    ];

    /** Orden de los tipos dentro de un mismo día al renumerar */
    public const PRIORIDAD_TIPO = ['apertura' => 1, 'normal' => 2, 'ajuste' => 3, 'refundicion' => 4, 'cierre' => 5];

    protected $fillable = [
        'empresa_id', 'ejercicio_id', 'numero', 'fecha', 'tipo', 'concepto', 'estado',
        'origen', 'total', 'user_id', 'anulado_at', 'anulado_por', 'motivo_anulacion',
    ];

    protected $casts = [
        'fecha'      => 'date',
        'total'      => 'decimal:2',
        'numero'     => 'integer',
        'anulado_at' => 'datetime',
    ];

    public function lineas(): HasMany
    {
        return $this->hasMany(AsientoLinea::class)->orderBy('orden');
    }

    public function ejercicio(): BelongsTo
    {
        return $this->belongsTo(Ejercicio::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    public function scopeRegistrados(Builder $q): Builder
    {
        return $q->where('estado', 'registrado');
    }

    public function esBorrador(): bool
    {
        return $this->estado === 'borrador';
    }

    public function estaAnulado(): bool
    {
        return $this->estado === 'anulado';
    }

    /** Se puede modificar si no está anulado y el ejercicio admite su fecha (abierto y no bloqueado). */
    public function esEditable(): bool
    {
        return ! $this->estaAnulado() && $this->ejercicio && $this->ejercicio->admiteFecha($this->fecha);
    }

    /** Los asientos generados por comprobantes se modifican desde el comprobante, no a mano. */
    public function esAutomatico(): bool
    {
        return $this->origen !== 'manual';
    }

    public function esEditableManualmente(): bool
    {
        return $this->esEditable() && ! $this->esAutomatico();
    }

    /** Comprobante que generó el asiento (si es automático) */
    public function comprobante()
    {
        return $this->hasOne(Comprobante::class);
    }

    public function getNumeroFormateadoAttribute(): string
    {
        return $this->numero ? str_pad((string) $this->numero, 5, '0', STR_PAD_LEFT) : 'S/N';
    }
}
