@extends('layouts.app')

@php
    $esVenta = $circuito === 'venta';
    $m = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp

@section('title', $comprobante->descripcion_corta)
@section('header')
    {{ \App\Support\Afip::descripcion($comprobante->tipo_comprobante) }} {{ $comprobante->numero_completo }}
    @if($comprobante->estaAnulado())<span class="badge text-bg-secondary fs-6 align-middle">Anulado</span>@endif
@endsection

@section('actions')
    @if(auth()->user()->puede('operador') && $comprobante->esEditable())
        <a href="{{ route('comprobantes.edit', [$circuito, $comprobante]) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>Editar</a>
    @endif
    @if(auth()->user()->puede('contador') && $comprobante->esEditable())
        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalAnular"><i class="bi bi-x-circle me-1"></i>Anular</button>
    @endif
    <a href="{{ route('comprobantes.index', $circuito) }}" class="btn btn-outline-secondary btn-sm">Volver</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">{{ $esVenta ? 'Cliente' : 'Proveedor' }}</div>
                <div class="fw-semibold fs-6">{{ $comprobante->tercero->razon_social }}</div>
                <div class="small text-muted mb-3">{{ $comprobante->tercero->documento_formateado }} · {{ $comprobante->tercero->condicion_iva_texto }}</div>

                <dl class="row small mb-0">
                    <dt class="col-5 fw-normal text-muted">Fecha</dt><dd class="col-7">{{ $comprobante->fecha->format('d/m/Y') }}</dd>
                    <dt class="col-5 fw-normal text-muted">Período IVA</dt><dd class="col-7">{{ \Carbon\Carbon::createFromFormat('Y-m', $comprobante->periodo_iva)->format('m/Y') }}</dd>
                    @if($comprobante->cae)
                        <dt class="col-5 fw-normal text-muted">CAE</dt><dd class="col-7 font-monospace">{{ $comprobante->cae }} @if($comprobante->vto_cae)(vto. {{ $comprobante->vto_cae->format('d/m/Y') }})@endif</dd>
                    @endif
                    <dt class="col-5 fw-normal text-muted">Imputación</dt><dd class="col-7">{{ $comprobante->cuentaImputacion?->codigo_nombre }}</dd>
                    @if($comprobante->observaciones)
                        <dt class="col-5 fw-normal text-muted">Observaciones</dt><dd class="col-7">{{ $comprobante->observaciones }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <table class="table table-sm mb-0">
                <tbody>
                @foreach($comprobante->alicuotas as $a)
                    <tr><td>Neto gravado {{ str_replace('.', ',', $a->porcentaje) }}%</td><td class="num">{{ $m($a->neto) }}</td></tr>
                @endforeach
                @if((float) $comprobante->no_gravado)<tr><td>{{ $comprobante->alicuotas->isEmpty() ? 'Importe (sin IVA discriminado)' : 'No gravado' }}</td><td class="num">{{ $m($comprobante->no_gravado) }}</td></tr>@endif
                @if((float) $comprobante->exento)<tr><td>Exento</td><td class="num">{{ $m($comprobante->exento) }}</td></tr>@endif
                @foreach($comprobante->alicuotas as $a)
                    <tr><td>IVA {{ str_replace('.', ',', $a->porcentaje) }}%</td><td class="num">{{ $m($a->iva) }}</td></tr>
                @endforeach
                @if((float) $comprobante->percepcion_iva)<tr><td>Percepción IVA</td><td class="num">{{ $m($comprobante->percepcion_iva) }}</td></tr>@endif
                @if((float) $comprobante->percepcion_iibb)<tr><td>Percepción IIBB</td><td class="num">{{ $m($comprobante->percepcion_iibb) }}</td></tr>@endif
                @if((float) $comprobante->otros_tributos)<tr><td>Otros tributos</td><td class="num">{{ $m($comprobante->otros_tributos) }}</td></tr>@endif
                </tbody>
                <tfoot>
                    <tr class="table-light fw-bold fs-6"><td>TOTAL</td><td class="num">$ {{ $m($comprobante->total) }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

@if($comprobante->asiento)
    <div class="card mt-3">
        <div class="card-header bg-white d-flex justify-content-between">
            <span class="fw-semibold">Asiento contable generado</span>
            <a href="{{ route('asientos.show', $comprobante->asiento) }}" class="small">
                Asiento N° {{ $comprobante->asiento->numero }}
                @if($comprobante->asiento->estaAnulado())(anulado)@endif
                <i class="bi bi-box-arrow-up-right"></i>
            </a>
        </div>
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th style="width: 130px;">Código</th><th>Cuenta</th><th class="text-end" style="width: 150px;">Debe</th><th class="text-end" style="width: 150px;">Haber</th></tr></thead>
            <tbody>
            @foreach($comprobante->asiento->lineas as $l)
                <tr>
                    <td class="font-monospace small text-muted">{{ $l->cuenta->codigo }}</td>
                    <td class="{{ (float) $l->haber > 0 ? 'ps-4' : '' }}">{{ $l->cuenta->nombre }}</td>
                    <td class="num">{{ (float) $l->debe > 0 ? $m($l->debe) : '' }}</td>
                    <td class="num">{{ (float) $l->haber > 0 ? $m($l->haber) : '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@if(auth()->user()->puede('contador') && $comprobante->esEditable())
<div class="modal fade" id="modalAnular" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('comprobantes.anular', [$circuito, $comprobante]) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Anular {{ $comprobante->descripcion_corta }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Se anula el comprobante y su asiento contable. Deja de computarse en los libros IVA y contables.</p>
                <label class="form-label">Motivo *</label>
                <input type="text" name="motivo" class="form-control" maxlength="150" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-danger">Anular</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
