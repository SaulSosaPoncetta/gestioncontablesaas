<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmpresaRequest;
use Illuminate\Http\Request;

/**
 * "Mi empresa": datos de la empresa cliente. El alta se hace por el registro público
 * y la gestión de la suscripción desde el panel de administración.
 */
class EmpresaController extends Controller
{
    public function edit(Request $request)
    {
        abort_unless($request->user()->puede('administrador'), 403);

        return view('empresa.edit', ['empresa' => app('empresa_activa')]);
    }

    public function update(EmpresaRequest $request)
    {
        app('empresa_activa')->update($request->validated());

        return redirect()->route('empresa.edit')
            ->with('success', 'Datos de la empresa actualizados.');
    }
}
