@extends('layouts.app')

@php $practicada = $tipo === 'practicada'; @endphp

@section('title', $practicada ? 'Retenciones practicadas' : 'Retenciones sufridas')
@section('header', 'Retenciones y percepciones')

@section('actions')
    @if(auth()->user()->puede('operador'))
        <a href="{{ route('retenciones.create', $tipo) }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>{{ $practicada ? 'Nueva retención' : 'Cargar certificado' }}
        </a>
    @endif
@endsection

@section('content')
@include('retenciones._nav')
@php $m = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp

@if($sinRegimenes)
    <div class="alert alert-warning">
        Para practicar retenciones primero configure los regímenes en los que actúa como agente.
        <a href="{{ route('regimenes.index') }}" class="alert-link">Ir a Regímenes</a>
    </div>
@endif

@if($totales->isNotEmpty())
    <div class="row g-2 mb-3">
        @foreach($totales as $imp => $t)
            <div class="col-6 col-md-3">
                <div class="card"><div class="card-body py-2">
                    <div class="small text-muted">{{ \App\Models\Retencion::IMPUESTOS[$imp] ?? $imp }} · {{ $t->cantidad }}</div>
                    <div class="fw-semibold">$ {{ $m($t->importe) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>
@endif

<div class="card">
    <div class="card-body border-bottom">
        <form class="row g-2" method="GET">
            <div class="col-6 col-md-2">
                <input type="month" name="periodo" value="{{ request('periodo') }}" class="form-control form-control-sm" title="Período">
            </div>
            <div class="col-6 col-md-2">
                <select name="impuesto" class="form-select form-select-sm">
                    <option value="">Todos los impuestos</option>
                    @foreach(\App\Models\Retencion::IMPUESTOS as $v => $t)
                        <option value="{{ $v }}" @selected(request('impuesto') === $v)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <select name="tercero" class="form-select form-select-sm">
                    <option value="">{{ $practicada ? 'Todos los proveedores' : 'Todos los clientes' }}</option>
                    @foreach($terceros as $t)
                        <option value="{{ $t->id }}" @selected(request('tercero') == $t->id)>{{ $t->razon_social }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <option value="registrado" @selected(request('estado') === 'registrado')>Registradas</option>
                    <option value="anulado" @selected(request('estado') === 'anulado')>Anuladas</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th><th>Certificado</th><th>Impuesto / régimen</th><th>{{ $practicada ? 'Proveedor' : 'Agente (cliente)' }}</th>
                    <th class="text-end">Base</th><th class="text-end">Alíc.</th><th class="text-end">Importe</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($retenciones as $r)
                <tr class="{{ $r->estaAnulada() ? 'text-muted text-decoration-line-through' : '' }}" style="cursor: pointer;"
                    onclick="location='{{ route('retenciones.show', [$tipo, $r]) }}'">
                    <td>{{ $r->fecha->format('d/m/Y') }}</td>
                    <td class="font-monospace">{{ $r->numero_certificado ?? '—' }}</td>
                    <td>{{ $r->impuesto_texto }} @if($r->regimen)<span class="small text-muted">· {{ $r->regimen->codigo }}</span>@endif</td>
                    <td>{{ $r->tercero->razon_social }}</td>
                    <td class="num">{{ (float) $r->base_imponible ? $m($r->base_imponible) : '' }}</td>
                    <td class="num">{{ (float) $r->alicuota ? $m($r->alicuota) . '%' : '' }}</td>
                    <td class="num fw-semibold">{{ $m($r->importe) }}</td>
                    <td class="text-end">
                        @if($r->estaAnulada())<span class="badge text-bg-secondary">Anulada</span>@endif
                        @if($practicada && (float) $r->importe == 0 && ! $r->estaAnulada())<span class="badge text-bg-light border">sin retención</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No hay retenciones con ese criterio.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($retenciones->hasPages())
        <div class="card-body">{{ $retenciones->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
