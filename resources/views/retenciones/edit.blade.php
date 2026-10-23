@extends('layouts.app')

@section('title', 'Editar retención')
@section('header', 'Editar retención ' . ($retencion->numero_certificado ?? ''))

@section('content')
<form method="POST" action="{{ route('retenciones.update', [$tipo, $retencion]) }}" autocomplete="off">
    @csrf
    @method('PUT')
    @include('retenciones._form')
</form>
@endsection
