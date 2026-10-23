@extends('layouts.app')

@section('title', 'Nuevo ejercicio')
@section('header', 'Nuevo ejercicio')

@section('content')
<form method="POST" action="{{ route('ejercicios.store') }}">
    @csrf
    @include('ejercicios._form')

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ route('ejercicios.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
