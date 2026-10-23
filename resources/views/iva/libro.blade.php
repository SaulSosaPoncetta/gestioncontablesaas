@extends('layouts.app')

@section('title', $circuito === 'venta' ? 'Libro IVA Ventas' : 'Libro IVA Compras')
@section('header', 'Libros IVA')

@push('styles')
<style>
    .libro-iva { font-size: .82rem; }
    .libro-iva th { white-space: nowrap; font-size: .72rem; }
    .libro-iva td { white-space: nowrap; padding: .25rem .4rem; }
    .libro-iva .razon { white-space: normal; min-width: 180px; }
    .libro-iva .neg { color: #dc3545; }
</style>
@endpush

@section('content')
@include('iva._nav')

@php
    $m = fn ($v) => round($v, 2) == 0 ? '' : number_format($v, 2, ',', '.');
    $mt = fn ($v) => number_format($v, 2, ',', '.');
    $esVenta = $circuito === 'venta';
@endphp

<div class="d-flex justify-content-between align-items-center mb-2">
    <div>
        <span class="fw-semibold">Libro IVA {{ $esVenta ? 'Ventas' : 'Compras' }}</span>
        <span class="text-muted">· período {{ \Carbon\Carbon::createFromFormat('Y-m-d', $periodo . '-01')->translatedFormat('m/Y') }}</span>
        · {{ $filas->count() }} comprobantes
    </div>
    @if($cerrado)
        <span class="badge text-bg-secondary"><i class="bi bi-lock-fill"></i> Período cerrado</span>
    @else
        <span class="badge text-bg-success">Período abierto</span>
    @endif
</div>

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 libro-iva align-middle">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Comprobante</th>
                    <th>{{ $esVenta ? 'Cliente' : 'Proveedor' }}</th>
                    <th>CUIT / Doc.</th>
                    <th class="text-end">Neto gravado</th>
                    <th class="text-end">IVA 21%</th>
                    <th class="text-end">IVA 10,5%</th>
                    <th class="text-end">IVA otras</th>
                    <th class="text-end">No grav.</th>
                    <th class="text-end">Exento</th>
                    <th class="text-end">Perc. IVA</th>
                    <th class="text-end">Perc. IIBB</th>
                    <th class="text-end">Otros</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
            @forelse($filas as $f)
                @php $c = $f->comprobante; @endphp
                <tr class="{{ $f->total < 0 ? 'neg' : '' }}" style="cursor: pointer;" onclick="location='{{ route('comprobantes.show', [$circuito, $c]) }}'">
                    <td>{{ $c->fecha->format('d/m/Y') }}</td>
                    <td class="font-monospace">{{ $c->descripcion_corta }}</td>
                    <td class="razon">{{ $c->tercero->razon_social }}</td>
                    <td>{{ $c->tercero->nro_documento }}</td>
                    <td class="num">{{ $m($f->neto) }}</td>
                    <td class="num">{{ $m($f->iva21) }}</td>
                    <td class="num">{{ $m($f->iva105) }}</td>
                    <td class="num">{{ $m($f->iva_otras) }}</td>
                    <td class="num">{{ $m($f->no_gravado) }}</td>
                    <td class="num">{{ $m($f->exento) }}</td>
                    <td class="num">{{ $m($f->percepcion_iva) }}</td>
                    <td class="num">{{ $m($f->percepcion_iibb) }}</td>
                    <td class="num">{{ $m($f->otros) }}</td>
                    <td class="num fw-semibold">{{ $mt($f->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="14" class="text-center text-muted py-4">No hay comprobantes registrados en el período.</td></tr>
            @endforelse
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td colspan="4" class="text-end">TOTALES</td>
                    <td class="num">{{ $mt($totales['neto']) }}</td>
                    <td class="num">{{ $mt($totales['iva21']) }}</td>
                    <td class="num">{{ $mt($totales['iva105']) }}</td>
                    <td class="num">{{ $mt($totales['iva_otras']) }}</td>
                    <td class="num">{{ $mt($totales['no_gravado']) }}</td>
                    <td class="num">{{ $mt($totales['exento']) }}</td>
                    <td class="num">{{ $mt($totales['percepcion_iva']) }}</td>
                    <td class="num">{{ $mt($totales['percepcion_iibb']) }}</td>
                    <td class="num">{{ $mt($totales['otros']) }}</td>
                    <td class="num">{{ $mt($totales['total']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6 col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-semibold small">Resumen por alícuota</div>
            <table class="table table-sm mb-0 small">
                <thead class="table-light"><tr><th>Alícuota</th><th class="text-end">Neto gravado</th><th class="text-end">IVA</th></tr></thead>
                <tbody>
                @forelse($alicuotas as $id => $a)
                    <tr>
                        <td>{{ str_replace('.', ',', \App\Support\Afip::ALICUOTAS[$id] ?? 0) }} %</td>
                        <td class="num">{{ $mt($a['neto']) }}</td>
                        <td class="num">{{ $mt($a['iva']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted text-center">Sin operaciones gravadas</td></tr>
                @endforelse
                </tbody>
                <tfoot class="fw-semibold">
                    <tr><td>Total</td><td class="num">{{ $mt($totales['neto']) }}</td><td class="num">{{ $mt($totales['iva21'] + $totales['iva105'] + $totales['iva_otras']) }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>
    <div class="col-md-6 col-lg-8 small text-muted">
        <p class="mb-1">Se incluyen los comprobantes registrados con período IVA {{ $periodo }} (los anulados no se computan). Las notas de crédito restan y se muestran en rojo.</p>
        <p class="mb-0">Hacé clic en una fila para abrir el comprobante.</p>
    </div>
</div>
@endsection
