<?php

namespace App\Services;

use App\Models\Comprobante;
use App\Models\CuentaConfig;
use App\Models\Ejercicio;
use App\Models\Integracion;
use App\Models\IntegracionRegistro;
use App\Models\IvaPosicion;
use App\Models\Tercero;
use App\Support\Afip;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Recibe comprobantes desde sistemas externos (GestiónComercial) y los registra
 * con su asiento. Es idempotente: el mismo (tipo_origen, id_externo) actualiza en lugar de duplicar.
 */
class IntegracionComprobanteService
{
    private const PORCENTAJES = ['21' => 5, '10.5' => 4, '27' => 6, '5' => 8, '2.5' => 9, '0' => 3];

    public function __construct(private ComprobanteService $comprobantes)
    {
    }

    /** @return array ['accion', 'comprobante' => Comprobante] */
    public function recibir(Integracion $integracion, array $datos): array
    {
        $d = $this->validar($datos);
        $circuito = $d['circuito'];

        $registro = IntegracionRegistro::firstOrNew([
            'integracion_id' => $integracion->id,
            'tipo_origen'    => $d['tipo_origen'],
            'id_externo'     => (string) $d['id_externo'],
        ]);
        $registro->payload = $datos;

        try {
            $tercero = $this->tercero($d['tercero'], $circuito);
            $existente = $registro->comprobante_id ? Comprobante::find($registro->comprobante_id) : null;

            // Ya cargado por otra vía (a mano o importado de ARCA): se vincula sin duplicar
            if (! $existente) {
                $otro = Comprobante::where('circuito', $circuito)
                    ->where('tipo_comprobante', $d['tipo_comprobante'])
                    ->where('punto_venta', $d['punto_venta'])
                    ->where('numero', $d['numero'])
                    ->where('estado', 'registrado')
                    ->when($circuito === 'compra', fn ($q) => $q->where('tercero_id', $tercero->id))
                    ->first();

                if ($otro) {
                    return $this->cerrarRegistro($registro, 'ok', 'vinculado', $otro, 'El comprobante ya estaba cargado: se vinculó sin duplicarlo.');
                }
            }

            if ($existente && ! $existente->esEditable()) {
                throw ValidationException::withMessages(['comprobante' => 'El comprobante ya está registrado y no se puede modificar (anulado, período IVA cerrado o ejercicio bloqueado).']);
            }

            $this->verificarFecha($d['fecha']);

            $comprobante = $this->comprobantes->guardar(
                $this->datosComprobante($d, $tercero, $integracion, $circuito),
                $circuito,
                $existente
            );

            return $this->cerrarRegistro($registro, 'ok', $existente ? 'actualizado' : 'creado', $comprobante);
        } catch (ValidationException $e) {
            $this->cerrarRegistro($registro, 'error', null, null, collect($e->errors())->flatten()->implode(' '));
            throw $e;
        } catch (\Throwable $e) {
            $this->cerrarRegistro($registro, 'error', null, null, $e->getMessage());
            throw $e;
        }
    }

    public function anular(Integracion $integracion, string $tipoOrigen, string $idExterno, string $motivo): ?Comprobante
    {
        $registro = IntegracionRegistro::where('integracion_id', $integracion->id)
            ->where('tipo_origen', $tipoOrigen)->where('id_externo', $idExterno)->first();

        $comprobante = $registro?->comprobante;
        if (! $comprobante) {
            return null;
        }

        if ($comprobante->estaAnulado()) {
            return $comprobante;
        }
        if (! $comprobante->esEditable()) {
            throw ValidationException::withMessages(['comprobante' => 'El comprobante no se puede anular (período IVA cerrado o ejercicio bloqueado).']);
        }

        $this->comprobantes->anular($comprobante, $motivo);
        $registro->update(['accion' => 'anulado', 'mensaje' => 'Anulado desde el sistema de origen: ' . $motivo]);

        return $comprobante->fresh();
    }

    /* ---------------------------------------------------------------- */

    private function validar(array $datos): array
    {
        $empresa = app('empresa_activa');
        $circuito = $datos['circuito'] ?? null;

        $v = Validator::make($datos, [
            'tipo_origen'             => ['required', Rule::in(array_keys(IntegracionRegistro::TIPOS_ORIGEN))],
            'id_externo'              => ['required', 'max:50'],
            'circuito'                => ['required', Rule::in(['venta', 'compra'])],
            'tipo_comprobante'        => ['required', 'integer', Rule::in(array_keys(Afip::tiposPara($circuito === 'compra' ? 'compra' : 'venta', $empresa->condicion_iva)))],
            'punto_venta'             => ['required', 'integer', 'between:1,99999'],
            'numero'                  => ['required', 'integer', 'between:1,99999999'],
            'fecha'                   => ['required', 'date'],
            'cae'                     => ['nullable', 'string', 'max:20'],
            'vto_cae'                 => ['nullable', 'date'],
            'tercero'                 => ['required', 'array'],
            'tercero.razon_social'    => ['required', 'string', 'max:255'],
            'tercero.tipo_documento'  => ['required', 'integer', Rule::in(array_keys(Afip::TIPOS_DOCUMENTO))],
            'tercero.nro_documento'   => ['nullable', 'string', 'max:20'],
            'tercero.condicion_iva'   => ['nullable', 'integer', Rule::in(array_keys(Afip::CONDICIONES_IVA))],
            'alicuotas'               => ['array'],
            'alicuotas.*.porcentaje'  => ['required', 'numeric'],
            'alicuotas.*.neto'        => ['required', 'numeric'],
            'alicuotas.*.iva'         => ['required', 'numeric'],
            'no_gravado'              => ['nullable', 'numeric', 'min:0'],
            'exento'                  => ['nullable', 'numeric', 'min:0'],
            'percepcion_iva'          => ['nullable', 'numeric', 'min:0'],
            'percepcion_iibb'         => ['nullable', 'numeric', 'min:0'],
            'otros_tributos'          => ['nullable', 'numeric', 'min:0'],
            'total'                   => ['required', 'numeric', 'gt:0'],
            'observaciones'           => ['nullable', 'string', 'max:255'],
        ], [
            'tipo_comprobante.in' => 'El tipo de comprobante no es válido para la condición de IVA de la empresa en el sistema contable.',
        ]);

        $d = $v->validate();

        foreach ($d['alicuotas'] ?? [] as $a) {
            $pct = rtrim(rtrim(number_format((float) $a['porcentaje'], 2, '.', ''), '0'), '.');
            if (! isset(self::PORCENTAJES[$pct])) {
                throw ValidationException::withMessages(['alicuotas' => "Alícuota de IVA {$a['porcentaje']}% no admitida."]);
            }
        }

        return $d;
    }

    private function verificarFecha(string $fecha): void
    {
        $f = Carbon::parse($fecha)->startOfDay();
        $ej = Ejercicio::where('fecha_inicio', '<=', $f)->where('fecha_cierre', '>=', $f)->first();

        if (! $ej) {
            throw ValidationException::withMessages(['fecha' => 'No hay ejercicio contable para el ' . $f->format('d/m/Y') . '.']);
        }
        if (! $ej->admiteFecha($f)) {
            throw ValidationException::withMessages(['fecha' => 'El ejercicio está cerrado o el período bloqueado para el ' . $f->format('d/m/Y') . '.']);
        }
        if (IvaPosicion::estaCerrado($f->format('Y-m'))) {
            throw ValidationException::withMessages(['fecha' => 'El período IVA ' . $f->format('m/Y') . ' ya está cerrado en el sistema contable.']);
        }
    }

    private function datosComprobante(array $d, Tercero $tercero, Integracion $integracion, string $circuito): array
    {
        $alicuotas = array_map(function ($a) {
            $pct = rtrim(rtrim(number_format((float) $a['porcentaje'], 2, '.', ''), '0'), '.');
            return ['alicuota_id' => self::PORCENTAJES[$pct], 'neto' => round((float) $a['neto'], 2), 'iva' => round((float) $a['iva'], 2)];
        }, $d['alicuotas'] ?? []);

        $noGravado = (float) ($d['no_gravado'] ?? 0);

        // Si el comprobante no discrimina IVA en este circuito, todo va a no gravado
        if (! Afip::discriminaIva((int) $d['tipo_comprobante'], $circuito) && $alicuotas) {
            $noGravado += array_sum(array_map(fn ($a) => $a['neto'] + $a['iva'], $alicuotas));
            $alicuotas = [];
        }

        $otros = (float) ($d['otros_tributos'] ?? 0);
        $suma = array_sum(array_map(fn ($a) => $a['neto'] + $a['iva'], $alicuotas))
            + $noGravado + (float) ($d['exento'] ?? 0) + (float) ($d['percepcion_iva'] ?? 0) + (float) ($d['percepcion_iibb'] ?? 0) + $otros;
        $diferencia = round((float) $d['total'] - $suma, 2);

        if ($diferencia < -0.05) {
            throw ValidationException::withMessages(['total' => 'Los importes informados superan el total del comprobante.']);
        }
        if ($diferencia > 0.05) {
            $otros += $diferencia;   // conceptos no detallados por el sistema de origen
        } elseif ($diferencia != 0 && $alicuotas) {
            $alicuotas[0]['neto'] = round($alicuotas[0]['neto'] + $diferencia, 2);   // redondeo
        }

        $cuenta = $tercero->cuenta_imputacion_id
            ?: ($circuito === 'venta' ? $integracion->cuenta_ventas_id : $integracion->cuenta_compras_id)
            ?: CuentaConfig::idPara($circuito === 'venta' ? 'ventas' : 'compras');

        if (! $cuenta) {
            throw ValidationException::withMessages(['cuenta' => 'No hay cuenta de imputación configurada para ' . ($circuito === 'venta' ? 'ventas' : 'compras') . '.']);
        }

        return [
            'tercero_id'           => $tercero->id,
            'tipo_comprobante'     => (int) $d['tipo_comprobante'],
            'punto_venta'          => (int) $d['punto_venta'],
            'numero'               => (int) $d['numero'],
            'fecha'                => Carbon::parse($d['fecha'])->toDateString(),
            'periodo_iva'          => Carbon::parse($d['fecha'])->format('Y-m'),
            'cae'                  => isset($d['cae']) ? (preg_replace('/\D/', '', $d['cae']) ?: null) : null,
            'vto_cae'              => $d['vto_cae'] ?? null,
            'cuenta_imputacion_id' => $cuenta,
            'operacion_iva'        => $circuito === 'compra' && $tercero->operacion_iva ? $tercero->operacion_iva : 1,
            'alicuotas'            => $alicuotas,
            'no_gravado'           => round($noGravado, 2),
            'exento'               => (float) ($d['exento'] ?? 0),
            'percepcion_iva'       => (float) ($d['percepcion_iva'] ?? 0),
            'percepcion_iibb'      => (float) ($d['percepcion_iibb'] ?? 0),
            'otros_tributos'       => round($otros, 2),
            'observaciones'        => mb_substr(trim('Integración ' . ($d['observaciones'] ?? '')), 0, 255),
        ];
    }

    private function tercero(array $t, string $circuito): Tercero
    {
        $rol = $circuito === 'venta' ? 'cliente' : 'proveedor';
        $tipoDoc = (int) $t['tipo_documento'];
        $nro = preg_replace('/\D/', '', (string) ($t['nro_documento'] ?? ''));

        if ($tipoDoc === 99 || $nro === '' || (int) $nro === 0) {
            return Tercero::firstOrCreate(
                ['tipo_documento' => 99, 'razon_social' => mb_substr($t['razon_social'], 0, 255)],
                ['tipo' => $rol, 'condicion_iva' => 5, 'activo' => true]
            );
        }

        $tercero = Tercero::where('tipo_documento', $tipoDoc)->where('nro_documento', $nro)->first();

        if (! $tercero) {
            return Tercero::create([
                'tipo'           => $rol,
                'razon_social'   => mb_substr($t['razon_social'], 0, 255),
                'tipo_documento' => $tipoDoc,
                'nro_documento'  => $nro,
                'condicion_iva'  => $t['condicion_iva'] ?? 5,
                'domicilio'      => isset($t['domicilio']) ? mb_substr($t['domicilio'], 0, 255) : null,
                'email'          => $t['email'] ?? null,
                'telefono'       => isset($t['telefono']) ? mb_substr($t['telefono'], 0, 50) : null,
                'observaciones'  => 'Alta automática desde la integración.',
                'activo'         => true,
            ]);
        }

        if (! in_array($tercero->tipo, [$rol, 'ambos'], true)) {
            $tercero->update(['tipo' => 'ambos']);
        }

        return $tercero;
    }

    private function cerrarRegistro(IntegracionRegistro $r, string $estado, ?string $accion, ?Comprobante $c, ?string $mensaje = null): array
    {
        $r->fill([
            'estado'         => $estado,
            'accion'         => $accion ?? $r->accion,
            'comprobante_id' => $c?->id ?? $r->comprobante_id,
            'mensaje'        => $mensaje ?? ($c ? "{$c->descripcion_corta} · asiento N° {$c->asiento?->numero}" : null),
        ])->save();

        return ['accion' => $accion, 'comprobante' => $c];
    }
}
