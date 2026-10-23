@extends('layouts.app')

@php $esVenta = $circuito === 'venta'; @endphp

@section('title', $esVenta ? 'Ventas' : 'Compras')
@section('header', $esVenta ? 'Comprobantes de venta' : 'Comprobantes de compra')

@section('actions')
    @if(auth()->user()->puede('operador'))
        <a href="{{ route('comprobantes.create', $circuito) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ $esVenta ? 'Nueva venta' : 'Nueva compra' }}</a>
        @if($empresaActiva->tieneModulo('integraciones') || auth()->user()->es_superadmin)
            <a href="{{ route('importacion.form', ['circuito' => $circuito]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-cloud-download me-1"></i>Importar de ARCA</a>
        @endif
    @endif
@endsection

@section('content')
@php $m = fn ($v) => number_format($v, 2, ',', '.'); @endphp

<div class="card">
    <div class="card-body border-bottom">
        <form class="row g-2" method="GET">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Período IVA</label>
                <input type="month" name="periodo" value="{{ request('periodo') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" value="{{ request('desde') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">{{ $esVenta ? 'Cliente' : 'Proveedor' }}</label>
                <select name="tercero" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($terceros as $t)
                        <option value="{{ $t->id }}" @selected(request('tercero') == $t->id)>{{ $t->razon_social }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Tipo</label>
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach($tipos as $v => $t)
                        <option value="{{ $v }}" @selected(request('tipo') == $v)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <input type="number" name="numero" value="{{ request('numero') }}" class="form-control form-control-sm" placeholder="Número">
            </div>
            <div class="col-6 col-md-2">
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Registrados y anulados</option>
                    <option value="registrado" @selected(request('estado') === 'registrado')>Registrados</option>
                    <option value="anulado" @selected(request('estado') === 'anulado')>Anulados</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
                @if(request()->except('page'))<a href="{{ route('comprobantes.index', $circuito) }}" class="btn btn-sm btn-link">Limpiar</a>@endif
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Comprobante</th>
                    <th>{{ $esVenta ? 'Cliente' : 'Proveedor' }}</th>
                    <th class="text-end">Neto</th>
                    <th class="text-end">IVA</th>
                    <th class="text-end">Percep. / otros</th>
                    <th class="text-end">Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($comprobantes as $c)
                @php
                    $s = $c->signo();
                    $netos = (float) $c->neto_gravado + (float) $c->no_gravado + (float) $c->exento;
                    $otros = (float) $c->percepcion_iva + (float) $c->percepcion_iibb + (float) $c->otros_tributos;
                @endphp
                <tr class="{{ $c->estaAnulado() ? 'text-muted text-decoration-line-through' : '' }}" style="cursor: pointer;"
                    onclick="location='{{ route('comprobantes.show', [$circuito, $c]) }}'">
                    <td>{{ $c->fecha->format('d/m/Y') }}</td>
                    <td class="text-nowrap"><span class="font-monospace">{{ $c->descripcion_corta }}</span></td>
                    <td>
                        {{ $c->tercero->razon_social }}
                        <div class="small text-muted">{{ $c->tercero->documento_formateado }}</div>
                    </td>
                    <td class="num">{{ $m($s * $netos) }}</td>
                    <td class="num">{{ $m($s * (float) $c->iva) }}</td>
                    <td class="num">{{ $otros != 0 ? $m($s * $otros) : '' }}</td>
                    <td class="num fw-semibold">{{ $m($s * (float) $c->total) }}</td>
                    <td class="text-end">
                        @if($c->estaAnulado())<span class="badge text-bg-secondary">Anulado</span>@endif
                        @if($c->esNotaCredito())<span class="badge text-bg-info">NC</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No hay comprobantes con ese criterio.</td></tr>
            @endforelse
            </tbody>
            <tfoot class="table-light fw-semibold">
                <tr>
                    <td colspan="3" class="text-end">Totales (registrados, NC restan)</td>
                    <td class="num">{{ $m($totales['netos']) }}</td>
                    <td class="num">{{ $m($totales['iva']) }}</td>
                    <td class="num">{{ $m($totales['percepciones']) }}</td>
                    <td class="num">{{ $m($totales['total']) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    @if($comprobantes->hasPages())
        <div class="card-body">{{ $comprobantes->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
