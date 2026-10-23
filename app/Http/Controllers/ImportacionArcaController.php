<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Models\CuentaConfig;
use App\Services\ComprobanteService;
use App\Services\ImportadorMisComprobantes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Importación de «Mis Comprobantes» de ARCA en tres pasos: subir → revisar → confirmar.
 */
class ImportacionArcaController extends Controller
{
    public function form(Request $request)
    {
        abort_unless($request->user()->puede('operador'), 403);

        return view('importacion.form', ['circuito' => $request->circuito === 'compra' ? 'compra' : 'venta']);
    }

    public function analizar(Request $request, ImportadorMisComprobantes $importador)
    {
        abort_unless($request->user()->puede('operador'), 403);

        $request->validate([
            'circuito' => ['required', Rule::in(['venta', 'compra'])],
            'archivo'  => ['required', 'file', 'max:10240', 'mimes:csv,txt,xlsx,xls'],
        ], [], ['archivo' => 'archivo']);

        $archivo = $request->file('archivo');
        $filas = $importador->leer($archivo->getRealPath(), $archivo->getClientOriginalExtension());

        if ($importador->errores) {
            return back()->with('error', implode(' ', $importador->errores));
        }
        if (! $filas) {
            return back()->with('warning', 'El archivo no tiene comprobantes.');
        }

        $token = Str::random(24);
        Cache::put($this->clave($token), ['circuito' => $request->circuito, 'filas' => $filas, 'archivo' => $archivo->getClientOriginalName()], now()->addHours(2));

        return redirect()->route('importacion.revisar', $token);
    }

    public function revisar(Request $request, string $token, ImportadorMisComprobantes $importador)
    {
        $datos = Cache::get($this->clave($token));
        if (! $datos) {
            return redirect()->route('importacion.form')->with('warning', 'La importación expiró. Vuelva a subir el archivo.');
        }

        $circuito = $datos['circuito'];
        $filas = $importador->analizar($datos['filas'], $circuito);

        return view('importacion.revisar', [
            'token'      => $token,
            'circuito'   => $circuito,
            'archivo'    => $datos['archivo'],
            'filas'      => $filas,
            'conteo'     => collect($filas)->countBy('estado'),
            'cuentas'    => Cuenta::planOrdenado(Cuenta::where('imputable', true)->where('activa', true)),
            'cuentaDefecto' => CuentaConfig::idPara($circuito === 'venta' ? 'ventas' : 'compras'),
        ]);
    }

    public function confirmar(Request $request, string $token, ImportadorMisComprobantes $importador, ComprobanteService $service)
    {
        abort_unless($request->user()->puede('operador'), 403);

        $datos = Cache::get($this->clave($token));
        if (! $datos) {
            return redirect()->route('importacion.form')->with('warning', 'La importación expiró. Vuelva a subir el archivo.');
        }

        $empresaId = app('empresa_activa')->id;
        $circuito = $datos['circuito'];

        $opciones = $request->validate([
            'cuenta_imputacion_id' => ['required', Rule::exists('cuentas', 'id')->where('empresa_id', $empresaId)->where('imputable', true)],
            'operacion_iva'        => ['required', 'integer', $circuito === 'venta' ? 'between:1,2' : 'between:1,4'],
            'periodo'              => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'seleccion'            => ['required', 'array', 'min:1'],
        ], ['seleccion.required' => 'Seleccione al menos un comprobante para importar.'], ['cuenta_imputacion_id' => 'cuenta de imputación']);
        $opciones['usar_habitual'] = $request->boolean('usar_habitual');

        // Se vuelve a analizar por si algo cambió desde la revisión
        $seleccion = array_map('intval', $request->input('seleccion', []));
        $filas = collect($importador->analizar($datos['filas'], $circuito))
            ->filter(fn ($f) => in_array($f['linea'], $seleccion, true))
            ->values()->all();

        $resultado = $importador->importar($filas, $circuito, $opciones, $service);

        if (! $resultado['errores']) {
            Cache::forget($this->clave($token));
        }

        $redir = $resultado['errores']
            ? redirect()->route('importacion.revisar', $token)
            : redirect()->route('comprobantes.index', $circuito);

        return $redir
            ->with($resultado['importados'] ? 'success' : 'warning', "Se importaron {$resultado['importados']} comprobantes con sus asientos.")
            ->with('erroresImportacion', $resultado['errores']);
    }

    private function clave(string $token): string
    {
        return 'importacion_mc_' . app('empresa_activa')->id . '_' . auth()->id() . '_' . $token;
    }
}
