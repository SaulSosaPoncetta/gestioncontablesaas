@extends('layouts.app')

@section('title', $circuito === 'venta' ? 'Nueva venta' : 'Nueva compra')
@section('header', $circuito === 'venta' ? 'Nuevo comprobante de venta' : 'Nuevo comprobante de compra')

@section('content')
<form method="POST" action="{{ route('comprobantes.store', $circuito) }}" autocomplete="off">
    @csrf
    @include('comprobantes._form')
</form>
@endsection
