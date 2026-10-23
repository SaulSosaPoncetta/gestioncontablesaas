<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Usuarios de la empresa cliente. Solo el administrador de la empresa los gestiona.
 * La cantidad máxima depende del plan contratado.
 */
class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $this->autorizar($request);
        $empresa = app('empresa_activa');

        return view('usuarios.index', [
            'usuarios'     => $empresa->usuarios()->orderBy('name')->get(),
            'puedeAgregar' => $empresa->puedeAgregarUsuario(),
            'maxUsuarios'  => $empresa->plan?->max_usuarios,
        ]);
    }

    public function create(Request $request)
    {
        $this->autorizar($request);

        if (! app('empresa_activa')->puedeAgregarUsuario()) {
            return redirect()->route('usuarios.index')
                ->with('warning', 'Alcanzó el máximo de usuarios de su plan. Mejore el plan para agregar más.');
        }

        return view('usuarios.create', ['usuario' => new User(['activo' => true]), 'rol' => 'operador']);
    }

    public function store(Request $request)
    {
        $this->autorizar($request);
        $empresa = app('empresa_activa');

        if (! $empresa->puedeAgregarUsuario()) {
            return redirect()->route('usuarios.index')
                ->with('warning', 'Alcanzó el máximo de usuarios de su plan.');
        }

        $datos = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'lowercase', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'rol'      => ['required', Rule::in(array_keys(User::ROLES))],
        ], [], ['name' => 'nombre', 'password' => 'contraseña']);

        DB::transaction(function () use ($datos, $empresa) {
            $usuario = User::create([
                'name'     => $datos['name'],
                'email'    => $datos['email'],
                'password' => $datos['password'],
                'activo'   => true,
            ]);
            $empresa->usuarios()->attach($usuario->id, ['rol' => $datos['rol']]);
        });

        return redirect()->route('usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(Request $request, User $usuario)
    {
        $this->autorizar($request);
        $usuario = $this->usuarioDeLaEmpresa($usuario);

        return view('usuarios.edit', ['usuario' => $usuario, 'rol' => $usuario->pivot->rol]);
    }

    public function update(Request $request, User $usuario)
    {
        $this->autorizar($request);
        $usuario = $this->usuarioDeLaEmpresa($usuario);
        $esUnoMismo = $usuario->id === $request->user()->id;

        $datos = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'lowercase', 'max:255', Rule::unique('users', 'email')->ignore($usuario->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'rol'      => [$esUnoMismo ? 'nullable' : 'required', Rule::in(array_keys(User::ROLES))],
            'activo'   => ['nullable', 'boolean'],
        ], [], ['name' => 'nombre', 'password' => 'contraseña']);

        $usuario->name = $datos['name'];
        $usuario->email = $datos['email'];
        if (! empty($datos['password'])) {
            $usuario->password = $datos['password'];
        }
        // Un administrador no puede desactivarse ni quitarse el rol a sí mismo
        if (! $esUnoMismo) {
            $usuario->activo = $request->boolean('activo');
            app('empresa_activa')->usuarios()->updateExistingPivot($usuario->id, ['rol' => $datos['rol']]);
        }
        $usuario->save();

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado.');
    }

    private function autorizar(Request $request): void
    {
        abort_unless($request->user()->puede('administrador'), 403);
    }

    private function usuarioDeLaEmpresa(User $usuario): User
    {
        return app('empresa_activa')->usuarios()->whereKey($usuario->id)->firstOrFail();
    }
}
