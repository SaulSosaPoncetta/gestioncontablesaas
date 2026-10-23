@extends('layouts.app')

@section('title', 'Situación Patrimonial')
@section('header', 'Estados contables')

@section('content')
@include('estados._nav')

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Al día</label>
                <input type="date" name="fecha" value="{{ $fecha->format('Y-m-d') }}"
                       min="{{ $ejercicio->fecha_inicio->format('Y-m-d') }}" max="{{ $ejercicio->fecha_cierre->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Detalle</label>
                <select name="nivel" class="form-select form-select-sm">
                    <option value="2" @selected($nivel === 2)>Resumido (corriente / no corriente)</option>
                    <option value="3" @selected($nivel === 3)>Por rubros</option>
                    <option value="4" @selected($nivel === 4)>Analítico (cuentas)</option>
                </select>
            </div>
            <div class="col-md-3">
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="comparativo" value="1" id="comp" @checked(request()->boolean('comparativo'))>
                    <label class="form-check-label small" for="comp">Comparativo con el ejercicio anterior</label>
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Consultar</button>
            </div>
        </form>
        @if(request()->boolean('comparativo') && ! $anterior)
            <div class="small text-warning mt-2"><i class="bi bi-info-circle"></i> No hay un ejercicio anterior cargado para comparar.</div>
        @endif
    </div>
</div>

@if($diferencia != 0)
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-1"></i>
        El Activo no es igual al Pasivo + Patrimonio Neto (diferencia $ {{ number_format($diferencia, 2, ',', '.') }}).
        Revise asientos con cuentas de orden o cuentas fuera de los rubros 1 a 5.
    </div>
@endif

@php
    $comp = $anterior !== null;
    $m = fn ($v) => $v === null ? '' : number_format($v, 2, ',', '.');
@endphp

<div class="card estado">
    <div class="card-header bg-white text-center">
        <div class="fw-semibold">ESTADO DE SITUACIÓN PATRIMONIAL</div>
        <div class="small text-muted">al {{ $fecha->format('d/m/Y') }} · en pesos</div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Concepto</th>
                    <th class="text-end" style="width: 170px;">{{ $fecha->format('d/m/Y') }}</th>
                    @if($comp)<th class="text-end" style="width: 170px;">{{ $anterior->fecha_cierre->format('d/m/Y') }}</th>@endif
                </tr>
            </thead>
            <tbody>
            @foreach(['1', '2', '3'] as $raiz)
                @php $s = $secciones[$raiz]; @endphp
                <tr class="seccion"><td colspan="{{ $comp ? 3 : 2 }}">{{ $s['titulo'] }}</td></tr>
                @include('estados._filas', ['filas' => $s['filas'], 'comparativo' => $comp])
                <tr class="fila-subtotal">
                    <td>TOTAL {{ $s['titulo'] }}</td>
                    <td class="num">{{ $m($s['total']) }}</td>
                    @if($comp)<td class="num col-ant">{{ $m($s['anterior']) }}</td>@endif
                </tr>
                @if($raiz === '2')<tr><td colspan="{{ $comp ? 3 : 2 }}" class="border-0 py-2"></td></tr>@endif
            @endforeach
                <tr class="gran-total">
                    <td>TOTAL PASIVO + PATRIMONIO NETO</td>
                    <td class="num">{{ $m($pasivoMasPn) }}</td>
                    @if($comp)<td class="num col-ant">{{ $m($pasivoMasPnAnt) }}</td>@endif
                </tr>
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2">Incluye los asientos registrados hasta la fecha, sin refundición ni cierre. El resultado del ejercicio se calcula con las cuentas de resultado (4 y 5).</p>
@endsection
