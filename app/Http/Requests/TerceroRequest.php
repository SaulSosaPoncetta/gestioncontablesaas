<?php

namespace App\Http\Requests;

use App\Models\Empresa;
use App\Models\Tercero;
use App\Support\Afip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TerceroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->puede('operador');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nro_documento' => preg_replace('/\D/', '', (string) $this->nro_documento) ?: null,
            'activo'        => $this->has('activo') ? $this->boolean('activo') : true,
        ]);
    }

    public function rules(): array
    {
        $empresaId = app('empresa_activa')->id;
        $cuentaImputable = Rule::exists('cuentas', 'id')->where('empresa_id', $empresaId)->where('imputable', true);

        return [
            'tipo'                 => ['required', Rule::in(array_keys(Tercero::TIPOS))],
            'razon_social'         => ['required', 'string', 'max:255'],
            'tipo_documento'       => ['required', Rule::in(array_keys(Afip::TIPOS_DOCUMENTO))],
            'nro_documento'        => ['nullable', 'string', 'max:20', Rule::requiredIf(fn () => (int) $this->tipo_documento !== 99)],
            'condicion_iva'        => ['required', Rule::in(array_keys(Afip::CONDICIONES_IVA))],
            'domicilio'            => ['nullable', 'string', 'max:255'],
            'localidad'            => ['nullable', 'string', 'max:100'],
            'provincia'            => ['nullable', 'string', 'max:60'],
            'email'                => ['nullable', 'email', 'max:255'],
            'telefono'             => ['nullable', 'string', 'max:50'],
            'cuenta_id'            => ['nullable', $cuentaImputable],
            'cuenta_imputacion_id' => ['nullable', $cuentaImputable],
            'operacion_iva'        => ['nullable', 'integer', 'between:1,4'],
            'observaciones'        => ['nullable', 'string', 'max:2000'],
            'activo'               => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $v) {
                if ($v->errors()->isNotEmpty() || ! $this->nro_documento) {
                    return;
                }

                if (in_array((int) $this->tipo_documento, [80, 86], true) && ! Empresa::cuitValido($this->nro_documento)) {
                    $v->errors()->add('nro_documento', 'El CUIT/CUIL no es válido (verifique el dígito verificador).');
                    return;
                }

                $existe = Tercero::where('tipo_documento', $this->tipo_documento)
                    ->where('nro_documento', $this->nro_documento)
                    ->when($this->route('tercero'), fn ($q, $t) => $q->whereKeyNot($t->id))
                    ->first();

                if ($existe) {
                    $v->errors()->add('nro_documento', "Ya existe {$existe->razon_social} con ese documento. Si opera como cliente y proveedor, edítelo y elija «Cliente y proveedor».");
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'razon_social'         => 'razón social',
            'tipo_documento'       => 'tipo de documento',
            'nro_documento'        => 'número de documento',
            'condicion_iva'        => 'condición frente al IVA',
            'cuenta_id'            => 'cuenta corriente',
            'cuenta_imputacion_id' => 'cuenta de imputación',
        ];
    }
}
