<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Índice de precios mensual (serie FACPCE) para el ajuste por inflación. Común a todas las empresas.
 */
class IndiceInflacion extends Model
{
    protected $table = 'indices_inflacion';

    protected $fillable = ['periodo', 'indice', 'fuente'];

    protected $casts = [
        'periodo' => 'date',
        'indice'  => 'decimal:6',
    ];

    /** Índices entre dos meses (inclusive) como ['2026-01' => 1234.5678, ...]. */
    public static function mapa(Carbon $desde, Carbon $hasta): array
    {
        return static::whereBetween('periodo', [$desde->copy()->startOfMonth()->toDateString(), $hasta->copy()->startOfMonth()->toDateString()])
            ->orderBy('periodo')
            ->get()
            ->mapWithKeys(fn ($i) => [$i->periodo->format('Y-m') => (float) $i->indice])
            ->all();
    }

    /** Meses (Y-m) entre dos fechas, inclusive. */
    public static function meses(Carbon $desde, Carbon $hasta): array
    {
        $meses = [];
        for ($m = $desde->copy()->startOfMonth(); $m->lte($hasta); $m->addMonth()) {
            $meses[] = $m->format('Y-m');
        }

        return $meses;
    }
}
