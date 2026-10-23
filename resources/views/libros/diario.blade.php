@extends('layouts.app')

@section('title', 'Libro Diario')
@section('header', 'Libros y balances')

@push('styles')
<style>
    .diario td { padding-top: .25rem; padding-bottom: .25rem; }
    .diario .cab td { background: #eef2f7; border-top: 2px solid #c9d3e0; }
    .diario .anulado td { color: #adb5bd; }
    .diario .haber-cuenta { padding-left: 2rem !important; }
</style>
@endpush

@section('content')
@include('libros._nav')

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Consultar</button>
            </div>
            <div class="col text-md-end small text-muted">
                {{ $totales['asientos'] }} asientos registrados ·
                Debe $ {{ number_format($totales['debe'], 2, ',', '.') }} ·
                Haber $ {{ number_format($totales['haber'], 2, ',', '.') }}
                @if(round($totales['debe'] - $totales['haber'], 2) != 0)
                    <span class="text-danger fw-semibold">· ¡No balancea!</span>
                @endif
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm mb-0 diario">
            <thead class="table-light">
                <tr>
                    <th style="width: 130px;">Código</th>
                    <th>Cuenta / Detalle</th>
                    <th class="text-end" style="width: 150px;">Debe</th>
                    <th class="text-end" style="width: 150px;">Haber</th>
                </tr>
            </thead>
            <tbody>
            @forelse($asientos as $a)
                <tr class="cab {{ $a->estaAnulado() ? 'anulado' : '' }}">
                    <td colspan="4">
                        <a href="{{ route('asientos.show', $a) }}" class="fw-semibold text-decoration-none">Asiento N° {{ $a->numero }}</a>
                        <span class="ms-2">{{ $a->fecha->format('d/m/Y') }}</span>
                        <span class="ms-2 text-muted">{{ $a->concepto }}</span>
                        @if($a->estaAnulado())<span class="badge text-bg-secondary ms-2">ANULADO</span>@endif
                    </td>
                </tr>
                @unless($a->estaAnulado())
                    @foreach($a->lineas as $l)
                        <tr>
                            <td class="font-monospace text-muted small">{{ $l->cuenta->codigo }}</td>
                            <td class="{{ (float) $l->haber > 0 ? 'haber-cuenta' : '' }}">
                                {{ (float) $l->haber > 0 ? 'a ' : '' }}{{ $l->cuenta->nombre }}
                                @if($l->detalle)<span class="small text-muted"> — {{ $l->detalle }}</span>@endif
                            </td>
                            <td class="num">{{ (float) $l->debe > 0 ? number_format($l->debe, 2, ',', '.') : '' }}</td>
                            <td class="num">{{ (float) $l->haber > 0 ? number_format($l->haber, 2, ',', '.') : '' }}</td>
                        </tr>
                    @endforeach
                @endunless
            @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No hay asientos registrados en el período.</td></tr>
            @endforelse
            </tbody>
            @if($asientos->isNotEmpty() && ! $asientos->hasMorePages())
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td colspan="2" class="text-end">Totales del período</td>
                        <td class="num">{{ number_format($totales['debe'], 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($totales['haber'], 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    @if($asientos->hasPages())
        <div class="card-body">{{ $asientos->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
