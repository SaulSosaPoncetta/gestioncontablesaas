<?php

namespace App\Http\Controllers;

use App\Services\EstadosContablesService;
use App\Support\Csv;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EstadoController extends Controller
{
    public function __construct(private EstadosContablesService $estados)
    {
    }

    /** Estado de Situación Patrimonial al día "fecha" */
    public function situacion(Request $request)
    {
        if (! $ejercicio = $this->ejercicio()) {
            return $this->sinEjercicio();
        }

        $fecha = $request->fecha ? Carbon::parse($request->fecha)->startOfDay() : min(today(), $ejercicio->fecha_cierre)->copy();
        $fecha = $fecha->max($ejercicio->fecha_inicio)->min($ejercicio->fecha_cierre)->copy();
        $nivel = in_array((int) $request->nivel, [2, 3, 4, 5], true) ? (int) $request->nivel : 3;
        $anterior = $request->boolean('comparativo') ? $this->estados->ejercicioAnterior($ejercicio) : null;

        $datos = compact('ejercicio', 'fecha', 'nivel', 'anterior')
            + $this->estados->situacionPatrimonial($ejercicio, $fecha, $anterior, $nivel);

        if ($request->formato === 'pdf') {
            return $this->pdf('pdf.situacion', 'estado_situacion_patrimonial', $datos + ['desde' => $ejercicio->fecha_inicio, 'hasta' => $fecha]);
        }

        if ($request->formato === 'csv') {
            $filas = [];
            foreach ($datos['secciones'] as $s) {
                $filas[] = [$s['titulo'], null, null];
                foreach ($s['filas'] as $f) {
                    $filas[] = [str_repeat('   ', $f->sangria) . $f->texto, $f->importe, $f->anterior];
                }
                $filas[] = ['TOTAL ' . $s['titulo'], $s['total'], $s['anterior']];
            }
            $filas[] = ['TOTAL PASIVO + PATRIMONIO NETO', $datos['pasivoMasPn'], $datos['pasivoMasPnAnt']];

            return Csv::descargar($this->archivo('estado_situacion_patrimonial', 'csv'),
                ['Concepto', 'Al ' . $fecha->format('d/m/Y'), $anterior ? 'Al ' . $anterior->fecha_cierre->format('d/m/Y') : ''], $filas);
        }

        return view('estados.situacion', $datos);
    }

    /** Estado de Resultados del período */
    public function resultados(Request $request)
    {
        if (! $ejercicio = $this->ejercicio()) {
            return $this->sinEjercicio();
        }

        $desde = $request->desde ? Carbon::parse($request->desde)->startOfDay() : $ejercicio->fecha_inicio->copy();
        $hasta = $request->hasta ? Carbon::parse($request->hasta)->startOfDay() : $ejercicio->fecha_cierre->copy();
        $desde = $desde->max($ejercicio->fecha_inicio)->copy();
        $hasta = $hasta->min($ejercicio->fecha_cierre)->max($desde)->copy();
        $nivel = in_array((int) $request->nivel, [2, 3, 4, 5], true) ? (int) $request->nivel : 3;
        $anterior = $request->boolean('comparativo') ? $this->estados->ejercicioAnterior($ejercicio) : null;

        $datos = compact('ejercicio', 'desde', 'hasta', 'nivel', 'anterior')
            + $this->estados->resultados($ejercicio, $desde, $hasta, $anterior, $nivel);

        if ($request->formato === 'pdf') {
            return $this->pdf('pdf.resultados', 'estado_de_resultados', $datos);
        }

        if ($request->formato === 'csv') {
            $filas = array_map(fn ($f) => [str_repeat('   ', $f->sangria) . $f->texto, $f->importe, $f->anterior], $datos['filas']);

            return Csv::descargar($this->archivo('estado_de_resultados', 'csv'),
                ['Concepto', $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y'), $anterior ? 'Ejercicio anterior' : ''], $filas);
        }

        return view('estados.resultados', $datos);
    }

    /* ---------------------------------------------------------------- */

    private function ejercicio()
    {
        return app()->bound('ejercicio_activo') ? app('ejercicio_activo') : null;
    }

    private function sinEjercicio()
    {
        return redirect()->route('dashboard')->with('warning', 'Seleccione un ejercicio para ver los estados contables.');
    }

    private function pdf(string $vista, string $nombre, array $datos)
    {
        $datos['empresa'] = app('empresa_activa');

        return Pdf::loadView($vista, $datos)->setPaper('a4', 'portrait')->stream($this->archivo($nombre, 'pdf'));
    }

    private function archivo(string $base, string $ext): string
    {
        return $base . '_' . app('empresa_activa')->cuit . '_' . now()->format('Ymd_His') . '.' . $ext;
    }
}
