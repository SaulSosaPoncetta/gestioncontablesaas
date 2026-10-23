@extends('layouts.app')

@section('title', 'Mi empresa')
@section('header', 'Mi empresa')

@section('content')
<form method="POST" action="{{ route('empresa.update') }}">
    @csrf
    @method('PUT')
    @include('empresa._form')

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
