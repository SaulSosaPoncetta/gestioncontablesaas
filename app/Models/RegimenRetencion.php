<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegimenRetencion extends Model
{
    use BelongsToEmpresa;

    protected $table = 'regimenes_retencion';

    public const IMPUESTOS = [
        'ganancias' => 'Ganancias',
        'iva'       => 'IVA',
        'iibb_arba' => 'IIBB ARBA (Bs. As.)',
        'iibb_agip' => 'IIBB AGIP (CABA)',
        'iibb_otra' => 'IIBB otra jurisdicción',
    ];

    /**
     * Regímenes habituales para empezar. Las alícuotas y mínimos son de referencia:
     * verificarlos con la normativa vigente (RG 830 y modificatorias, RG 2854, DN ARBA) antes de usarlos.
     * [impuesto, código, descripción, alíc. inscripto, alíc. no inscripto, mínimo no sujeto, mínimo retención, acumula]
     */
    public const HABITUALES = [
        ['ganancias', '078', 'Enajenación de bienes muebles y bienes de cambio', 2.00, 10.00, 0, 0, true],
        ['ganancias', '094', 'Locaciones de obra y/o servicios no ejecutados en relación de dependencia', 2.00, 28.00, 0, 0, true],
        ['ganancias', '031', 'Alquileres o arrendamientos de bienes inmuebles urbanos', 6.00, 28.00, 0, 0, true],
        ['ganancias', '116', 'Honorarios de directores, profesionales liberales y otros', 0, 28.00, 0, 0, true],
        ['iva',       '212', 'Retención IVA – Locaciones y prestaciones de servicios', 0, 0, 0, 0, false],
        ['iibb_arba', '6',   'Retención IIBB ARBA – Régimen general', 0, 0, 0, 0, false],
    ];

    protected $fillable = [
        'empresa_id', 'impuesto', 'codigo', 'descripcion', 'alicuota_inscripto', 'alicuota_no_inscripto',
        'minimo_no_sujeto', 'minimo_retencion', 'acumula_mensual', 'activo',
    ];

    protected $casts = [
        'alicuota_inscripto'    => 'decimal:2',
        'alicuota_no_inscripto' => 'decimal:2',
        'minimo_no_sujeto'      => 'decimal:2',
        'minimo_retencion'      => 'decimal:2',
        'acumula_mensual'       => 'boolean',
        'activo'                => 'boolean',
    ];

    public function retenciones(): HasMany
    {
        return $this->hasMany(Retencion::class, 'regimen_id');
    }

    public function alicuotaPara(string $condicion): float
    {
        return (float) ($condicion === 'no_inscripto' ? $this->alicuota_no_inscripto : $this->alicuota_inscripto);
    }

    public function getNombreAttribute(): string
    {
        return (self::IMPUESTOS[$this->impuesto] ?? $this->impuesto) . " {$this->codigo} – {$this->descripcion}";
    }
}
