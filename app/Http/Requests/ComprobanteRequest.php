<?php

namespace App\Http\Requests;

use App\Models\Comprobante;
use App\Models\Ejercicio;
use App\Models\Tercero;
use App\Services\AsientoService;
use App\Support\Afip;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ComprobanteRequest extends FormRequest
{
    private const IMPORTES = ['no_gravado', 'exento', 'percepcion_iva', 'percepcion_iibb', 'otros_tributos'];

    public function authorize(): bool
    {
        return $this->user()->puede('operador');
    }

    protected function prepareForValidation(): void
    {
        $datos = [];
        foreach (self::IMPORTES as $campo) {
            $datos[$campo] = AsientoService::aCentavos($this->input($campo, '')) / 100;
        }

        $datos['alicuotas'] = collect($this->input('alicuotas', []))
            ->map(fn ($a) => [
                'alicuota_id' => $a['alicuota_id'] ?? null,
                'neto'        => AsientoService::aCentavos($a['neto'] ?? '') / 100,
                'iva'         => AsientoService::aCentavos($a['iva'] ?? '') / 100,
            ])
            ->filter(fn ($a) => $a['neto'] != 0 || $a['iva'] != 0)
            ->values()
            ->all();

        $datos['cae'] = preg_replace('/\D/', '', (string) $this->cae) ?: null;

        $this->merge($datos);
    }

    public function rules(): array
    {
        $empresa = app('empresa_activa');
        $circuito = $this->route('circuito');

        return [
            'tercero_id'           => ['required', Rule::exists('terceros', 'id')->where('empresa_id', $empresa->id)],
            'tipo_comprobante'     => ['required', 'integer', Rule::in(array_keys(Afip::tiposPara($circuito, $empresa->condicion_iva)))],
            'punto_venta'          => ['required', 'integer', 'between:1,99999'],
            'numero'               => ['required', 'integer', 'between:1,99999999'],
            'fecha'                => ['required', 'date'],
            'periodo_iva'          => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'cae'                  => ['nullable', 'digits:14'],
            'vto_cae'              => ['nullable', 'date'],
            'cuenta_imputacion_id' => ['required', Rule::exists('cuentas', 'id')->where('empresa_id', $empresa->id)->where('imputable', true)->where('activa', true)],
            'operacion_iva'        => ['required', 'integer', $circuito === 'venta' ? 'between:1,2' : 'between:1,4'],
            'no_gravado'           => ['numeric', 'min:0'],
            'exento'               => ['numeric', 'min:0'],
            'percepcion_iva'       => ['numeric', 'min:0'],
            'percepcion_iibb'      => ['numeric', 'min:0'],
            'otros_tributos'       => ['numeric', 'min:0'],
            'alicuotas'            => ['array', 'max:6'],
            'alicuotas.*.alicuota_id' => ['required', Rule::in(array_keys(Afip::ALICUOTAS))],
            'alicuotas.*.neto'     => ['numeric', 'min:0'],
            'alicuotas.*.iva'      => ['numeric', 'min:0'],
            'observaciones'        => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $v) {
                if ($v->errors()->isNotEmpty()) {
                    return;
                }

                $circuito = $this->route('circuito');
                $actual = $this->route('comprobante');
                $tipo = (int) $this->tipo_comprobante;

                // Tercero del circuito
                $tercero = Tercero::find($this->tercero_id);
                if (! in_array($tercero->tipo, [$circuito === 'venta' ? 'cliente' : 'proveedor', 'ambos'], true)) {
                    $v->errors()->add('tercero_id', $circuito === 'venta' ? 'El tercero no está dado de alta como cliente.' : 'El tercero no está dado de alta como proveedor.');
                }

                // Ejercicio de la fecha: abierto y no bloqueado
                $fecha = Carbon::parse($this->fecha);
                $ejercicio = Ejercicio::where('fecha_inicio', '<=', $fecha)->where('fecha_cierre', '>=', $fecha)->first();
                if (! $ejercicio) {
                    $v->errors()->add('fecha', 'No hay un ejercicio contable que incluya esa fecha.');
                } elseif (! $ejercicio->admiteFecha($fecha)) {
                    $v->errors()->add('fecha', "El ejercicio N° {$ejercicio->numero} está cerrado o el período está bloqueado para esa fecha.");
                } elseif ($actual && $actual->ejercicio_id !== $ejercicio->id) {
                    $v->errors()->add('fecha', 'No se puede mover el comprobante a otro ejercicio. Anúlelo y cárguelo nuevamente.');
                }

                // Período IVA cerrado (el nuevo y, al editar, el original)
                if (\App\Models\IvaPosicion::estaCerrado($this->periodo_iva)) {
                    $v->errors()->add('periodo_iva', 'El período IVA ' . $this->periodo_iva . ' ya está cerrado. Reábralo desde la posición de IVA o use otro período.');
                }
                if ($actual && $actual->periodo_iva !== $this->periodo_iva && \App\Models\IvaPosicion::estaCerrado($actual->periodo_iva)) {
                    $v->errors()->add('periodo_iva', 'El comprobante pertenece a un período IVA cerrado.');
                }

                // IVA
                $discrimina = Afip::discriminaIva($tipo, $circuito);
                if (! $discrimina && ! empty($this->alicuotas)) {
                    $v->errors()->add('alicuotas', 'Este tipo de comprobante no discrimina IVA: cargue el importe en «No gravado / sin IVA discriminado».');
                }
                foreach ($this->alicuotas as $i => $a) {
                    $esperado = round($a['neto'] * (Afip::ALICUOTAS[$a['alicuota_id']] ?? 0) / 100, 2);
                    if (abs($esperado - $a['iva']) > 1) {
                        $pct = Afip::ALICUOTAS[$a['alicuota_id']];
                        $v->errors()->add("alicuotas.{$i}.iva", "El IVA al {$pct}% sobre $ " . number_format($a['neto'], 2, ',', '.') . ' debería ser $ ' . number_format($esperado, 2, ',', '.') . '.');
                    }
                }

                $total = collect($this->alicuotas)->sum(fn ($a) => $a['neto'] + $a['iva']);
                foreach (self::IMPORTES as $campo) {
                    $total += (float) $this->input($campo);
                }
                if ($total <= 0) {
                    $v->errors()->add('total', 'El comprobante no tiene importes.');
                }

                // Numeración duplicada (en compras, por proveedor)
                $duplicado = Comprobante::where('circuito', $circuito)
                    ->where('tipo_comprobante', $tipo)
                    ->where('punto_venta', $this->punto_venta)
                    ->where('numero', $this->numero)
                    ->where('estado', 'registrado')
                    ->when($circuito === 'compra', fn ($q) => $q->where('tercero_id', $this->tercero_id))
                    ->when($actual, fn ($q) => $q->whereKeyNot($actual->id))
                    ->exists();
                if ($duplicado) {
                    $v->errors()->add('numero', 'Ya existe un comprobante registrado con ese tipo, punto de venta y número.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'tercero_id'           => $this->route('circuito') === 'venta' ? 'cliente' : 'proveedor',
            'tipo_comprobante'     => 'tipo de comprobante',
            'punto_venta'          => 'punto de venta',
            'numero'               => 'número',
            'periodo_iva'          => 'período IVA',
            'cuenta_imputacion_id' => 'cuenta de imputación',
            'alicuotas.*.neto'     => 'neto gravado',
            'alicuotas.*.iva'      => 'IVA',
        ];
    }
}
