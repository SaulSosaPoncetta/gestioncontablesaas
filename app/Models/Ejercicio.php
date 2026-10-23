<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Ejercicio extends Model
{
    use BelongsToEmpresa;

    protected $fillable = [
        'empresa_id', 'numero', 'descripcion', 'fecha_inicio', 'fecha_cierre',
        'estado', 'bloqueado_hasta', 'cerrado_at', 'cerrado_por',
    ];

    protected $casts = [
        'fecha_inicio'    => 'date',
        'fecha_cierre'    => 'date',
        'bloqueado_hasta' => 'date',
        'cerrado_at'      => 'datetime',
    ];

    public function estaAbierto(): bool
    {
        return $this->estado === 'abierto';
    }

    /** Indica si se puede registrar un asiento en la fecha dada. */
    public function admiteFecha(Carbon|string $fecha): bool
    {
        $fecha = Carbon::parse($fecha)->startOfDay();

        if (! $this->estaAbierto()) {
            return false;
        }
        if ($fecha->lt($this->fecha_inicio) || $fecha->gt($this->fecha_cierre)) {
            return false;
        }
        if ($this->bloqueado_hasta && $fecha->lte($this->bloqueado_hasta)) {
            return false;
        }
        return true;
    }

    /** Ejercicio inmediato siguiente (empieza el día después del cierre). */
    public function siguiente(): ?self
    {
        return static::whereDate('fecha_inicio', $this->fecha_cierre->copy()->addDay()->toDateString())->first();
    }

    /** ¿Se cerró con asientos de refundición y cierre (no solo bloqueado)? */
    public function tieneCierreContable(): bool
    {
        return Asiento::where('ejercicio_id', $this->id)
            ->where('origen', 'cierre_ejercicio')
            ->where('estado', 'registrado')
            ->exists();
    }

    public function getNombreAttribute(): string
    {
        return "Ejercicio {$this->numero} ({$this->fecha_inicio->format('d/m/Y')} - {$this->fecha_cierre->format('d/m/Y')})";
    }
}
