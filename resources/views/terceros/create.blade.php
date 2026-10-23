@extends('layouts.app')

@section('title', 'Nuevo ' . ($tercero->tipo === 'proveedor' ? 'proveedor' : 'cliente'))
@section('header', 'Nuevo ' . ($tercero->tipo === 'proveedor' ? 'proveedor' : 'cliente'))

@section('content')
<form method="POST" action="{{ route('terceros.store') }}">
    @csrf
    <input type="hidden" name="volver" value="{{ old('volver', request('volver')) }}">
    @include('terceros._form')

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ str_starts_with((string) request('volver'), url('/')) ? request('volver') : route('terceros.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
