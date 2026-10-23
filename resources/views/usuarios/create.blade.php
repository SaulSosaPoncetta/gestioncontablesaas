@extends('layouts.app')

@section('title', 'Nuevo usuario')
@section('header', 'Nuevo usuario')

@section('content')
<form method="POST" action="{{ route('usuarios.store') }}">
    @csrf
    @include('usuarios._form')

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
