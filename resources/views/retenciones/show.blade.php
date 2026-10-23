@extends('layouts.app')

@php
    $practicada = $tipo === 'practicada';
    $m = fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp

@section('title', 'Retención ' . ($retencion->numero_certificado ?? ''))
@section('header')
    Retención {{ $retencion->impuesto_texto }} {{ $retencion->numero_certificado ? '· ' . $retencion->numero_certificado : '' }}
    @if($retencion->estaAnulada())<span class="badge text-bg-secondary fs-6 align-middle">Anulada</span>@endif
@endsection

@section('actions')
    @if($practicada && (float) $retencion->importe > 0 && ! $retencion->estaAnulada())
        <a href="{{ route('retenciones.certificado', [$tipo, $retencion]) }}" target="_blank" class="btn btn-outline-danger btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i>Certificado</a>
    @endif
    @if(auth()->user()->puede('operador') && $retencion->esEditable())
        <a href="{{ route('retenciones.edit', [$tipo, $retencion]) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>Editar</a>
    @endif
    @if(auth()->user()->puede('contador') && $retencion->esEditable())
        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalAnular"><i class="bi bi-x-circle me-1"></i>Anular</button>
    @endif
    <a href="{{ route('retenciones.index', $tipo) }}" class="btn btn-outline-secondary btn-sm">Volver</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">{{ $practicada ? 'Sujeto retenido' : 'Agente de retención' }}</div>
            <div class="fw-semibold fs-6">{{ $retencion->tercero->razon_social }}</div>
            <div class="small text-muted mb-3">{{ $retencion->tercero->documento_formateado }} · {{ $retencion->condicion === 'no_inscripto' ? 'No inscripto' : 'Inscripto' }}</div>
            <dl class="row small mb-0">
                <dt class="col-5 fw-normal text-muted">Fecha</dt><dd class="col-7">{{ $retencion->fecha->format('d/m/Y') }}</dd>
                @if($retencion->regimen)
                    <dt class="col-5 fw-normal text-muted">Régimen</dt><dd class="col-7">{{ $retencion->regimen->codigo }} – {{ $retencion->regimen->descripcion }}</dd>
                @endif
                @if($retencion->comprobante)
                    <dt class="col-5 fw-normal text-muted">Comprobante</dt>
                    <dd class="col-7"><a href="{{ route('comprobantes.show', [$retencion->comprobante->circuito, $retencion->comprobante]) }}">{{ $retencion->comprobante->descripcion_corta }}</a></dd>
                @endif
                @if($retencion->observaciones)
                    <dt class="col-5 fw-normal text-muted">Observaciones</dt><dd class="col-7">{{ $retencion->observaciones }}</dd>
                @endif
            </dl>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <table class="table mb-0">
                <tr><td>Importe del comprobante / pago</td><td class="num">{{ $m($retencion->importe_comprobante) }}</td></tr>
                <tr><td>Base imponible</td><td class="num">{{ $m($retencion->base_imponible) }}</td></tr>
                <tr><td>Alícuota</td><td class="num">{{ $m($retencion->alicuota) }} %</td></tr>
                <tr class="table-light fw-bold fs-6"><td>IMPORTE RETENIDO</td><td class="num">$ {{ $m($retencion->importe) }}</td></tr>
            </table>
        </div>
    </div>
</div>

@if($retencion->asiento)
    <div class="card mt-3">
        <div class="card-header bg-white d-flex justify-content-between">
            <span class="fw-semibold">Asiento contable</span>
            <a href="{{ route('asientos.show', $retencion->asiento) }}" class="small">N° {{ $retencion->asiento->numero }} @if($retencion->asiento->estaAnulado())(anulado)@endif <i class="bi bi-box-arrow-up-right"></i></a>
        </div>
        <table class="table table-sm mb-0">
            <tbody>
            @foreach($retencion->asiento->lineas as $l)
                <tr>
                    <td class="font-monospace small text-muted" style="width: 130px;">{{ $l->cuenta->codigo }}</td>
                    <td class="{{ (float) $l->haber > 0 ? 'ps-4' : '' }}">{{ $l->cuenta->nombre }}</td>
                    <td class="num" style="width: 150px;">{{ (float) $l->debe > 0 ? $m($l->debe) : '' }}</td>
                    <td class="num" style="width: 150px;">{{ (float) $l->haber > 0 ? $m($l->haber) : '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@elseif($practicada && (float) $retencion->importe == 0)
    <div class="alert alert-light border mt-3 small">Pago sin retención: no genera asiento ni certificado, pero se suma a los pagos del mes para la acumulación del régimen.</div>
@endif

@if(auth()->user()->puede('contador') && $retencion->esEditable())
<div class="modal fade" id="modalAnular" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('retenciones.anular', [$tipo, $retencion]) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">Anular retención</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
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
