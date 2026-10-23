<?php

namespace App\Http\Requests;

use App\Models\Empresa;
use App\Rules\Cuit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos de "Mi empresa". El CUIT solo lo puede cambiar el superadmin.
 */
class EmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->puede('administrador');
    }

    protected function prepareForValidation(): void
    {
        $datos = [
            'agente_retencion_iva'       => $this->boolean('agente_retencion_iva'),
            'agente_retencion_ganancias' => $this->boolean('agente_retencion_ganancias'),
            'agente_recaudacion_iibb'    => $this->boolean('agente_recaudacion_iibb'),
        ];

        if ($this->has('cuit')) {
            $datos['cuit'] = preg_replace('/\D/', '', (string) $this->cuit);
        }

        $this->merge($datos);
    }

    public function rules(): array
    {
        $empresa = app('empresa_activa');

        $rules = [
            'razon_social'       => ['required', 'string', 'max:255'],
            'nombre_fantasia'    => ['nullable', 'string', 'max:255'],
            'condicion_iva'      => ['required', Rule::in(array_keys(Empresa::CONDICIONES_IVA))],
            'condicion_iibb'     => ['required', Rule::in(array_keys(Empresa::CONDICIONES_IIBB))],
            'nro_iibb'           => ['nullable', 'string', 'max:20'],
            'actividad_afip'     => ['nullable', 'digits:6'],
            'inicio_actividades' => ['nullable', 'date'],
            'domicilio'          => ['nullable', 'string', 'max:255'],
            'localidad'          => ['nullable', 'string', 'max:100'],
            'provincia'          => ['nullable', 'string', 'max:60'],
            'codigo_postal'      => ['nullable', 'string', 'max:10'],
            'email'              => ['nullable', 'email', 'max:255'],
            'telefono'           => ['nullable', 'string', 'max:50'],
            'mes_cierre'         => ['required', 'integer', 'between:1,12'],
            'agente_retencion_iva'       => ['boolean'],
            'agente_retencion_ganancias' => ['boolean'],
            'agente_recaudacion_iibb'    => ['boolean'],
        ];

        if ($this->user()->es_superadmin) {
            $rules['cuit'] = ['required', 'digits:11', new Cuit, Rule::unique('empresas', 'cuit')->ignore($empresa->id)];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'razon_social'   => 'razón social',
            'cuit'           => 'CUIT',
            'condicion_iva'  => 'condición frente al IVA',
            'condicion_iibb' => 'condición frente a IIBB',
            'mes_cierre'     => 'mes de cierre',
        ];
    }
}
