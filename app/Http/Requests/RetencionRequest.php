<?php

namespace App\Http\Requests;

use App\Models\Comprobante;
use App\Models\Ejercicio;
use App\Models\Retencion;
use App\Models\Tercero;
use App\Services\AsientoService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RetencionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->puede('operador');
    }

    protected function prepareForValidation(): void
    {
        $datos = [];
        foreach (['importe_comprobante', 'base_imponible', 'importe'] as $campo) {
            $datos[$campo] = AsientoService::aCentavos($this->input($campo, '')) / 100;
        }
        $datos['alicuota'] = AsientoService::aCentavos($this->input('alicuota', '')) / 100;
        $datos['importe_manual'] = $this->boolean('importe_manual');

        $this->merge($datos);
    }

    public function rules(): array
    {
        $empresaId = app('empresa_activa')->id;
        $practicada = $this->route('tipo') === 'practicada';

        return [
            'impuesto'            => [$practicada ? 'nullable' : 'required', Rule::in(array_keys(Retencion::IMPUESTOS))],
            'regimen_id'          => [$practicada ? 'required' : 'nullable', Rule::exists('regimenes_retencion', 'id')->where('empresa_id', $empresaId)],
            'tercero_id'          => ['required', Rule::exists('terceros', 'id')->where('empresa_id', $empresaId)],
            'comprobante_id'      => ['nullable', Rule::exists('comprobantes', 'id')->where('empresa_id', $empresaId)],
            'fecha'               => ['required', 'date'],
            'condicion'           => ['required', Rule::in(['inscripto', 'no_inscripto'])],
            'numero_certificado'  => [$practicada ? 'nullable' : 'required', 'string', 'max:30'],
            'importe_comprobante' => ['numeric', 'min:0'],
            'base_imponible'      => [$practicada ? 'required' : 'nullable', 'numeric', $practicada ? 'gt:0' : 'min:0'],
            'alicuota'            => ['numeric', 'min:0', 'max:100'],
            'importe'             => ['numeric', $practicada ? 'min:0' : 'gt:0'],
            'importe_manual'      => ['boolean'],
            'observaciones'       => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $v) {
                if ($v->errors()->isNotEmpty()) {
                    return;
                }

                $tipo = $this->route('tipo');
                $actual = $this->route('retencion');

                $tercero = Tercero::find($this->tercero_id);
                $rol = $tipo === 'practicada' ? 'proveedor' : 'cliente';
                if (! in_array($tercero->tipo, [$rol, 'ambos'], true)) {
                    $v->errors()->add('tercero_id', $tipo === 'practicada'
                        ? 'Las retenciones practicadas se hacen a proveedores.'
                        : 'Las retenciones sufridas las practica un cliente.');
                }

                if ($this->comprobante_id) {
                    $comp = Comprobante::find($this->comprobante_id);
                    if ($comp->tercero_id !== (int) $this->tercero_id) {
                        $v->errors()->add('comprobante_id', 'El comprobante no corresponde al ' . $rol . ' elegido.');
                    }
                }

                $fecha = Carbon::parse($this->fecha);
                $ejercicio = Ejercicio::where('fecha_inicio', '<=', $fecha)->where('fecha_cierre', '>=', $fecha)->first();
                if (! $ejercicio || ! $ejercicio->admiteFecha($fecha)) {
                    $v->errors()->add('fecha', 'No hay un ejercicio abierto para esa fecha (o el período está bloqueado).');
                } elseif ($actual && $actual->ejercicio_id !== $ejercicio->id) {
                    $v->errors()->add('fecha', 'No se puede mover la retención a otro ejercicio.');
                }

                if ($tipo === 'sufrida' && $this->impuesto === 'iva' && \App\Models\IvaPosicion::estaCerrado($fecha->format('Y-m'))) {
                    $v->errors()->add('fecha', 'La posición de IVA de ese mes ya está cerrada: reábrala para cargar retenciones de IVA.');
                }

                if ($tipo === 'sufrida' && $this->numero_certificado) {
                    $dup = Retencion::where('tipo', 'sufrida')
                        ->where('tercero_id', $this->tercero_id)
                        ->where('impuesto', $this->impuesto)
                        ->where('numero_certificado', $this->numero_certificado)
                        ->where('estado', 'registrado')
                        ->when($actual, fn ($q) => $q->whereKeyNot($actual->id))
                        ->exists();
                    if ($dup) {
                        $v->errors()->add('numero_certificado', 'Ese certificado ya está cargado.');
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'regimen_id'          => 'régimen',
            'tercero_id'          => $this->route('tipo') === 'practicada' ? 'proveedor' : 'cliente',
            'comprobante_id'      => 'comprobante',
            'numero_certificado'  => 'número de certificado',
            'importe_comprobante' => 'importe del comprobante',
            'base_imponible'      => 'base imponible',
            'alicuota'            => 'alícuota',
        ];
    }
}
