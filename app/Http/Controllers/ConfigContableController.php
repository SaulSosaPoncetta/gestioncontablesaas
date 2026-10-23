<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Models\CuentaConfig;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Configuración de las cuentas que usan los asientos automáticos (comprobantes, IVA, retenciones).
 */
class ConfigContableController extends Controller
{
    public function edit(Request $request)
    {
        abort_unless($request->user()->puede('contador'), 403);

        $configuradas = CuentaConfig::pluck('cuenta_id', 'clave');
        $items = collect(CuentaConfig::CLAVES)->map(function ($def, $clave) use ($configuradas) {
            $efectiva = CuentaConfig::cuentaPara($clave);
            return (object) [
                'clave'       => $clave,
                'grupo'       => $def[0],
                'descripcion' => $def[1],
                'codigoModelo'=> $def[2],
                'cuenta_id'   => $configuradas[$clave] ?? null,
                'efectiva'    => $efectiva,
            ];
        })->groupBy('grupo');

        return view('config.cuentas', [
            'grupos'  => $items,
            'cuentas' => Cuenta::planOrdenado(Cuenta::where('imputable', true)->where('activa', true)),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()->puede('contador'), 403);

        $empresaId = app('empresa_activa')->id;
        $request->validate([
            'cuentas'   => ['array'],
            'cuentas.*' => ['nullable', Rule::exists('cuentas', 'id')->where('empresa_id', $empresaId)->where('imputable', true)],
        ]);

        foreach (array_keys(CuentaConfig::CLAVES) as $clave) {
            $cuentaId = $request->input("cuentas.{$clave}") ?: null;
            CuentaConfig::updateOrCreate(['clave' => $clave], ['cuenta_id' => $cuentaId]);
        }

        return redirect()->route('config.cuentas')->with('success', 'Cuentas automáticas actualizadas.');
    }
}
