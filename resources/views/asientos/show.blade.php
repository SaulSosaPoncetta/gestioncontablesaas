@extends('layouts.app')

@section('title', 'Asiento ' . $asiento->numero_formateado)
@section('header')
    Asiento {{ $asiento->numero ? 'N° ' . $asiento->numero : '(borrador)' }}
    @switch($asiento->estado)
        @case('registrado')<span class="badge text-bg-success fs-6 align-middle">Registrado</span>@break
        @case('borrador')<span class="badge text-bg-warning fs-6 align-middle">Borrador</span>@break
        @case('anulado')<span class="badge text-bg-secondary fs-6 align-middle">Anulado</span>@break
    @endswitch
@endsection

@section('actions')
    @if(auth()->user()->puede('operador') && $asiento->esEditableManualmente())
        <a href="{{ route('asientos.edit', $asiento) }}" class="btn btn-primary btn-sm"><i class="bi bi-pencil me-1"></i>{{ $asiento->esBorrador() ? 'Editar / Registrar' : 'Editar' }}</a>
    @endif
    @if(auth()->user()->puede('operador') && $ejercicioActivo?->estaAbierto())
        <a href="{{ route('asientos.create', ['copiar' => $asiento->id]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-files me-1"></i>Copiar</a>
    @endif
    @if(auth()->user()->puede('contador') && $asiento->estado === 'registrado' && $asiento->esEditableManualmente())
        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalAnular"><i class="bi bi-x-circle me-1"></i>Anular</button>
    @endif
    @if(auth()->user()->puede('operador') && $asiento->esBorrador())
        <form method="POST" action="{{ route('asientos.destroy', $asiento) }}" class="d-inline" data-confirm="¿Eliminar este borrador?">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Eliminar</button>
        </form>
    @endif
    <a href="{{ route('asientos.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
@endsection

@section('content')
@if($asiento->estaAnulado())
    <div class="alert alert-secondary">
        <i class="bi bi-x-octagon me-1"></i>
        Anulado el {{ $asiento->anulado_at?->format('d/m/Y H:i') }} por {{ $asiento->anuladoPor?->name ?? '—' }}.
        <strong>Motivo:</strong> {{ $asiento->motivo_anulacion }}
    </div>
@endif

@if($asiento->esAutomatico() && $asiento->comprobante)
    <div class="alert alert-info py-2 small">
        <i class="bi bi-link-45deg me-1"></i>
        Asiento generado por el comprobante
        <a href="{{ route('comprobantes.show', [$asiento->comprobante->circuito, $asiento->comprobante]) }}" class="alert-link">{{ $asiento->comprobante->descripcion_corta }}</a>.
        Para modificarlo o anularlo hágalo desde el comprobante.
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 small">
            <div class="col-6 col-md-2"><div class="text-muted">Fecha</div><div class="fw-semibold fs-6">{{ $asiento->fecha->format('d/m/Y') }}</div></div>
            <div class="col-6 col-md-2"><div class="text-muted">Tipo</div><div class="fw-semibold">{{ \App\Models\Asiento::TIPOS[$asiento->tipo] ?? $asiento->tipo }}</div></div>
            <div class="col-md-5"><div class="text-muted">Concepto</div><div class="fw-semibold">{{ $asiento->concepto }}</div></div>
            <div class="col-6 col-md-3"><div class="text-muted">Ejercicio</div><div>N° {{ $asiento->ejercicio->numero }} ({{ $asiento->ejercicio->fecha_inicio->format('d/m/Y') }} - {{ $asiento->ejercicio->fecha_cierre->format('d/m/Y') }})</div></div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th style="width: 140px;">Código</th>
                    <th>Cuenta</th>
                    <th>Detalle</th>
                    <th class="text-end" style="width: 160px;">Debe</th>
                    <th class="text-end" style="width: 160px;">Haber</th>
                </tr>
            </thead>
            <tbody>
            @foreach($asiento->lineas as $l)
                <tr>
                    <td class="font-monospace text-muted">{{ $l->cuenta->codigo }}</td>
                    <td class="{{ (float) $l->haber > 0 ? 'ps-4' : '' }}">{{ $l->cuenta->nombre }}</td>
                    <td class="small text-muted">{{ $l->detalle }}</td>
                    <td class="num">{{ (float) $l->debe > 0 ? number_format($l->debe, 2, ',', '.') : '' }}</td>
                    <td class="num">{{ (float) $l->haber > 0 ? number_format($l->haber, 2, ',', '.') : '' }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot class="table-light">
                @php
                    $totalDebe = $asiento->lineas->sum(fn ($l) => (float) $l->debe);
                    $totalHaber = $asiento->lineas->sum(fn ($l) => (float) $l->haber);
                @endphp
                <tr class="fw-semibold">
                    <td colspan="3" class="text-end">Totales</td>
                    <td class="num">{{ number_format($totalDebe, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($totalHaber, 2, ',', '.') }}</td>
                </tr>
                @if(round($totalDebe - $totalHaber, 2) != 0)
                    <tr><td colspan="5" class="text-danger small text-end">Diferencia: {{ number_format(abs($totalDebe - $totalHaber), 2, ',', '.') }}</td></tr>
                @endif
            </tfoot>
        </table>
    </div>
</div>

<p class="small text-muted">
    Cargado por {{ $asiento->usuario?->name ?? '—' }} el {{ $asiento->created_at->format('d/m/Y H:i') }}
    @if($asiento->updated_at->gt($asiento->created_at->copy()->addSeconds(5)))· última modificación {{ $asiento->updated_at->format('d/m/Y H:i') }}@endif
    · origen: {{ $asiento->origen }}
</p>

{{-- Modal de anulación --}}
@if(auth()->user()->puede('contador') && $asiento->estado === 'registrado')
<div class="modal fade" id="modalAnular" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('asientos.anular', $asiento) }}" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Anular asiento N° {{ $asiento->numero }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">El asiento conserva su número pero deja de computarse en los libros y balances. Esta acción no se puede deshacer.</p>
                <label class="form-label">Motivo *</label>
                <input type="text" name="motivo" class="form-control" maxlength="255" required autofocus>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-danger">Anular asiento</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection
