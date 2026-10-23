@extends('layouts.app')

@section('title', 'Editar asiento')
@section('header', $asiento->numero ? 'Editar asiento N° ' . $asiento->numero : 'Editar borrador')

@section('content')
@if($asiento->estado === 'registrado')
    <div class="alert alert-info py-2 small">
        <i class="bi bi-info-circle me-1"></i>
        Está modificando un asiento registrado. Conserva su número y debe seguir balanceado.
    </div>
@endif

<form method="POST" action="{{ route('asientos.update', $asiento) }}" autocomplete="off">
    @csrf
    @method('PUT')
    @include('asientos._form')
</form>
@endsection
