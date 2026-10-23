<?php

namespace App\Http\Controllers;

use App\Models\Plan;

class SuscripcionController extends Controller
{
    public function show()
    {
        $empresa = app('empresa_activa')->load('plan');

        return view('suscripcion.show', [
            'empresa' => $empresa,
            'pagos'   => $empresa->pagos()->with('plan')->limit(24)->get(),
            'planes'  => Plan::where('activo', true)->orderBy('orden')->get(),
            'estado'  => $empresa->estadoAcceso(),
            'dias'    => $empresa->diasParaVencer(),
        ]);
    }
}
