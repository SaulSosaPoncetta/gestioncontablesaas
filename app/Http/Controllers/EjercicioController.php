<?php

namespace App\Http\Controllers;

use App\Http\Requests\EjercicioRequest;
use App\Models\Ejercicio;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EjercicioController extends Controller
{
    public function index()
    {
        $ejercicios = Ejercicio::orderByDesc('fecha_inicio')->get();

        return view('ejercicios.index', compact('ejercicios'));
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->puede('contador'), 403);

        // Propone el ejercicio siguiente al último cargado
        $ultimo = Ejercicio::orderByDesc('fecha_cierre')->first();
        $empresa = app('empresa_activa');

        if ($ultimo) {
            $inicio = $ultimo->fecha_cierre->copy()->addDay();
        } else {
            $cierreHoy = Carbon::create(now()->year, $empresa->mes_cierre, 1)->endOfMonth()->startOfDay();
            if ($cierreHoy->lt(now()->startOfDay())) {
                $cierreHoy = Carbon::create(now()->year + 1, $empresa->mes_cierre, 1)->endOfMonth()->startOfDay();
            }
            $inicio = $cierreHoy->copy()->subYearNoOverflow()->endOfMonth()->addDay();
        }

        $ejercicio = new Ejercicio([
            'fecha_inicio' => $inicio->startOfDay(),
            'fecha_cierre' => $inicio->copy()->addYearNoOverflow()->subDay()->startOfDay(),
        ]);
        $ejercicio->descripcion = 'Ejercicio ' . $ejercicio->fecha_cierre->year;

        return view('ejercicios.create', compact('ejercicio'));
    }

    public function store(EjercicioRequest $request)
    {
        $ejercicio = DB::transaction(function () use ($request) {
            $numero = (int) Ejercicio::lockForUpdate()->max('numero') + 1;

            return Ejercicio::create($request->validated() + ['numero' => $numero, 'estado' => 'abierto']);
        });

        session(['ejercicio_id' => $ejercicio->id]);

        return redirect()->route('ejercicios.index')
            ->with('success', "Ejercicio N° {$ejercicio->numero} creado y seleccionado.");
    }

    public function edit(Request $request, Ejercicio $ejercicio)
    {
        abort_unless($request->user()->puede('contador'), 403);

        return view('ejercicios.edit', compact('ejercicio'));
    }

    public function update(EjercicioRequest $request, Ejercicio $ejercicio)
    {
        if ($this->tieneMovimientos($ejercicio)) {
            // Con asientos cargados solo se permite cambiar descripción y bloqueo
            $ejercicio->update($request->safe()->only(['descripcion', 'bloqueado_hasta']));

            return redirect()->route('ejercicios.index')
                ->with('warning', 'El ejercicio tiene asientos: solo se actualizaron la descripción y el bloqueo.');
        }

        $ejercicio->update($request->validated());

        return redirect()->route('ejercicios.index')
            ->with('success', 'Ejercicio actualizado correctamente.');
    }

    public function destroy(Request $request, Ejercicio $ejercicio)
    {
        abort_unless($request->user()->puede('administrador'), 403);

        if ($this->tieneMovimientos($ejercicio)) {
            return back()->with('error', 'No se puede eliminar un ejercicio con asientos registrados.');
        }

        if (session('ejercicio_id') == $ejercicio->id) {
            session()->forget('ejercicio_id');
        }

        $ejercicio->delete();

        return redirect()->route('ejercicios.index')
            ->with('success', 'Ejercicio eliminado.');
    }

    /** Bloqueo/reapertura administrativa sin asientos. El cierre contable completo está en CierreEjercicioController. */
    public function cambiarEstado(Request $request, Ejercicio $ejercicio)
    {
        abort_unless($request->user()->puede('administrador'), 403);

        // Si se cerró con asientos de cierre, la reapertura los anula (ver CierreEjercicioController)
        if (! $ejercicio->estaAbierto() && $ejercicio->tieneCierreContable()) {
            try {
                app(\App\Services\CierreEjercicioService::class)->reabrir($ejercicio);
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }

            return back()->with('success', "Ejercicio N° {$ejercicio->numero} reabierto: se anularon la refundición, el cierre y la apertura del ejercicio siguiente.");
        }

        $ejercicio->update(['estado' => $ejercicio->estaAbierto() ? 'cerrado' : 'abierto']);

        return back()->with('success', $ejercicio->estaAbierto()
            ? "Ejercicio N° {$ejercicio->numero} reabierto."
            : "Ejercicio N° {$ejercicio->numero} cerrado. No admite nuevos asientos.");
    }

    public function seleccionar(Ejercicio $ejercicio)
    {
        session(['ejercicio_id' => $ejercicio->id]);

        return back()->with('success', "Trabajando sobre el ejercicio N° {$ejercicio->numero}.");
    }

    private function tieneMovimientos(Ejercicio $ejercicio): bool
    {
        return Schema::hasTable('asientos')
            && DB::table('asientos')->where('ejercicio_id', $ejercicio->id)->exists();
    }
}
