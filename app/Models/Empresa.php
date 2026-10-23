<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use SoftDeletes;

    public const CONDICIONES_IVA = [
        'responsable_inscripto' => 'Responsable Inscripto',
        'monotributo'           => 'Monotributo',
        'exento'                => 'Exento',
        'no_alcanzado'          => 'No alcanzado',
    ];

    public const CONDICIONES_IIBB = [
        'local'                 => 'Contribuyente local',
        'convenio_multilateral' => 'Convenio Multilateral',
        'exento'                => 'Exento',
        'no_inscripto'          => 'No inscripto',
    ];

    public const ESTADOS_SUSCRIPCION = [
        'prueba'     => 'Prueba gratis',
        'activa'     => 'Activa',
        'suspendida' => 'Suspendida',
        'cancelada'  => 'Cancelada',
    ];

    protected $fillable = [
        'razon_social', 'nombre_fantasia', 'cuit', 'condicion_iva', 'condicion_iibb',
        'nro_iibb', 'actividad_afip', 'inicio_actividades', 'domicilio', 'localidad', 'provincia',
        'codigo_postal', 'email', 'telefono', 'mes_cierre',
        'agente_retencion_iva', 'agente_retencion_ganancias', 'agente_recaudacion_iibb',
        'activa',
        'plan_id', 'estado_suscripcion', 'prueba_hasta', 'vence_el', 'notas_admin',
    ];

    protected $casts = [
        'inicio_actividades'         => 'date',
        'prueba_hasta'               => 'date',
        'vence_el'                   => 'date',
        'agente_retencion_iva'       => 'boolean',
        'agente_retencion_ganancias' => 'boolean',
        'agente_recaudacion_iibb'    => 'boolean',
        'activa'                     => 'boolean',
    ];

    /* ----------------------------------------------------------------
     | Relaciones
     * ---------------------------------------------------------------- */

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class)->orderByDesc('fecha');
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('rol')->withTimestamps();
    }

    public function ejercicios(): HasMany
    {
        return $this->hasMany(Ejercicio::class)->withoutGlobalScope('empresa');
    }

    public function cuentas(): HasMany
    {
        return $this->hasMany(Cuenta::class)->withoutGlobalScope('empresa');
    }

    /* ----------------------------------------------------------------
     | Suscripción
     * ---------------------------------------------------------------- */

    /** Fecha hasta la que tiene servicio (fin de prueba o vencimiento del abono). */
    public function getFechaVencimientoAttribute(): ?Carbon
    {
        return $this->estado_suscripcion === 'prueba' ? $this->prueba_hasta : $this->vence_el;
    }

    /** Días que faltan para vencer (negativo si ya venció, null si no tiene vencimiento). */
    public function diasParaVencer(): ?int
    {
        $vto = $this->fecha_vencimiento;
        return $vto ? (int) today()->diffInDays($vto, false) : null;
    }

    /**
     * Estado de acceso al sistema:
     *  ok           → acceso completo
     *  gracia       → vencida hace pocos días, acceso completo con aviso
     *  solo_lectura → vencida fuera del período de gracia
     *  bloqueada    → suspendida o cancelada
     */
    public function estadoAcceso(): string
    {
        if (in_array($this->estado_suscripcion, ['suspendida', 'cancelada'], true) || ! $this->activa) {
            return 'bloqueada';
        }

        $dias = $this->diasParaVencer();

        if ($dias === null || $dias >= 0) {
            return 'ok';
        }

        return abs($dias) <= (int) config('saas.dias_gracia') ? 'gracia' : 'solo_lectura';
    }

    public function tieneModulo(string $modulo): bool
    {
        return $this->plan?->tieneModulo($modulo) ?? false;
    }

    public function puedeAgregarUsuario(): bool
    {
        $max = $this->plan?->max_usuarios;
        return $max === null || $this->usuarios()->count() < $max;
    }

    /* ----------------------------------------------------------------
     | CUIT
     * ---------------------------------------------------------------- */

    public function getCuitFormateadoAttribute(): string
    {
        $c = $this->cuit;
        return strlen((string) $c) === 11 ? substr($c, 0, 2) . '-' . substr($c, 2, 8) . '-' . substr($c, 10) : (string) $c;
    }

    /** Valida CUIT con dígito verificador (algoritmo módulo 11 de AFIP/ARCA). */
    public static function cuitValido(string $cuit): bool
    {
        $cuit = preg_replace('/\D/', '', $cuit);
        if (strlen($cuit) !== 11) {
            return false;
        }
        $mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += (int) $cuit[$i] * $mult[$i];
        }
        $dv = 11 - ($suma % 11);
        $dv = $dv === 11 ? 0 : ($dv === 10 ? 9 : $dv);
        return $dv === (int) $cuit[10];
    }
}
