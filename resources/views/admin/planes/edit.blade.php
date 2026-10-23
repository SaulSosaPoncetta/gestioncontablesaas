@extends('layouts.app')

@section('title', 'Editar plan')
@section('header', 'Editar plan: ' . $plan->nombre)

@section('content')
<form method="POST" action="{{ route('admin.planes.update', $plan) }}">
    @csrf
    @method('PUT')
    @include('admin.planes._form')

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
        <a href="{{ route('admin.planes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
