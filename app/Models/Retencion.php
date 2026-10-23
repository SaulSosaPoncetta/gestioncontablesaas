<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Retencion extends Model
{
    use BelongsToEmpresa;

    protected $table = 'retenciones';

    public const IMPUESTOS = [
        'ganancias' => 'Ganancias',
        'iva'       => 'IVA',
        'iibb_arba' => 'IIBB ARBA',
        'iibb_agip' => 'IIBB AGIP',
        'iibb_otra' => 'IIBB otra jurisdicción',
        'suss'      => 'SUSS',
    ];

    /** Cuenta automática según impuesto y tipo (practicada → a depositar, sufrida → crédito) */
    public const CLAVES_CUENTA = [
        'practicada' => [
            'ganancias' => 'ret_ganancias_depositar', 'iva' => 'ret_iva_depositar',
            'iibb_arba' => 'ret_iibb_depositar', 'iibb_agip' => 'ret_iibb_depositar', 'iibb_otra' => 'ret_iibb_depositar',
            'suss' => 'ret_suss_depositar',
        ],
        'sufrida' => [
            'ganancias' => 'ret_ganancias_sufridas', 'iva' => 'ret_iva_sufridas',
            'iibb_arba' => 'ret_iibb_sufridas', 'iibb_agip' => 'ret_iibb_sufridas', 'iibb_otra' => 'ret_iibb_sufridas',
            'suss' => 'ret_suss_sufridas',
        ],
    ];

    protected $fillable = [
        'empresa_id', 'ejercicio_id', 'tipo', 'impuesto', 'regimen_id', 'tercero_id', 'comprobante_id',
        'fecha', 'periodo', 'numero_certificado', 'condicion', 'importe_comprobante', 'base_imponible',
        'alicuota', 'importe', 'asiento_id', 'estado', 'observaciones', 'user_id',
    ];

    protected $casts = [
        'fecha'               => 'date',
        'importe_comprobante' => 'decimal:2',
        'base_imponible'      => 'decimal:2',
        'alicuota'            => 'decimal:2',
        'importe'             => 'decimal:2',
    ];

    public function regimen(): BelongsTo
    {
        return $this->belongsTo(RegimenRetencion::class, 'regimen_id');
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }

    public function comprobante(): BelongsTo
    {
        return $this->belongsTo(Comprobante::class);
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(Asiento::class);
    }

    public function esPracticada(): bool
    {
        return $this->tipo === 'practicada';
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'anulado';
    }

    public function esEditable(): bool
    {
        $ivaCerrado = $this->tipo === 'sufrida' && $this->impuesto === 'iva' && IvaPosicion::estaCerrado($this->periodo);

        return ! $this->estaAnulada() && ! $ivaCerrado && (! $this->asiento || $this->asiento->esEditable());
    }

    public function getImpuestoTextoAttribute(): string
    {
        return self::IMPUESTOS[$this->impuesto] ?? $this->impuesto;
    }
}
