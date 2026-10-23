@extends('layouts.app')

@section('title', $tipo === 'practicada' ? 'Nueva retención' : 'Cargar certificado')
@section('header', $tipo === 'practicada' ? 'Nueva retención practicada' : 'Cargar certificado de retención sufrida')

@section('content')
<form method="POST" action="{{ route('retenciones.store', $tipo) }}" autocomplete="off">
    @csrf
    @include('retenciones._form')
</form>
@endsection
