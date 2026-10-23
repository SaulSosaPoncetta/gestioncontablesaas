<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = [
        'administrador' => 'Administrador',
        'contador'      => 'Contador',
        'operador'      => 'Operador',
        'consulta'      => 'Solo consulta',
    ];

    protected $fillable = [
        'name', 'email', 'password', 'es_superadmin', 'empresa_actual_id', 'activo',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'es_superadmin'     => 'boolean',
            'activo'            => 'boolean',
        ];
    }

    /** Un usuario cliente pertenece a una sola empresa (se usa la tabla pivote por el rol). */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class)->withPivot('rol')->withTimestamps();
    }

    /** Solo superadmin: empresa en la que está entrando como soporte. */
    public function empresaActual(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_actual_id');
    }

    public function tieneAccesoA(Empresa $empresa): bool
    {
        return $this->es_superadmin || $this->empresas()->whereKey($empresa->id)->exists();
    }

    public function rolEn(?Empresa $empresa): ?string
    {
        if (! $empresa) {
            return null;
        }
        if ($this->es_superadmin) {
            return 'administrador';
        }
        return $this->empresas()->whereKey($empresa->id)->first()?->pivot->rol;
    }

    /** Roles: administrador > contador > operador > consulta */
    public function puede(string $rolMinimo, ?Empresa $empresa = null): bool
    {
        $orden = ['consulta' => 1, 'operador' => 2, 'contador' => 3, 'administrador' => 4];
        $empresa ??= app()->bound('empresa_activa') ? app('empresa_activa') : null;
        $rol = $this->rolEn($empresa);
        return $rol && $orden[$rol] >= $orden[$rolMinimo];
    }
}
