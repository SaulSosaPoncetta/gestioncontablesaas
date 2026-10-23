<?php

namespace App\Http\Requests;

use App\Models\Cuenta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CuentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->puede('contador');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo'    => trim((string) $this->codigo),
            'imputable' => $this->boolean('imputable'),
            'monetaria' => $this->boolean('monetaria'),
            'activa'    => $this->has('activa') ? $this->boolean('activa') : true,
        ]);
    }

    public function rules(): array
    {
        $cuenta = $this->route('cuenta');
        $empresaId = app('empresa_activa')->id;

        return [
            'padre_id'       => ['nullable', Rule::exists('cuentas', 'id')->where('empresa_id', $empresaId)],
            'codigo'         => [
                'required', 'string', 'max:30', 'regex:' . Cuenta::REGEX_CODIGO,
                Rule::unique('cuentas', 'codigo')->where('empresa_id', $empresaId)->ignore($cuenta?->id),
            ],
            'nombre'         => ['required', 'string', 'max:255'],
            'imputable'      => ['boolean'],
            'saldo_habitual' => ['required', Rule::in(['deudor', 'acreedor'])],
            'monetaria'      => ['boolean'],
            'activa'         => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $v) {
                if ($v->errors()->isNotEmpty()) {
                    return;
                }

                $cuenta = $this->route('cuenta');
                $padre = $this->padre_id ? Cuenta::find($this->padre_id) : null;
                $partes = explode('.', $this->codigo);

                // Coherencia código / cuenta padre
                if ($padre) {
                    if ($cuenta && ($padre->id === $cuenta->id || str_starts_with($padre->codigo . '.', $cuenta->codigo . '.'))) {
                        $v->errors()->add('padre_id', 'Una cuenta no puede depender de sí misma ni de una subcuenta propia.');
                        return;
                    }
                    if ($padre->imputable) {
                        $v->errors()->add('padre_id', "La cuenta {$padre->codigo_nombre} es imputable y no puede tener subcuentas.");
                    }
                    $esperado = $padre->codigo . '.';
                    if (! str_starts_with($this->codigo, $esperado) || count($partes) !== substr_count($padre->codigo, '.') + 2) {
                        $v->errors()->add('codigo', "El código debe tener la forma {$esperado}XX (un nivel debajo de la cuenta padre).");
                    }
                } elseif (count($partes) !== 1) {
                    $v->errors()->add('padre_id', 'Debe indicar la cuenta padre (solo los rubros principales 1 a 9 van sin padre).');
                }

                if (! $cuenta) {
                    return;
                }

                // Restricciones al modificar una cuenta existente
                $tieneHijas = $cuenta->tieneHijas();
                $tieneMovimientos = $cuenta->tieneMovimientos();

                if ($this->codigo !== $cuenta->codigo || (int) $this->padre_id !== (int) $cuenta->padre_id) {
                    if ($tieneHijas) {
                        $v->errors()->add('codigo', 'No se puede cambiar el código de una cuenta que tiene subcuentas.');
                    }
                    if ($tieneMovimientos) {
                        $v->errors()->add('codigo', 'No se puede cambiar el código de una cuenta con movimientos.');
                    }
                }
                if ($this->imputable && $tieneHijas) {
                    $v->errors()->add('imputable', 'Una cuenta con subcuentas no puede ser imputable.');
                }
                if (! $this->imputable && $cuenta->imputable && $tieneMovimientos) {
                    $v->errors()->add('imputable', 'La cuenta tiene movimientos: no puede dejar de ser imputable.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'padre_id'       => 'cuenta padre',
            'codigo'         => 'código',
            'saldo_habitual' => 'saldo habitual',
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.regex' => 'El código debe ser numérico con niveles separados por punto (ej: 1.1.01.001).',
        ];
    }
}
