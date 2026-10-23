@extends('layouts.app')

@section('title', 'Editar ' . $comprobante->descripcion_corta)
@section('header', 'Editar ' . $comprobante->descripcion_corta)

@section('content')
<div class="alert alert-info py-2 small">
    <i class="bi bi-info-circle me-1"></i>
    Al guardar los cambios se regenera el asiento N° {{ $comprobante->asiento?->numero }} (conserva su número).
</div>

<form method="POST" action="{{ route('comprobantes.update', [$circuito, $comprobante]) }}" autocomplete="off">
    @csrf
    @method('PUT')
    @include('comprobantes._form')
</form>
@endsection
