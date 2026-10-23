@extends('layouts.app')

@section('title', 'Editar ejercicio')
@section('header', 'Editar ejercicio N° ' . $ejercicio->numero)

@section('content')
<form method="POST" action="{{ route('ejercicios.update', $ejercicio) }}">
    @csrf
    @method('PUT')
    @include('ejercicios._form')

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
        <a href="{{ route('ejercicios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
