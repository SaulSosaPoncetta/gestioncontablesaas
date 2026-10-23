<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Registro manual de pagos de abono. Cada pago extiende el vencimiento
 * y deja la suscripción en estado "activa".
 */
class PagoController extends Controller
{
    public function store(Request $request, Empresa $empresa)
    {
        $datos = $request->validate([
            'fecha'         => ['required', 'date'],
            'meses'         => ['required', 'integer', 'between:1,24'],
            'importe'       => ['required', 'numeric', 'min:0'],
            'medio'         => ['required', Rule::in(array_keys(Pago::MEDIOS))],
            'referencia'    => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($datos, $empresa, $request) {
            // El período arranca al día siguiente del vencimiento vigente, o hoy si ya estaba vencido / en prueba
            $vtoActual = $empresa->estado_suscripcion === 'activa' ? $empresa->vence_el : null;
            $desde = $vtoActual && $vtoActual->gte(today()) ? $vtoActual->copy()->addDay() : today();
            $hasta = $desde->copy()->addMonthsNoOverflow((int) $datos['meses'])->subDay();

            Pago::create([
                'empresa_id'     => $empresa->id,
                'plan_id'        => $empresa->plan_id,
                'fecha'          => $datos['fecha'],
                'periodo_desde'  => $desde,
                'periodo_hasta'  => $hasta,
                'importe'        => $datos['importe'],
                'medio'          => $datos['medio'],
                'referencia'     => $datos['referencia'] ?? null,
                'observaciones'  => $datos['observaciones'] ?? null,
                'registrado_por' => $request->user()->id,
            ]);

            $empresa->update([
                'estado_suscripcion' => 'activa',
                'vence_el'           => $hasta,
                'activa'             => true,
            ]);
        });

        return redirect()->route('admin.suscriptores.edit', $empresa)
            ->with('success', 'Pago registrado. Nuevo vencimiento: ' . $empresa->fresh()->vence_el->format('d/m/Y'));
    }

    /** Elimina un pago cargado por error. No modifica el vencimiento: ajustarlo a mano si corresponde. */
    public function destroy(Pago $pago)
    {
        $empresa = $pago->empresa;
        $pago->delete();

        return redirect()->route('admin.suscriptores.edit', $empresa)
            ->with('warning', 'Pago eliminado. Revise la fecha de vencimiento de la suscripción.');
    }
}
