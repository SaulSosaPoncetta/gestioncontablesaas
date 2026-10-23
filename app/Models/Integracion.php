<?php

namespace App\Models;

use App\Models\Concerns\BelongsToEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Integracion extends Model
{
    use BelongsToEmpresa;

    protected $table = 'integraciones';

    public const SISTEMAS = [
        'gestion_comercial' => 'GestiónComercial (MiGestión)',
        'otro'              => 'Otro sistema (API)',
    ];

    protected $fillable = [
        'empresa_id', 'sistema', 'nombre', 'token_hash', 'token_prefijo', 'activa',
        'cuenta_ventas_id', 'cuenta_compras_id', 'ultimo_uso_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'activa'        => 'boolean',
        'ultimo_uso_at' => 'datetime',
    ];

    public function registros(): HasMany
    {
        return $this->hasMany(IntegracionRegistro::class);
    }

    public function cuentaVentas(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_ventas_id');
    }

    public function cuentaCompras(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_compras_id');
    }

    /** Genera un token nuevo, guarda su hash y devuelve el token en claro (se muestra una sola vez). */
    public function generarToken(): string
    {
        $token = 'mgc_' . Str::random(48);
        $this->token_hash = hash('sha256', $token);
        $this->token_prefijo = substr($token, 0, 10);

        return $token;
    }

    /** Busca la integración por token (sin el scope de empresa: todavía no se conoce). */
    public static function porToken(string $token): ?self
    {
        return static::withoutGlobalScope('empresa')->where('token_hash', hash('sha256', $token))->first();
    }
}
