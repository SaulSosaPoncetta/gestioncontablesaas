@extends('layouts.app')

@section('title', 'Inicio')
@section('header', 'Inicio')

@section('actions')
    @if($ejercicio?->estaAbierto() && auth()->user()->puede('operador'))
        <a href="{{ route('asientos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nuevo asiento</a>
    @endif
@endsection

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Empresa</div>
                <div class="fw-semibold">{{ $empresaActiva->razon_social }}</div>
                <div class="small text-muted">CUIT {{ $empresaActiva->cuit_formateado }}</div>
                <div class="small text-muted">{{ \App\Models\Empresa::CONDICIONES_IVA[$empresaActiva->condicion_iva] ?? '' }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Ejercicio activo</div>
                @if($ejercicio)
                    <div class="fw-semibold">N° {{ $ejercicio->numero }} · {{ $ejercicio->descripcion }}</div>
                    <div class="small text-muted">{{ $ejercicio->fecha_inicio->format('d/m/Y') }} al {{ $ejercicio->fecha_cierre->format('d/m/Y') }}</div>
                    @if($ejercicio->estaAbierto())
                        <span class="badge text-bg-success">Abierto</span>
                        @if($diasRestantes !== null)
                            <span class="small text-muted ms-1">
                                {{ $diasRestantes >= 0 ? "cierra en {$diasRestantes} días" : 'fecha de cierre vencida' }}
                            </span>
                        @endif
                    @else
                        <span class="badge text-bg-secondary">Cerrado</span>
                    @endif
                @else
                    <div class="text-muted">Sin ejercicios</div>
                    <a href="{{ route('ejercicios.create') }}" class="btn btn-sm btn-primary mt-2">Crear ejercicio</a>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-3">
        <a href="{{ route('asientos.index') }}" class="text-decoration-none text-reset">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Asientos del ejercicio</div>
                    <div class="fs-4 fw-semibold">{{ $stats['registrados'] }}</div>
                    <div class="small text-muted">{{ $stats['mes'] }} este mes
                        @if($stats['borradores'] > 0)· <span class="text-warning">{{ $stats['borradores'] }} en borrador</span>@endif
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="col-md-6 col-xl-3">
        <a href="{{ route('cuentas.index') }}" class="text-decoration-none text-reset">
            <div class="card h-100">
                <div class="card-body">
                    <div class="text-muted small">Plan de cuentas</div>
                    <div class="fs-4 fw-semibold">{{ $stats['cuentas'] }}</div>
                    <div class="small text-muted">{{ $stats['imputables'] }} cuentas imputables</div>
                </div>
            </div>
        </a>
    </div>
</div>

@if($stats['cuentas'] === 0)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Esta empresa no tiene plan de cuentas. <a href="{{ route('cuentas.index') }}" class="alert-link">Cargarlo ahora</a>.
    </div>
@endif

<div class="card">
    <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        Últimos movimientos
        <a href="{{ route('asientos.index') }}" class="small fw-normal">Ver todos</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <tbody>
            @forelse($ultimos as $a)
                <tr style="cursor: pointer;" onclick="location='{{ route('asientos.show', $a) }}'">
                    <td class="font-monospace ps-3" style="width: 80px;">{{ $a->numero_formateado }}</td>
                    <td style="width: 100px;">{{ $a->fecha->format('d/m/Y') }}</td>
                    <td>{{ $a->concepto }}</td>
                    <td class="num">{{ number_format($a->total, 2, ',', '.') }}</td>
                    <td class="pe-3 text-end" style="width: 110px;">
                        @if($a->esBorrador())<span class="badge text-bg-warning">Borrador</span>@else<span class="badge text-bg-success">Registrado</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td class="text-center text-muted py-4">Todavía no hay asientos en este ejercicio.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
