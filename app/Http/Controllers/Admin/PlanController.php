<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        return view('admin.planes.index', [
            'planes' => Plan::withCount('empresas')->orderBy('orden')->get(),
        ]);
    }

    public function create()
    {
        return view('admin.planes.create', ['plan' => new Plan(['activo' => true])]);
    }

    public function store(Request $request)
    {
        Plan::create($this->validar($request));

        return redirect()->route('admin.planes.index')->with('success', 'Plan creado.');
    }

    public function edit(Plan $plan)
    {
        return view('admin.planes.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $plan->update($this->validar($request));

        return redirect()->route('admin.planes.index')->with('success', 'Plan actualizado.');
    }

    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'nombre'         => ['required', 'string', 'max:60'],
            'descripcion'    => ['nullable', 'string', 'max:255'],
            'precio_mensual' => ['required', 'numeric', 'min:0'],
            'max_usuarios'   => ['nullable', 'integer', 'min:1'],
            'orden'          => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        return $datos + [
            'modulo_iva'           => $request->boolean('modulo_iva'),
            'modulo_retenciones'   => $request->boolean('modulo_retenciones'),
            'modulo_integraciones' => $request->boolean('modulo_integraciones'),
            'activo'               => $request->boolean('activo'),
            'orden'                => $datos['orden'] ?? 0,
        ];
    }
}
