<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Models\Integracion;
use App\Models\IntegracionRegistro;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Configuración › Integraciones: tokens de acceso para GestiónComercial u otros sistemas. */
class IntegracionController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->puede('administrador'), 403);

        $registros = IntegracionRegistro::with(['integracion:id,nombre', 'comprobante'])
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->latest('updated_at')
            ->paginate(30)
            ->withQueryString();

        return view('integraciones.index', [
            'integraciones' => Integracion::withCount(['registros', 'registros as errores_count' => fn ($q) => $q->where('estado', 'error')])->get(),
            'registros'     => $registros,
            'cuentas'       => Cuenta::planOrdenado(Cuenta::where('imputable', true)->where('activa', true)),
            'tokenNuevo'    => session('token_nuevo'),
            'urlApi'        => url('/api/integracion/v1'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->puede('administrador'), 403);

        $datos = $request->validate([
            'sistema' => ['required', Rule::in(array_keys(Integracion::SISTEMAS))],
            'nombre'  => ['required', 'string', 'max:100'],
        ]);

        $integracion = new Integracion($datos + ['activa' => true]);
        $token = $integracion->generarToken();
        $integracion->save();

        return redirect()->route('integraciones.index')
            ->with('token_nuevo', ['id' => $integracion->id, 'token' => $token])
            ->with('success', 'Integración creada. Copie el token ahora: no se vuelve a mostrar.');
    }

    public function update(Request $request, Integracion $integracion)
    {
        abort_unless($request->user()->puede('administrador'), 403);
        $empresaId = app('empresa_activa')->id;

        $datos = $request->validate([
            'nombre'            => ['required', 'string', 'max:100'],
            'cuenta_ventas_id'  => ['nullable', Rule::exists('cuentas', 'id')->where('empresa_id', $empresaId)->where('imputable', true)],
            'cuenta_compras_id' => ['nullable', Rule::exists('cuentas', 'id')->where('empresa_id', $empresaId)->where('imputable', true)],
        ]);

        $integracion->update($datos + ['activa' => $request->boolean('activa')]);

        return redirect()->route('integraciones.index')->with('success', 'Integración actualizada.');
    }

    /** Genera un token nuevo; el anterior deja de funcionar. */
    public function regenerar(Request $request, Integracion $integracion)
    {
        abort_unless($request->user()->puede('administrador'), 403);

        $token = $integracion->generarToken();
        $integracion->save();

        return redirect()->route('integraciones.index')
            ->with('token_nuevo', ['id' => $integracion->id, 'token' => $token])
            ->with('warning', 'Se generó un token nuevo. El anterior ya no funciona: actualícelo en el sistema de origen.');
    }

    public function destroy(Request $request, Integracion $integracion)
    {
        abort_unless($request->user()->puede('administrador'), 403);

        $integracion->delete();

        return redirect()->route('integraciones.index')->with('success', 'Integración eliminada. Los comprobantes recibidos se conservan.');
    }
}
