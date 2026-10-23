@extends('layouts.app')

@section('title', 'Nuevo régimen')
@section('header', 'Nuevo régimen de retención')

@section('content')
<form method="POST" action="{{ route('regimenes.store') }}">
    @csrf
    @include('regimenes._form')
    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ route('regimenes.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
