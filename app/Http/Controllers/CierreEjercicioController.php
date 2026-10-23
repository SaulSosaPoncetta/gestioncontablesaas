<?php

namespace App\Http\Controllers;

use App\Models\Ejercicio;
use App\Services\CierreEjercicioService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Cierre contable del ejercicio (refundición, cierre y apertura del siguiente) y su reapertura.
 */
class CierreEjercicioController extends Controller
{
    public function __construct(private CierreEjercicioService $cierre)
    {
    }

    public function show(Request $request, Ejercicio $ejercicio)
    {
        abort_unless($request->user()->puede('contador'), 403);

        return view('cierre.show', ['ejercicio' => $ejercicio]
            + $this->cierre->verificar($ejercicio)
            + $this->cierre->resumen($ejercicio));
    }

    public function cerrar(Request $request, Ejercicio $ejercicio)
    {
        abort_unless($request->user()->puede('administrador'), 403);

        $request->validate(['confirmo' => 'accepted'], ['confirmo.accepted' => 'Confirme que revisó los saldos antes de cerrar.']);

        try {
            [, , $apertura, $siguiente] = $this->cierre->cerrar($ejercicio);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        session(['ejercicio_id' => $siguiente->id]);

        $mensaje = "Ejercicio N° {$ejercicio->numero} cerrado. Ahora trabaja sobre el ejercicio N° {$siguiente->numero}";
        $mensaje .= $apertura ? ", con el asiento de apertura N° {$apertura->numero_formateado}." : '.';
        if ($apertura && $apertura->numero > 1) {
            $mensaje .= ' Como ya había asientos en el ejercicio nuevo, use «Renumerar» en Asientos para que la apertura quede primera.';
        }

        return redirect()->route('ejercicios.index')->with('success', $mensaje);
    }

    public function reabrir(Request $request, Ejercicio $ejercicio)
    {
        abort_unless($request->user()->puede('administrador'), 403);

        try {
            $this->cierre->reabrir($ejercicio);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        session(['ejercicio_id' => $ejercicio->id]);

        return redirect()->route('ejercicios.index')->with('success',
            "Ejercicio N° {$ejercicio->numero} reabierto: se anularon la refundición, el cierre y la apertura del ejercicio siguiente.");
    }
}
