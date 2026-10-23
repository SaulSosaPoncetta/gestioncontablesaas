<?php

namespace App\Http\Requests;

use App\Models\Asiento;
use App\Models\Cuenta;
use App\Services\AsientoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AsientoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->puede('operador');
    }

    /** Descarta las líneas vacías antes de validar. */
    protected function prepareForValidation(): void
    {
        $lineas = collect($this->input('lineas', []))
            ->filter(fn ($l) => ! empty($l['cuenta_id'])
                || AsientoService::aCentavos($l['debe'] ?? '') > 0
                || AsientoService::aCentavos($l['haber'] ?? '') > 0)
            ->map(fn ($l) => [
                'cuenta_id' => $l['cuenta_id'] ?? null,
                'debe'      => AsientoService::aCentavos($l['debe'] ?? '') / 100,
                'haber'     => AsientoService::aCentavos($l['haber'] ?? '') / 100,
                'detalle'   => $l['detalle'] ?? null,
            ])
            ->values()
            ->all();

        $datos = ['lineas' => $lineas];

        // Botón "Registrar y cargar otro"
        if ($this->accion === 'registrar_otro') {
            $datos['accion'] = 'registrar';
            $datos['otro'] = 1;
        }

        $this->merge($datos);
    }

    public function rules(): array
    {
        $empresaId = app('empresa_activa')->id;

        return [
            'fecha'             => ['required', 'date'],
            'tipo'              => ['required', Rule::in(array_keys(Asiento::TIPOS))],
            'concepto'          => ['required', 'string', 'max:255'],
            'accion'            => ['required', Rule::in(['borrador', 'registrar'])],
            'lineas'            => ['required', 'array', 'min:2', 'max:500'],
            'lineas.*.cuenta_id'=> ['required', Rule::exists('cuentas', 'id')->where('empresa_id', $empresaId)],
            'lineas.*.debe'     => ['numeric', 'min:0', 'max:9999999999999'],
            'lineas.*.haber'    => ['numeric', 'min:0', 'max:9999999999999'],
            'lineas.*.detalle'  => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $v) {
                if ($v->errors()->isNotEmpty()) {
                    return;
                }

                $asiento = $this->route('asiento');
                $ejercicio = $asiento?->ejercicio ?? (app()->bound('ejercicio_activo') ? app('ejercicio_activo') : null);

                if (! $ejercicio) {
                    $v->errors()->add('fecha', 'No hay un ejercicio seleccionado. Cree o seleccione un ejercicio.');
                    return;
                }

                // Fecha dentro del ejercicio, abierto y fuera del período bloqueado
                if (! $ejercicio->admiteFecha($this->fecha)) {
                    $motivo = ! $ejercicio->estaAbierto()
                        ? "el ejercicio N° {$ejercicio->numero} está cerrado"
                        : ($ejercicio->bloqueado_hasta && $this->date('fecha')->lte($ejercicio->bloqueado_hasta)
                            ? 'el período está bloqueado hasta el ' . $ejercicio->bloqueado_hasta->format('d/m/Y')
                            : 'debe estar entre el ' . $ejercicio->fecha_inicio->format('d/m/Y') . ' y el ' . $ejercicio->fecha_cierre->format('d/m/Y'));
                    $v->errors()->add('fecha', "Fecha no válida: {$motivo}.");
                }

                // Cuentas imputables y activas
                $cuentas = Cuenta::whereIn('id', collect($this->lineas)->pluck('cuenta_id'))->get()->keyBy('id');

                $totalDebe = 0;
                $totalHaber = 0;

                foreach ($this->lineas as $i => $l) {
                    $n = $i + 1;
                    $cuenta = $cuentas->get((int) $l['cuenta_id']);

                    if ($cuenta && ! $cuenta->imputable) {
                        $v->errors()->add("lineas.{$i}.cuenta_id", "Línea {$n}: la cuenta {$cuenta->codigo_nombre} no es imputable.");
                    }
                    if ($cuenta && ! $cuenta->activa) {
                        $v->errors()->add("lineas.{$i}.cuenta_id", "Línea {$n}: la cuenta {$cuenta->codigo_nombre} está inactiva.");
                    }

                    $debe = AsientoService::aCentavos($l['debe']);
                    $haber = AsientoService::aCentavos($l['haber']);

                    if ($debe > 0 && $haber > 0) {
                        $v->errors()->add("lineas.{$i}.debe", "Línea {$n}: cargue el importe en el Debe o en el Haber, no en ambos.");
                    }
                    if ($debe === 0 && $haber === 0) {
                        $v->errors()->add("lineas.{$i}.debe", "Línea {$n}: falta el importe.");
                    }

                    $totalDebe += $debe;
                    $totalHaber += $haber;
                }

                $registra = $this->accion === 'registrar' || $asiento?->estado === 'registrado';

                if ($registra && $totalDebe !== $totalHaber) {
                    $dif = number_format(abs($totalDebe - $totalHaber) / 100, 2, ',', '.');
                    $v->errors()->add('lineas', "El asiento no balancea: diferencia de $ {$dif} entre Debe y Haber. Puede guardarlo como borrador.");
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'lineas'              => 'líneas',
            'lineas.*.cuenta_id'  => 'cuenta',
            'lineas.*.debe'       => 'debe',
            'lineas.*.haber'      => 'haber',
        ];
    }

    public function messages(): array
    {
        return [
            'lineas.min'                 => 'El asiento debe tener al menos dos líneas.',
            'lineas.*.cuenta_id.required'=> 'Hay líneas con importe pero sin cuenta.',
        ];
    }
}
