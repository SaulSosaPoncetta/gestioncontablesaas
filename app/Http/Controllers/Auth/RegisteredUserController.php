<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\User;
use App\Rules\Cuit;
use App\Services\AltaEmpresa;
use App\Services\HubClientes;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * Registro público de un nuevo cliente SaaS:
 * crea la empresa, su usuario administrador y arranca el período de prueba.
 */
class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless(config('saas.registro_abierto'), 404);

        $planes = Plan::where('activo', true)->orderBy('orden')->get();

        return view('auth.register', [
            'planes'       => $planes,
            'planElegido'  => (int) $request->query('plan', $planes->get(1)?->id ?? $planes->first()?->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('saas.registro_abierto'), 404);

        $request->merge(['cuit' => preg_replace('/\D/', '', (string) $request->cuit)]);

        $request->validate([
            'razon_social'  => ['required', 'string', 'max:255'],
            'cuit'          => ['required', 'digits:11', new Cuit, Rule::unique('empresas', 'cuit')],
            'condicion_iva' => ['required', Rule::in(array_keys(Empresa::CONDICIONES_IVA))],
            'mes_cierre'    => ['required', 'integer', 'between:1,12'],
            'plan_id'       => ['required', Rule::exists('planes', 'id')->where('activo', true)],
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password'      => ['required', 'confirmed', Rules\Password::defaults()],
            'terminos'      => ['accepted'],
        ], [
            'cuit.unique'       => 'Ya existe una cuenta registrada con ese CUIT. Contáctenos si necesita recuperar el acceso.',
            'terminos.accepted' => 'Debe aceptar los términos del servicio.',
        ], [
            'razon_social'  => 'razón social',
            'cuit'          => 'CUIT',
            'condicion_iva' => 'condición frente al IVA',
            'mes_cierre'    => 'mes de cierre',
            'name'          => 'nombre',
            'password'      => 'contraseña',
        ]);

        $empresa = null;

        $user = DB::transaction(function () use ($request, &$empresa) {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => $request->password,
            ]);

            $empresa = AltaEmpresa::crear(
                [
                    'razon_social'   => $request->razon_social,
                    'cuit'           => $request->cuit,
                    'condicion_iva'  => $request->condicion_iva,
                    'condicion_iibb' => 'local',
                    'mes_cierre'     => (int) $request->mes_cierre,
                    'email'          => $request->email,
                ],
                Plan::findOrFail($request->plan_id),
                $user
            );

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);

        // Alta en MiGestión Panel (si ya usa GestiónComercial, el hub vincula los dos sistemas solo)
        dispatch(fn () => app(HubClientes::class)->registrar($empresa, $user->email))->afterResponse();

        return redirect()->route('dashboard')->with('success',
            '¡Bienvenido! Su cuenta quedó creada con ' . config('saas.dias_prueba') . ' días de prueba gratis. '
            . 'Ya tiene cargado el plan de cuentas modelo y el ejercicio vigente.'
        );
    }
}
