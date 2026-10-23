@extends('layouts.app')

@section('title', 'Sumas y Saldos')
@section('header', 'Libros y balances')

@push('styles')
<style>
    .ss td { padding-top: .25rem; padding-bottom: .25rem; }
    .ss .n1 td { font-weight: 700; background: #eef2f7; }
    .ss .n2 td { font-weight: 600; }
    .ss .agrupadora td { color: #495057; }
</style>
@endpush

@section('content')
@include('libros._nav')

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Nivel de detalle</label>
                <select name="nivel" class="form-select form-select-sm">
                    @for($n = 1; $n <= $maxNivel; $n++)
                        <option value="{{ $n }}" @selected($nivel === $n)>Nivel {{ $n }}{{ $n === $maxNivel ? ' (analítico)' : '' }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-6 col-md-3">
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="sin_movimiento" value="1" id="sinmov" @checked(request()->boolean('sin_movimiento'))>
                    <label class="form-check-label small" for="sinmov">Incluir cuentas sin movimientos</label>
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Consultar</button>
            </div>
        </form>
    </div>
</div>

@php
    $f2 = fn ($v) => $v != 0 ? number_format($v, 2, ',', '.') : '';
    $balanceaSumas = round($totales['debe'] - $totales['haber'], 2) == 0;
    $balanceaSaldos = round($totales['deudor'] - $totales['acreedor'], 2) == 0;
@endphp

@if(! $balanceaSumas || ! $balanceaSaldos)
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>El balance no cierra. Revise los asientos del período.</div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle ss">
            <thead class="table-light">
                <tr>
                    <th rowspan="2" style="width: 130px;" class="align-middle">Código</th>
                    <th rowspan="2" class="align-middle">Cuenta</th>
                    <th colspan="2" class="text-center border-start">Sumas</th>
                    <th colspan="2" class="text-center border-start">Saldos</th>
                </tr>
                <tr>
                    <th class="text-end border-start" style="width: 140px;">Debe</th>
                    <th class="text-end" style="width: 140px;">Haber</th>
                    <th class="text-end border-start" style="width: 140px;">Deudor</th>
                    <th class="text-end" style="width: 140px;">Acreedor</th>
                </tr>
            </thead>
            <tbody>
            @forelse($filas as $f)
                <tr class="n{{ $f->cuenta->nivel }} {{ ! $f->hoja ? 'agrupadora' : '' }}">
                    <td class="font-monospace small">{{ $f->cuenta->codigo }}</td>
                    <td style="padding-left: {{ 0.5 + ($f->cuenta->nivel - 1) * 1 }}rem;">
                        @if($f->cuenta->imputable)
                            <a href="{{ route('libros.mayor', ['cuenta' => $f->cuenta->id, 'desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')]) }}"
                               class="text-decoration-none text-reset">{{ $f->cuenta->nombre }}</a>
                        @else
                            {{ $f->cuenta->nombre }}
                        @endif
                    </td>
                    <td class="num border-start">{{ $f2($f->debe) }}</td>
                    <td class="num">{{ $f2($f->haber) }}</td>
                    <td class="num border-start">{{ $f2($f->deudor) }}</td>
                    <td class="num">{{ $f2($f->acreedor) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No hay movimientos registrados en el período.</td></tr>
            @endforelse
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="2" class="text-end">TOTALES</td>
                    <td class="num border-start {{ $balanceaSumas ? '' : 'text-danger' }}">{{ number_format($totales['debe'], 2, ',', '.') }}</td>
                    <td class="num {{ $balanceaSumas ? '' : 'text-danger' }}">{{ number_format($totales['haber'], 2, ',', '.') }}</td>
                    <td class="num border-start {{ $balanceaSaldos ? '' : 'text-danger' }}">{{ number_format($totales['deudor'], 2, ',', '.') }}</td>
                    <td class="num {{ $balanceaSaldos ? '' : 'text-danger' }}">{{ number_format($totales['acreedor'], 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<p class="small text-muted mt-2">Hacé clic en una cuenta imputable para ver su mayor. Solo se computan asientos registrados.</p>
@endsection
