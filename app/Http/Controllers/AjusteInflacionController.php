<?php

namespace App\Http\Controllers;

use App\Services\AjusteInflacionService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Ajuste por inflación (RT 6) del ejercicio en uso.
 */
class AjusteInflacionController extends Controller
{
    public function __construct(private AjusteInflacionService $ajuste)
    {
    }

    public function index()
    {
        $ejercicio = app()->bound('ejercicio_activo') ? app('ejercicio_activo') : null;

        if (! $ejercicio) {
            return redirect()->route('ejercicios.index')->with('warning', 'Primero cree o seleccione un ejercicio.');
        }

        return view('ajuste.index', ['ejercicio' => $ejercicio] + $this->ajuste->calcular($ejercicio));
    }

    public function generar(Request $request)
    {
        abort_unless($request->user()->puede('contador'), 403);

        $ejercicio = app()->bound('ejercicio_activo') ? app('ejercicio_activo') : null;
        abort_unless($ejercicio, 404);

        try {
            $asiento = $this->ajuste->generar($ejercicio);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('asientos.show', $asiento)
            ->with('success', "Ajuste por inflación registrado en el asiento N° {$asiento->numero_formateado}.");
    }
}
