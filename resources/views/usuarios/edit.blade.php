@extends('layouts.app')

@section('title', 'Editar usuario')
@section('header', 'Editar usuario: ' . $usuario->name)

@section('content')
<form method="POST" action="{{ route('usuarios.update', $usuario) }}">
    @csrf
    @method('PUT')
    @include('usuarios._form')

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
