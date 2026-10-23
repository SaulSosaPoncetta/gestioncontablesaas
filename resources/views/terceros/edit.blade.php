@extends('layouts.app')

@section('title', 'Editar ' . $tercero->razon_social)
@section('header', $tercero->razon_social)

@section('content')
<form method="POST" action="{{ route('terceros.update', $tercero) }}">
    @csrf
    @method('PUT')
    @include('terceros._form')

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
        <a href="{{ route('terceros.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
