@extends('layouts.app')

@section('title', 'Nuevo plan')
@section('header', 'Nuevo plan')

@section('content')
<form method="POST" action="{{ route('admin.planes.store') }}">
    @csrf
    @include('admin.planes._form')

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ route('admin.planes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
