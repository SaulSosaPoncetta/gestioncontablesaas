@extends('layouts.app')

@section('title', 'Editar cuenta')
@section('header', 'Editar cuenta ' . $cuenta->codigo)

@section('content')
<form method="POST" action="{{ route('cuentas.update', $cuenta) }}">
    @csrf
    @method('PUT')
    @include('cuentas._form')

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
        <a href="{{ route('cuentas.index') }}#c{{ $cuenta->id }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
