<?php

namespace App\Http\Controllers;

use App\Models\Asiento;
use App\Models\Cuenta;
use App\Models\Ejercicio;

class DashboardController extends Controller
{
    public function index()
    {
        $ejercicio = app()->bound('ejercicio_activo') ? app('ejercicio_activo') : null;

        $asientosEj = fn () => Asiento::query()->when($ejercicio, fn ($q) => $q->where('ejercicio_id', $ejercicio->id));

        $stats = [
            'cuentas'     => Cuenta::count(),
            'imputables'  => Cuenta::imputables()->count(),
            'ejercicios'  => Ejercicio::count(),
            'abiertos'    => Ejercicio::where('estado', 'abierto')->count(),
            'registrados' => $asientosEj()->where('estado', 'registrado')->count(),
            'borradores'  => $asientosEj()->where('estado', 'borrador')->count(),
            'mes'         => $asientosEj()->where('estado', 'registrado')
                                ->whereBetween('fecha', [today()->startOfMonth(), today()->endOfMonth()])->count(),
        ];

        $ultimos = $asientosEj()->whereIn('estado', ['registrado', 'borrador'])
            ->latest('updated_at')->limit(8)->get();

        $diasRestantes = $ejercicio && $ejercicio->estaAbierto()
            ? (int) today()->diffInDays($ejercicio->fecha_cierre, false)
            : null;

        return view('dashboard', compact('ejercicio', 'stats', 'diasRestantes', 'ultimos'));
    }
}
