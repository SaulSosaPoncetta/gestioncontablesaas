@extends('layouts.app')

@section('title', 'Editar régimen')
@section('header', 'Régimen ' . $regimen->codigo)

@section('content')
<form method="POST" action="{{ route('regimenes.update', $regimen) }}">
    @csrf
    @method('PUT')
    @include('regimenes._form')
    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
        <a href="{{ route('regimenes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
