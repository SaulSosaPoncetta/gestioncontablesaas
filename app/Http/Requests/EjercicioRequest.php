<?php

namespace App\Http\Requests;

use App\Models\Ejercicio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class EjercicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->puede('contador');
    }

    public function rules(): array
    {
        return [
            'descripcion'     => ['nullable', 'string', 'max:255'],
            'fecha_inicio'    => ['required', 'date'],
            'fecha_cierre'    => ['required', 'date', 'after:fecha_inicio'],
            'bloqueado_hasta' => ['nullable', 'date', 'after_or_equal:fecha_inicio', 'before_or_equal:fecha_cierre'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $actual = $this->route('ejercicio');

                $superpuesto = Ejercicio::query()
                    ->when($actual, fn ($q) => $q->whereKeyNot($actual->id))
                    ->where('fecha_inicio', '<=', $this->fecha_cierre)
                    ->where('fecha_cierre', '>=', $this->fecha_inicio)
                    ->exists();

                if ($superpuesto) {
                    $validator->errors()->add('fecha_inicio', 'Las fechas se superponen con otro ejercicio de la empresa.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'fecha_inicio'    => 'fecha de inicio',
            'fecha_cierre'    => 'fecha de cierre',
            'bloqueado_hasta' => 'bloqueado hasta',
        ];
    }
}
