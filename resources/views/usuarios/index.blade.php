@extends('layouts.app')

@section('title', 'Usuarios')
@section('header', 'Usuarios de la empresa')

@section('actions')
    @if($puedeAgregar)
        <a href="{{ route('usuarios.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nuevo usuario</a>
    @else
        <a href="{{ route('suscripcion.show') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-lock me-1"></i>Límite del plan alcanzado</a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="card-body py-2 small text-muted border-bottom">
        {{ $usuarios->count() }} de {{ $maxUsuarios ?? 'ilimitados' }} usuarios disponibles en su plan.
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
            @foreach($usuarios as $u)
                <tr>
                    <td class="fw-semibold">
                        {{ $u->name }}
                        @if($u->id === auth()->id())<span class="badge text-bg-light border ms-1">Usted</span>@endif
                    </td>
                    <td>{{ $u->email }}</td>
                    <td>{{ \App\Models\User::ROLES[$u->pivot->rol] ?? $u->pivot->rol }}</td>
                    <td>
                        @if($u->activo)
                            <span class="badge text-bg-success">Activo</span>
                        @else
                            <span class="badge text-bg-secondary">Inactivo</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('usuarios.edit', $u) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card mt-3">
    <div class="card-body small">
        <div class="fw-semibold mb-2">Permisos por rol</div>
        <ul class="mb-0">
            <li><strong>Administrador:</strong> todo, incluidos datos de la empresa, usuarios y cierre de ejercicios.</li>
            <li><strong>Contador:</strong> carga y modificación de asientos, ejercicios, plan de cuentas e impuestos.</li>
            <li><strong>Operador:</strong> carga de comprobantes y asientos.</li>
            <li><strong>Solo consulta:</strong> ve libros y reportes, no modifica nada.</li>
        </ul>
    </div>
</div>
@endsection
