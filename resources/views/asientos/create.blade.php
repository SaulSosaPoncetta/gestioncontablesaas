@extends('layouts.app')

@section('title', 'Nuevo asiento')
@section('header', 'Nuevo asiento')

@section('content')
<form method="POST" action="{{ route('asientos.store') }}" autocomplete="off">
    @csrf
    @include('asientos._form')
</form>
@endsection
