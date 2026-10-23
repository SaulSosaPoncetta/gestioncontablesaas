<?php

namespace App\Http\Controllers;

use App\Models\RegimenRetencion;
use App\Services\AsientoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Regímenes de retención en los que la empresa actúa como agente */
class RegimenController extends Controller
{
    public function index()
    {
        return view('regimenes.index', [
            'regimenes' => RegimenRetencion::withCount('retenciones')->orderBy('impuesto')->orderBy('codigo')->get(),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($request->user()->puede('contador'), 403);

        return view('regimenes.create', ['regimen' => new RegimenRetencion(['impuesto' => 'ganancias', 'acumula_mensual' => true, 'activo' => true])]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->puede('contador'), 403);
        RegimenRetencion::create($this->validar($request));

        return redirect()->route('regimenes.index')->with('success', 'Régimen creado.');
    }

    public function edit(Request $request, RegimenRetencion $regimen)
    {
        abort_unless($request->user()->puede('contador'), 403);

        return view('regimenes.edit', compact('regimen'));
    }

    public function update(Request $request, RegimenRetencion $regimen)
    {
        abort_unless($request->user()->puede('contador'), 403);
        $regimen->update($this->validar($request));

        return redirect()->route('regimenes.index')->with('success', 'Régimen actualizado. Los cambios aplican a las retenciones nuevas.');
    }

    /** Carga los regímenes habituales (solo los que no existan por impuesto + código) */
    public function cargarHabituales(Request $request)
    {
        abort_unless($request->user()->puede('contador'), 403);

        $creados = 0;
        foreach (RegimenRetencion::HABITUALES as [$imp, $cod, $desc, $ai, $ani, $mns, $min, $acum]) {
            $r = RegimenRetencion::firstOrCreate(
                ['impuesto' => $imp, 'codigo' => $cod],
                ['descripcion' => $desc, 'alicuota_inscripto' => $ai, 'alicuota_no_inscripto' => $ani,
                 'minimo_no_sujeto' => $mns, 'minimo_retencion' => $min, 'acumula_mensual' => $acum, 'activo' => true]
            );
            $creados += $r->wasRecentlyCreated ? 1 : 0;
        }

        return redirect()->route('regimenes.index')->with('warning',
            "Se cargaron {$creados} regímenes de referencia. IMPORTANTE: complete los mínimos no sujetos y verifique las alícuotas vigentes antes de usarlos.");
    }

    private function validar(Request $request): array
    {
        foreach (['alicuota_inscripto', 'alicuota_no_inscripto', 'minimo_no_sujeto', 'minimo_retencion'] as $campo) {
            $request->merge([$campo => AsientoService::aCentavos($request->input($campo, '')) / 100]);
        }

        $datos = $request->validate([
            'impuesto'              => ['required', Rule::in(array_keys(RegimenRetencion::IMPUESTOS))],
            'codigo'                => ['required', 'string', 'max:10'],
            'descripcion'           => ['required', 'string', 'max:255'],
            'alicuota_inscripto'    => ['numeric', 'min:0', 'max:100'],
            'alicuota_no_inscripto' => ['numeric', 'min:0', 'max:100'],
            'minimo_no_sujeto'      => ['numeric', 'min:0'],
            'minimo_retencion'      => ['numeric', 'min:0'],
        ], [], ['codigo' => 'código', 'descripcion' => 'descripción']);

        return $datos + [
            'acumula_mensual' => $request->boolean('acumula_mensual'),
            'activo'          => $request->boolean('activo'),
        ];
    }
}
