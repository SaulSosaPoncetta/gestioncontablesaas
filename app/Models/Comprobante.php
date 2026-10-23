<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use App\Support\Afip;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comprobante extends Model
{
    use BelongsToEmpresa;

    protected $fillable = [
        'empresa_id', 'ejercicio_id', 'circuito', 'tercero_id', 'tipo_comprobante', 'punto_venta', 'numero',
        'fecha', 'periodo_iva', 'cae', 'vto_cae', 'cuenta_imputacion_id', 'operacion_iva',
        'neto_gravado', 'no_gravado', 'exento', 'iva', 'percepcion_iva', 'percepcion_iibb', 'otros_tributos', 'total',
        'asiento_id', 'estado', 'observaciones', 'user_id',
    ];

    protected $casts = [
        'fecha'            => 'date',
        'vto_cae'          => 'date',
        'tipo_comprobante' => 'integer',
        'punto_venta'      => 'integer',
        'numero'           => 'integer',
        'neto_gravado'     => 'decimal:2',
        'no_gravado'       => 'decimal:2',
        'exento'           => 'decimal:2',
        'iva'              => 'decimal:2',
        'percepcion_iva'   => 'decimal:2',
        'percepcion_iibb'  => 'decimal:2',
        'otros_tributos'   => 'decimal:2',
        'total'            => 'decimal:2',
    ];

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }

    public function alicuotas(): HasMany
    {
        return $this->hasMany(ComprobanteAlicuota::class);
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class);
    }

    public function ejercicio(): BelongsTo
    {
        return $this->belongsTo(Ejercicio::class);
    }

    public function cuentaImputacion(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_imputacion_id');
    }

    public function esNotaCredito(): bool
    {
        return Afip::esNotaCredito($this->tipo_comprobante);
    }

    public function estaAnulado(): bool
    {
        return $this->estado === 'anulado';
    }

    /** Signo para totalizar libros: las notas de crédito restan */
    public function signo(): int
    {
        return $this->esNotaCredito() ? -1 : 1;
    }

    public function getNumeroCompletoAttribute(): string
    {
        return Afip::numeroFormateado($this->punto_venta, $this->numero);
    }

    /** Ej: "FA 00001-00001234" */
    public function getDescripcionCortaAttribute(): string
    {
        return Afip::abreviatura($this->tipo_comprobante) . ' ' . $this->numero_completo;
    }

    public function periodoCerrado(): bool
    {
        return IvaPosicion::estaCerrado($this->periodo_iva);
    }

    /** Editable si no está anulado, su período IVA está abierto y su asiento admite cambios. */
    public function esEditable(): bool
    {
        return ! $this->estaAnulado()
            && ! $this->periodoCerrado()
            && (! $this->asiento || $this->asiento->esEditable());
    }
}
