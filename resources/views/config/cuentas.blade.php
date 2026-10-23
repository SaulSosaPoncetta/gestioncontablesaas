@extends('layouts.app')

@section('title', 'Cuentas automáticas')
@section('header', 'Cuentas automáticas')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
@endpush

@section('content')
<p class="text-muted small">
    Son las cuentas que usa el sistema al generar los asientos de los comprobantes de compra y venta.
    Si deja una vacía, se usa la cuenta del plan modelo con el código indicado (si existe en su plan).
</p>

<form method="POST" action="{{ route('config.cuentas.update') }}">
    @csrf
    @method('PUT')

    @foreach($grupos as $grupo => $items)
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">{{ $grupo }}</div>
            <div class="card-body">
                @foreach($items as $item)
                    <div class="row g-2 align-items-center mb-2">
                        <div class="col-md-5">
                            <div>{{ $item->descripcion }}</div>
                            <div class="small">
                                @if($item->efectiva)
                                    <span class="text-success"><i class="bi bi-check-circle"></i> Usa: {{ $item->efectiva->codigo }} - {{ $item->efectiva->nombre }}</span>
                                @else
                                    <span class="text-danger"><i class="bi bi-exclamation-circle"></i> Sin cuenta: configúrela para poder generar asientos</span>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-7">
                            <select name="cuentas[{{ $item->clave }}]" class="form-select form-select-sm cuenta-select">
                                <option value="">Por defecto (plan modelo {{ $item->codigoModelo }})</option>
                                @foreach($cuentas as $c)
                                    <option value="{{ $c->id }}" @selected($item->cuenta_id == $c->id)>{{ $c->codigo }} - {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
</form>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    document.querySelectorAll('.cuenta-select').forEach(el => new TomSelect(el, { maxOptions: 80 }));
</script>
@endpush
