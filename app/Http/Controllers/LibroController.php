<?php

namespace App\Http\Controllers;

use App\Models\Cuenta;
use App\Services\LibrosService;
use App\Support\Csv;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LibroController extends Controller
{
    public function __construct(private LibrosService $libros)
    {
    }

    /* ----------------------------------------------------------------
     | Libro Diario
     * ---------------------------------------------------------------- */

    public function diario(Request $request)
    {
        if (! $ejercicio = $this->ejercicio()) {
            return $this->sinEjercicio();
        }
        [$desde, $hasta] = $this->periodo($request, $ejercicio);

        $query = $this->libros->diarioQuery($ejercicio, $desde, $hasta);
        $totales = $this->libros->totalesDiario($ejercicio, $desde, $hasta);

        if ($request->formato === 'pdf') {
            return $this->pdf('pdf.diario', 'libro_diario', compact('ejercicio', 'desde', 'hasta', 'totales') + [
                'asientos' => $query->get(),
            ]);
        }

        if ($request->formato === 'csv') {
            $filas = [];
            foreach ($query->get() as $a) {
                foreach ($a->lineas as $l) {
                    $filas[] = [
                        $a->numero, $a->fecha->format('d/m/Y'), $a->concepto,
                        $l->cuenta->codigo, $l->cuenta->nombre, $l->detalle,
                        $a->estaAnulado() ? 0.0 : (float) $l->debe,
                        $a->estaAnulado() ? 0.0 : (float) $l->haber,
                        $a->estaAnulado() ? 'ANULADO' : '',
                    ];
                }
            }
            return Csv::descargar($this->nombreArchivo('libro_diario', 'csv'),
                ['Asiento', 'Fecha', 'Concepto', 'Código', 'Cuenta', 'Detalle', 'Debe', 'Haber', 'Estado'], $filas);
        }

        return view('libros.diario', compact('ejercicio', 'desde', 'hasta', 'totales') + [
            'asientos' => $query->paginate(40)->withQueryString(),
        ]);
    }

    /* ----------------------------------------------------------------
     | Libro Mayor
     * ---------------------------------------------------------------- */

    public function mayor(Request $request)
    {
        if (! $ejercicio = $this->ejercicio()) {
            return $this->sinEjercicio();
        }
        [$desde, $hasta] = $this->periodo($request, $ejercicio);

        $cuentas = Cuenta::planOrdenado(Cuenta::where('imputable', true));
        $todas = $request->cuenta === 'todas';
        $cuenta = (! $todas && $request->cuenta) ? Cuenta::where('imputable', true)->find($request->cuenta) : null;

        // Mayor de todas las cuentas con movimientos (solo PDF / CSV)
        if ($todas && in_array($request->formato, ['pdf', 'csv'], true)) {
            $mayores = $this->libros->cuentasConMovimientos($ejercicio, $desde, $hasta)
                ->map(fn ($c) => $this->libros->mayor($ejercicio, $c, $desde, $hasta))
                ->filter(fn ($m) => $m['movimientos']->isNotEmpty() || $m['anterior'] != 0)
                ->values();

            return $request->formato === 'pdf'
                ? $this->pdf('pdf.mayor', 'libro_mayor', compact('ejercicio', 'desde', 'hasta', 'mayores'))
                : $this->mayorCsv($mayores);
        }

        $mayor = $cuenta ? $this->libros->mayor($ejercicio, $cuenta, $desde, $hasta) : null;

        if ($mayor && $request->formato === 'pdf') {
            return $this->pdf('pdf.mayor', 'mayor_' . $cuenta->codigo, compact('ejercicio', 'desde', 'hasta') + ['mayores' => collect([$mayor])]);
        }
        if ($mayor && $request->formato === 'csv') {
            return $this->mayorCsv(collect([$mayor]));
        }

        return view('libros.mayor', compact('ejercicio', 'desde', 'hasta', 'cuentas', 'cuenta', 'mayor', 'todas'));
    }

    private function mayorCsv($mayores)
    {
        $filas = [];
        foreach ($mayores as $m) {
            $c = $m['cuenta'];
            $filas[] = [$c->codigo, $c->nombre, '', '', 'Saldo anterior', '', 0.0, 0.0, $m['anterior']];
            foreach ($m['movimientos'] as $mov) {
                $filas[] = [$c->codigo, $c->nombre, $mov->fecha->format('d/m/Y'), $mov->numero,
                    $mov->concepto, $mov->detalle, $mov->debe, $mov->haber, $mov->saldo];
            }
        }

        return Csv::descargar($this->nombreArchivo('libro_mayor', 'csv'),
            ['Código', 'Cuenta', 'Fecha', 'Asiento', 'Concepto', 'Detalle', 'Debe', 'Haber', 'Saldo'], $filas);
    }

    /* ----------------------------------------------------------------
     | Balance de Sumas y Saldos
     * ---------------------------------------------------------------- */

    public function sumasSaldos(Request $request)
    {
        if (! $ejercicio = $this->ejercicio()) {
            return $this->sinEjercicio();
        }
        [$desde, $hasta] = $this->periodo($request, $ejercicio);

        $resultado = $this->libros->sumasYSaldos(
            $ejercicio, $desde, $hasta,
            $request->filled('nivel') ? (int) $request->nivel : null,
            $request->boolean('sin_movimiento')
        );

        $datos = compact('ejercicio', 'desde', 'hasta') + $resultado;

        if ($request->formato === 'pdf') {
            return $this->pdf('pdf.sumas-saldos', 'sumas_y_saldos', $datos);
        }

        if ($request->formato === 'csv') {
            $filas = $resultado['filas']->map(fn ($f) => [
                $f->cuenta->codigo, $f->cuenta->nombre, $f->cuenta->nivel,
                $f->debe, $f->haber, $f->deudor, $f->acreedor,
            ])->all();
            $t = $resultado['totales'];
            $filas[] = ['', 'TOTALES', '', $t['debe'], $t['haber'], $t['deudor'], $t['acreedor']];

            return Csv::descargar($this->nombreArchivo('sumas_y_saldos', 'csv'),
                ['Código', 'Cuenta', 'Nivel', 'Debe', 'Haber', 'Saldo deudor', 'Saldo acreedor'], $filas);
        }

        return view('libros.sumas-saldos', $datos);
    }

    /* ---------------------------------------------------------------- */

    private function ejercicio()
    {
        return app()->bound('ejercicio_activo') ? app('ejercicio_activo') : null;
    }

    private function sinEjercicio()
    {
        return redirect()->route('dashboard')->with('warning', 'Seleccione un ejercicio para consultar los libros.');
    }

    /** Período pedido, acotado al ejercicio (por defecto, el ejercicio completo). */
    private function periodo(Request $request, $ejercicio): array
    {
        $desde = $request->desde ? Carbon::parse($request->desde)->startOfDay() : $ejercicio->fecha_inicio->copy();
        $hasta = $request->hasta ? Carbon::parse($request->hasta)->startOfDay() : $ejercicio->fecha_cierre->copy();

        $desde = $desde->max($ejercicio->fecha_inicio)->copy();
        $hasta = $hasta->min($ejercicio->fecha_cierre)->copy();
        if ($hasta->lt($desde)) {
            $hasta = $desde->copy();
        }

        return [$desde, $hasta];
    }

    private function pdf(string $vista, string $nombre, array $datos)
    {
        $datos['empresa'] = app('empresa_activa');

        return Pdf::loadView($vista, $datos)
            ->setPaper('a4', 'portrait')
            ->stream($this->nombreArchivo($nombre, 'pdf'));
    }

    private function nombreArchivo(string $base, string $ext): string
    {
        return $base . '_' . app('empresa_activa')->cuit . '_' . now()->format('Ymd_His') . '.' . $ext;
    }
}
