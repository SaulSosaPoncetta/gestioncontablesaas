<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsientoRequest;
use App\Models\Asiento;
use App\Models\Cuenta;
use App\Services\AsientoService;
use Illuminate\Http\Request;

class AsientoController extends Controller
{
    public function __construct(private AsientoService $service)
    {
    }

    public function index(Request $request)
    {
        $ejercicio = $this->ejercicio();

        $asientos = Asiento::query()
            ->with('usuario:id,name')
            ->when($ejercicio, fn ($q) => $q->where('ejercicio_id', $ejercicio->id))
            ->when($request->desde, fn ($q, $d) => $q->whereDate('fecha', '>=', $d))
            ->when($request->hasta, fn ($q, $h) => $q->whereDate('fecha', '<=', $h))
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->when($request->tipo, fn ($q, $t) => $q->where('tipo', $t))
            ->when($request->numero, fn ($q, $n) => $q->where('numero', (int) $n))
            ->when($request->q, fn ($q, $txt) => $q->where('concepto', 'like', "%{$txt}%"))
            ->when($request->cuenta, fn ($q, $c) => $q->whereHas('lineas', fn ($l) => $l->where('cuenta_id', $c)))
            ->orderByRaw('numero IS NULL DESC')   // borradores primero
            ->orderByDesc('fecha')
            ->orderByDesc('numero')
            ->paginate(30)
            ->withQueryString();

        $borradores = $ejercicio ? Asiento::where('ejercicio_id', $ejercicio->id)->where('estado', 'borrador')->count() : 0;

        return view('asientos.index', [
            'asientos'   => $asientos,
            'ejercicio'  => $ejercicio,
            'borradores' => $borradores,
            'cuentas'    => Cuenta::planOrdenado(Cuenta::where('imputable', true)),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->puede('operador'), 403);

        $ejercicio = $this->ejercicio();
        if (! $ejercicio || ! $ejercicio->estaAbierto()) {
            return redirect()->route('asientos.index')->with('warning', 'Seleccione un ejercicio abierto para cargar asientos.');
        }

        $asiento = new Asiento(['fecha' => $this->fechaSugerida($ejercicio), 'tipo' => 'normal']);
        $lineas = [];

        // Copiar un asiento existente
        if ($request->copiar && $origen = Asiento::with('lineas')->find($request->copiar)) {
            $asiento->concepto = $origen->concepto;
            $asiento->tipo = $origen->tipo === 'normal' ? 'normal' : $origen->tipo;
            $lineas = $origen->lineas->map(fn ($l) => $l->only(['cuenta_id', 'debe', 'haber', 'detalle']))->all();
        }

        return view('asientos.create', [
            'asiento'   => $asiento,
            'lineas'    => $lineas,
            'cuentas'   => $this->cuentasImputables(),
            'ejercicio' => $ejercicio,
        ]);
    }

    public function store(AsientoRequest $request)
    {
        $asiento = $this->service->guardar($request->validated(), $this->ejercicio());

        $mensaje = $asiento->esBorrador()
            ? 'Asiento guardado como borrador.'
            : "Asiento N° {$asiento->numero} registrado.";

        if ($request->boolean('otro')) {
            return redirect()->route('asientos.create')->with('success', $mensaje);
        }

        return redirect()->route('asientos.show', $asiento)->with('success', $mensaje);
    }

    public function show(Asiento $asiento)
    {
        $asiento->load(['lineas.cuenta', 'usuario', 'anuladoPor', 'ejercicio']);

        return view('asientos.show', compact('asiento'));
    }

    public function edit(Request $request, Asiento $asiento)
    {
        abort_unless($request->user()->puede('operador'), 403);

        if (! $asiento->esEditableManualmente()) {
            return redirect()->route('asientos.show', $asiento)
                ->with('warning', $asiento->esAutomatico() ? 'Este asiento lo generó un comprobante: modifíquelo o anúlelo desde el comprobante.' : 'Este asiento no se puede modificar (anulado, ejercicio cerrado o período bloqueado).');
        }

        $asiento->load('lineas');

        return view('asientos.edit', [
            'asiento'   => $asiento,
            'lineas'    => $asiento->lineas->map(fn ($l) => $l->only(['cuenta_id', 'debe', 'haber', 'detalle']))->all(),
            'cuentas'   => $this->cuentasImputables($asiento),
            'ejercicio' => $asiento->ejercicio,
        ]);
    }

    public function update(AsientoRequest $request, Asiento $asiento)
    {
        if (! $asiento->esEditableManualmente()) {
            return redirect()->route('asientos.show', $asiento)->with('error', 'Este asiento no se puede modificar.');
        }

        $eraBorrador = $asiento->esBorrador();
        $asiento = $this->service->guardar($request->validated(), $asiento->ejercicio, $asiento);

        $mensaje = match (true) {
            $asiento->esBorrador()       => 'Borrador actualizado.',
            $eraBorrador                 => "Asiento N° {$asiento->numero} registrado.",
            default                      => "Asiento N° {$asiento->numero} modificado.",
        };

        return redirect()->route('asientos.show', $asiento)->with('success', $mensaje);
    }

    /** Solo se eliminan borradores. Los registrados se anulan. */
    public function destroy(Request $request, Asiento $asiento)
    {
        abort_unless($request->user()->puede('operador'), 403);

        if (! $asiento->esBorrador()) {
            return back()->with('error', 'Solo se pueden eliminar borradores. Los asientos registrados se anulan.');
        }

        $asiento->delete();

        return redirect()->route('asientos.index')->with('success', 'Borrador eliminado.');
    }

    public function anular(Request $request, Asiento $asiento)
    {
        abort_unless($request->user()->puede('contador'), 403);

        $request->validate(['motivo' => ['required', 'string', 'max:255']], [], ['motivo' => 'motivo de anulación']);

        if ($asiento->estado !== 'registrado' || ! $asiento->esEditableManualmente()) {
            return back()->with('error', 'Solo se pueden anular asientos registrados de un período abierto.');
        }

        $this->service->anular($asiento, $request->motivo);

        return redirect()->route('asientos.show', $asiento)->with('success', "Asiento N° {$asiento->numero} anulado.");
    }

    public function renumerar(Request $request)
    {
        abort_unless($request->user()->puede('contador'), 403);

        $ejercicio = $this->ejercicio();
        if (! $ejercicio || ! $ejercicio->estaAbierto()) {
            return back()->with('error', 'Solo se pueden renumerar asientos de un ejercicio abierto.');
        }

        $cantidad = $this->service->renumerar($ejercicio);

        return redirect()->route('asientos.index')
            ->with('success', "Se renumeraron {$cantidad} asientos del ejercicio N° {$ejercicio->numero} por orden de fecha.");
    }

    /* ---------------------------------------------------------------- */

    private function ejercicio()
    {
        return app()->bound('ejercicio_activo') ? app('ejercicio_activo') : null;
    }

    /** Hoy si cae dentro del ejercicio; si no, la fecha de cierre o la última usada. */
    private function fechaSugerida($ejercicio)
    {
        if ($ejercicio->admiteFecha(today())) {
            return today();
        }

        $ultima = Asiento::where('ejercicio_id', $ejercicio->id)->max('fecha');

        return $ultima ? \Carbon\Carbon::parse($ultima) : $ejercicio->fecha_inicio;
    }

    /** Cuentas imputables activas (al editar, incluye las inactivas que ya usa el asiento). */
    private function cuentasImputables(?Asiento $asiento = null)
    {
        $usadas = $asiento ? $asiento->lineas->pluck('cuenta_id')->all() : [];

        return Cuenta::planOrdenado(
            Cuenta::where('imputable', true)
                ->where(fn ($q) => $q->where('activa', true)->orWhereIn('id', $usadas))
        );
    }
}
