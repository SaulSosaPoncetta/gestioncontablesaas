<?php

namespace App\Http\Controllers;

use App\Http\Requests\TerceroRequest;
use App\Models\Cuenta;
use App\Models\Tercero;
use Illuminate\Http\Request;

class TerceroController extends Controller
{
    public function index(Request $request)
    {
        $terceros = Tercero::query()
            ->withCount('comprobantes')
            ->when($request->tipo, fn ($q, $t) => $t === 'cliente' || $t === 'proveedor' ? $q->whereIn('tipo', [$t, 'ambos']) : $q->where('tipo', $t))
            ->when($request->q, function ($q, $buscar) {
                $doc = preg_replace('/\D/', '', $buscar);
                $q->where(function ($w) use ($buscar, $doc) {
                    $w->where('razon_social', 'like', "%{$buscar}%");
                    if ($doc !== '') {
                        $w->orWhere('nro_documento', 'like', "%{$doc}%");
                    }
                });
            })
            ->when($request->filled('estado'), fn ($q) => $q->where('activo', $request->estado === 'activos'))
            ->orderBy('razon_social')
            ->paginate(30)
            ->withQueryString();

        return view('terceros.index', compact('terceros'));
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->puede('operador'), 403);

        $tercero = new Tercero([
            'tipo'           => in_array($request->tipo, ['cliente', 'proveedor'], true) ? $request->tipo : 'cliente',
            'tipo_documento' => 80,
            'condicion_iva'  => 1,
            'activo'         => true,
        ]);

        return view('terceros.create', ['tercero' => $tercero, 'cuentas' => $this->cuentas()]);
    }

    public function store(TerceroRequest $request)
    {
        $tercero = Tercero::create($request->validated());

        $volver = (string) $request->input('volver');
        $destino = $volver !== '' && str_starts_with($volver, url('/')) ? $volver : route('terceros.index');

        return redirect()->to($destino)
            ->with('success', "{$tercero->razon_social} dado de alta.");
    }

    public function edit(Request $request, Tercero $tercero)
    {
        abort_unless($request->user()->puede('operador'), 403);

        return view('terceros.edit', ['tercero' => $tercero, 'cuentas' => $this->cuentas()]);
    }

    public function update(TerceroRequest $request, Tercero $tercero)
    {
        $tercero->update($request->validated());

        return redirect()->route('terceros.index')->with('success', "{$tercero->razon_social} actualizado.");
    }

    public function destroy(Request $request, Tercero $tercero)
    {
        abort_unless($request->user()->puede('contador'), 403);

        if ($tercero->comprobantes()->exists()) {
            return back()->with('error', 'Tiene comprobantes cargados: no se puede eliminar. Puede desactivarlo.');
        }

        $tercero->delete();

        return redirect()->route('terceros.index')->with('success', 'Eliminado.');
    }

    private function cuentas()
    {
        return Cuenta::planOrdenado(Cuenta::where('imputable', true)->where('activa', true));
    }
}
