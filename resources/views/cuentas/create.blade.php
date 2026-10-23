@extends('layouts.app')

@section('title', 'Nueva cuenta')
@section('header', 'Nueva cuenta')

@section('content')
<form method="POST" action="{{ route('cuentas.store') }}">
    @csrf
    @include('cuentas._form')

    <div class="d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
        <a href="{{ route('cuentas.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
@endsection
