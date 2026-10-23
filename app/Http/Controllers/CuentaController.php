<?php

namespace App\Http\Controllers;

use App\Http\Requests\CuentaRequest;
use App\Models\Cuenta;
use App\Services\ImportadorPlanCuentas;
use App\Services\PlanCuentasModelo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CuentaController extends Controller
{
    public function index(Request $request)
    {
        $query = Cuenta::query()
            ->withCount('hijas')
            ->when($request->tipo, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($request->filled('estado'), fn ($q) => $q->where('activa', $request->estado === 'activas'));

        $buscar = trim((string) $request->q);
        if ($buscar !== '') {
            $query->where(fn ($w) => $w->where('codigo', 'like', "{$buscar}%")->orWhere('nombre', 'like', "%{$buscar}%"));
        }

        $cuentas = Cuenta::planOrdenado($query);

        return view('cuentas.index', [
            'cuentas'    => $cuentas,
            'filtrando'  => $buscar !== '' || $request->tipo || $request->filled('estado'),
            'total'      => Cuenta::count(),
            'imputables' => Cuenta::where('imputable', true)->count(),
            'puedeEditar'=> $request->user()->puede('contador'),
        ]);
    }

    public function create(Request $request)
    {
        $this->autorizar($request);

        $padre = $request->padre ? Cuenta::findOrFail($request->padre) : null;

        if ($padre?->imputable) {
            return back()->with('warning', "La cuenta {$padre->codigo_nombre} es imputable: no admite subcuentas.");
        }

        $codigo = Cuenta::siguienteCodigo($padre);
        [$tipo, $saldo] = Cuenta::tipoPorCodigo($codigo);

        $cuenta = new Cuenta([
            'padre_id'       => $padre?->id,
            'codigo'         => $codigo,
            'saldo_habitual' => $padre?->saldo_habitual ?? $saldo,
            'imputable'      => $padre && $padre->nivel >= 3,
            'monetaria'      => $padre?->monetaria ?? true,
            'activa'         => true,
        ]);

        return view('cuentas.create', ['cuenta' => $cuenta, 'padres' => $this->padresPosibles()]);
    }

    public function store(CuentaRequest $request)
    {
        $cuenta = Cuenta::create($this->datos($request));

        return redirect()->to(route('cuentas.index') . '#c' . $cuenta->id)
            ->with('success', "Cuenta {$cuenta->codigo_nombre} creada.");
    }

    public function edit(Request $request, Cuenta $cuenta)
    {
        $this->autorizar($request);

        return view('cuentas.edit', [
            'cuenta'           => $cuenta,
            'padres'           => $this->padresPosibles($cuenta),
            'tieneHijas'       => $cuenta->tieneHijas(),
            'tieneMovimientos' => $cuenta->tieneMovimientos(),
        ]);
    }

    public function update(CuentaRequest $request, Cuenta $cuenta)
    {
        $cuenta->update($this->datos($request));

        return redirect()->to(route('cuentas.index') . '#c' . $cuenta->id)
            ->with('success', "Cuenta {$cuenta->codigo_nombre} actualizada.");
    }

    public function destroy(Request $request, Cuenta $cuenta)
    {
        $this->autorizar($request);

        if ($cuenta->tieneHijas()) {
            return back()->with('error', 'No se puede eliminar una cuenta con subcuentas. Elimine primero las subcuentas.');
        }
        if ($cuenta->tieneMovimientos()) {
            return back()->with('error', 'La cuenta tiene movimientos: no se puede eliminar. Puede desactivarla.');
        }

        $nombre = $cuenta->codigo_nombre;
        $cuenta->delete();

        return redirect()->route('cuentas.index')->with('success', "Cuenta {$nombre} eliminada.");
    }

    public function cambiarEstado(Request $request, Cuenta $cuenta)
    {
        $this->autorizar($request);

        $cuenta->update(['activa' => ! $cuenta->activa]);

        return back()->with('success', "Cuenta {$cuenta->codigo} " . ($cuenta->activa ? 'activada.' : 'desactivada: no se podrá usar en nuevos asientos.'));
    }

    /** Carga el plan modelo cuando la empresa no tiene cuentas. */
    public function cargarModelo(Request $request)
    {
        $this->autorizar($request);

        if (Cuenta::exists()) {
            return back()->with('error', 'La empresa ya tiene un plan de cuentas. El modelo solo se carga sobre un plan vacío.');
        }

        $cantidad = PlanCuentasModelo::aplicar(app('empresa_activa'));

        return redirect()->route('cuentas.index')->with('success', "Plan de cuentas modelo cargado ({$cantidad} cuentas).");
    }

    /* ----------------------------------------------------------------
     | Exportar / Importar
     * ---------------------------------------------------------------- */

    public function exportar(): StreamedResponse
    {
        $cuentas = Cuenta::planOrdenado();
        $empresa = app('empresa_activa');
        $archivo = 'plan_de_cuentas_' . $empresa->cuit . '_' . now()->format('Ymd') . '.csv';

        return response()->streamDownload(function () use ($cuentas) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM para que Excel respete los acentos
            fputcsv($out, ['codigo', 'nombre', 'imputable', 'saldo_habitual', 'monetaria', 'tipo', 'activa'], ';');
            foreach ($cuentas as $c) {
                fputcsv($out, [
                    $c->codigo,
                    $c->nombre,
                    $c->imputable ? 'S' : 'N',
                    $c->saldo_habitual === 'deudor' ? 'D' : 'A',
                    $c->monetaria ? 'S' : 'N',
                    Cuenta::TIPOS[$c->tipo] ?? $c->tipo,
                    $c->activa ? 'S' : 'N',
                ], ';');
            }
            fclose($out);
        }, $archivo, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importarForm(Request $request)
    {
        $this->autorizar($request);

        return view('cuentas.importar');
    }

    public function importar(Request $request)
    {
        $this->autorizar($request);

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [], ['archivo' => 'archivo CSV']);

        $importador = new ImportadorPlanCuentas();

        if (! $importador->importar($request->file('archivo')->getRealPath())) {
            return back()->with('erroresImportacion', $importador->errores)
                ->with('error', 'No se importó ninguna cuenta. Corrija los errores y vuelva a intentar.');
        }

        return redirect()->route('cuentas.index')->with('success',
            "Importación terminada: {$importador->creadas} cuentas nuevas y {$importador->actualizadas} actualizadas.");
    }

    public function plantilla(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ([
                ['codigo', 'nombre', 'imputable', 'saldo_habitual', 'monetaria'],
                ['1', 'ACTIVO', 'N', 'D', 'S'],
                ['1.1', 'ACTIVO CORRIENTE', 'N', 'D', 'S'],
                ['1.1.01', 'Caja y Bancos', 'N', 'D', 'S'],
                ['1.1.01.001', 'Caja', 'S', 'D', 'S'],
                ['1.1.01.002', 'Banco Nación Cta. Cte.', 'S', 'D', 'S'],
            ] as $fila) {
                fputcsv($out, $fila, ';');
            }
            fclose($out);
        }, 'plantilla_plan_de_cuentas.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ----------------------------------------------------------------
     | Auxiliares
     * ---------------------------------------------------------------- */

    private function datos(CuentaRequest $request): array
    {
        $codigo = $request->codigo;
        [$tipo] = Cuenta::tipoPorCodigo($codigo);

        return [
            'padre_id'       => $request->padre_id ?: null,
            'codigo'         => $codigo,
            'nombre'         => $request->nombre,
            'nivel'          => substr_count($codigo, '.') + 1,
            'tipo'           => $tipo,
            'imputable'      => $request->boolean('imputable'),
            'saldo_habitual' => $request->saldo_habitual,
            'monetaria'      => $request->boolean('monetaria'),
            'ajustable'      => ! $request->boolean('monetaria'),
            'activa'         => $request->boolean('activa', true),
        ];
    }

    /** Cuentas que pueden ser padre: no imputables (y, al editar, que no sean la propia ni sus subcuentas). */
    private function padresPosibles(?Cuenta $actual = null)
    {
        return Cuenta::planOrdenado(
            Cuenta::where('imputable', false)
                ->when($actual, fn ($q) => $q->whereKeyNot($actual->id)->where('codigo', 'not like', $actual->codigo . '.%'))
        );
    }

    private function autorizar(Request $request): void
    {
        abort_unless($request->user()->puede('contador'), 403);
    }
}
