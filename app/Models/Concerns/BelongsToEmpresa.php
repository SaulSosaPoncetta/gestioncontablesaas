<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aísla los datos por empresa activa del usuario logueado.
 * Toda tabla con empresa_id debe usar este trait en su modelo.
 */
trait BelongsToEmpresa
{
    protected static function bootBelongsToEmpresa(): void
    {
        static::addGlobalScope('empresa', function (Builder $builder) {
            $empresaId = static::empresaActivaId();
            if ($empresaId) {
                $builder->where($builder->getModel()->getTable() . '.empresa_id', $empresaId);
            }
        });

        static::creating(function ($model) {
            if (empty($model->empresa_id)) {
                $model->empresa_id = static::empresaActivaId();
            }
        });
    }

    protected static function empresaActivaId(): ?int
    {
        return app()->bound('empresa_activa') ? app('empresa_activa')->id : null;
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
