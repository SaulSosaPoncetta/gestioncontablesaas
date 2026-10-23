@extends('layouts.app')

@section('title', 'Retenciones · resumen y exportación')
@section('header', 'Retenciones y percepciones')

@section('content')
@include('retenciones._nav')
@php
    $m = fn ($v) => number_format((float) $v, 2, ',', '.');
    $periodoTxt = \Carbon\Carbon::createFromFormat('Y-m-d', $periodo . '-01')->format('m/Y');
    $imp = \App\Models\Retencion::IMPUESTOS;
@endphp

<form method="GET" class="d-flex gap-2 align-items-end mb-3">
    <div>
        <label class="form-label small mb-1">Período</label>
        <input type="month" name="periodo" value="{{ $periodo }}" class="form-control form-control-sm" onchange="this.form.submit()">
    </div>
    <a href="{{ route('retenciones.listado', ['periodo' => $periodo]) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Listado completo (Excel)</a>
</form>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">A depositar · {{ $periodoTxt }}</div>
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Concepto</th><th class="text-center">Cant.</th><th class="text-end">Importe</th></tr></thead>
                <tbody>
                @foreach($practicadas as $i => $t)
                    <tr><td>Retenciones {{ $imp[$i] ?? $i }}</td><td class="text-center">{{ $t->cantidad }}</td><td class="num">{{ $m($t->importe) }}</td></tr>
                @endforeach
                @if($percepciones['iva'] != 0)
                    <tr><td>Percepciones IVA (en facturas de venta)</td><td></td><td class="num">{{ $m($percepciones['iva']) }}</td></tr>
                @endif
                @if($percepciones['iibb'] != 0)
                    <tr><td>Percepciones IIBB (en facturas de venta)</td><td></td><td class="num">{{ $m($percepciones['iibb']) }}</td></tr>
                @endif
                @if($practicadas->isEmpty() && $percepciones['iva'] == 0 && $percepciones['iibb'] == 0)
                    <tr><td colspan="3" class="text-muted text-center">Sin retenciones ni percepciones practicadas.</td></tr>
                @endif
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Sufridas (a computar) · {{ $periodoTxt }}</div>
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Impuesto</th><th class="text-center">Cert.</th><th class="text-end">Importe</th></tr></thead>
                <tbody>
                @forelse($sufridas as $i => $t)
                    <tr><td>{{ $imp[$i] ?? $i }}</td><td class="text-center">{{ $t->cantidad }}</td><td class="num">{{ $m($t->importe) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted text-center">Sin certificados cargados.</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="card-body small text-muted py-2">Las retenciones de IVA sufridas se suman automáticamente en la posición de IVA del período.</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="fw-semibold">SICORE · Ganancias</div>
                <p class="small text-muted mb-2">Archivo de importación de retenciones para el aplicativo SICORE (ARCA). {{ $sicore }} registros.</p>
                <a href="{{ route('retenciones.sicore', ['periodo' => $periodo]) }}" class="btn btn-sm btn-outline-primary {{ $sicore ? '' : 'disabled' }}"><i class="bi bi-download me-1"></i>Descargar TXT</a>
                <div class="alert alert-warning small mt-3 mb-0 py-2">
                    <i class="bi bi-exclamation-triangle"></i> Diseño de 144 posiciones con decimales con punto. Verifíquelo en la primera importación: si SICORE lo rechaza, envíe el mensaje de error para ajustarlo.
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="fw-semibold">ARBA · Retenciones IIBB (A-122R)</div>
                <p class="small text-muted mb-2">Lote de importación con el diseño vigente desde 03/2026 (ZIP «ER-CUIT-PERÍODO-ACTIVIDAD-LOTE»). {{ $arba }} registros.</p>
                <form method="GET" action="{{ route('retenciones.arba') }}" class="row g-2 align-items-end">
                    <input type="hidden" name="periodo" value="{{ $periodo }}">
                    <div class="col-4">
                        <label class="form-label small mb-1">Quincena</label>
                        <select name="quincena" class="form-select form-select-sm">
                            <option value="0">Mensual</option><option value="1">1ª quincena</option><option value="2">2ª quincena</option>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label small mb-1">Actividad</label>
                        <input type="text" name="actividad" value="6" class="form-control form-control-sm">
                    </div>
                    <div class="col-4">
                        <label class="form-label small mb-1">N° lote</label>
                        <input type="number" name="lote" value="1" min="1" class="form-control form-control-sm">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-sm btn-outline-primary" {{ $arba ? '' : 'disabled' }}><i class="bi bi-file-earmark-zip me-1"></i>Descargar lote</button>
                    </div>
                </form>
                <div class="small text-muted mt-2">El código de actividad y la quincena deben coincidir con la DDJJ que abre en ARBA.</div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="alert alert-light border small mb-0">
            <strong>IVA (SIRE) y AGIP:</strong> las retenciones y percepciones de IVA se informan en SIRE desde 05/2021 (RG 4523).
            Por ahora use el <a href="{{ route('retenciones.listado', ['periodo' => $periodo]) }}">listado completo</a> para la carga.
            La exportación en el formato de importación de SIRE y AGIP se agrega cuando tengamos la especificación oficial vigente.
        </div>
    </div>
</div>
@endsection
