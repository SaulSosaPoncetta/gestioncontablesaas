@extends('layouts.app')

@section('title', 'Suscriptores')
@section('header', 'Suscriptores')

@section('content')
<div class="row g-3 mb-3">
    @foreach([
        ['Empresas', $resumen['total'], 'buildings', 'primary'],
        ['En prueba', $resumen['prueba'], 'hourglass-split', 'info'],
        ['Activas', $resumen['activas'], 'check-circle', 'success'],
        ['Vencidas', $resumen['vencidas'], 'exclamation-triangle', 'danger'],
    ] as [$label, $valor, $icono, $color])
        <div class="col-6 col-lg">
            <div class="card h-100"><div class="card-body">
                <div class="text-muted small"><i class="bi bi-{{ $icono }} text-{{ $color }} me-1"></i>{{ $label }}</div>
                <div class="fs-4 fw-semibold">{{ $valor }}</div>
            </div></div>
        </div>
    @endforeach
    <div class="col-12 col-lg">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small"><i class="bi bi-cash-coin text-success me-1"></i>Cobrado este mes</div>
            <div class="fs-4 fw-semibold">$ {{ number_format($resumen['ingreso_mes'], 2, ',', '.') }}</div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-body border-bottom">
        <form class="row g-2" method="GET">
            <div class="col-md-4">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Razón social, CUIT o email">
            </div>
            <div class="col-md-3">
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos los estados</option>
                    @foreach(\App\Models\Empresa::ESTADOS_SUSCRIPCION as $v => $t)
                        <option value="{{ $v }}" @selected(request('estado') === $v)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="plan" class="form-select form-select-sm">
                    <option value="">Todos los planes</option>
                    @foreach($planes as $p)
                        <option value="{{ $p->id }}" @selected(request('plan') == $p->id)>{{ $p->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Empresa</th>
                    <th>Plan</th>
                    <th>Estado</th>
                    <th>Vence</th>
                    <th class="text-center">Usuarios</th>
                    <th>Alta</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($empresas as $e)
                @php
                    $acceso = $e->estadoAcceso();
                    $color = ['ok' => 'success', 'gracia' => 'warning', 'solo_lectura' => 'danger', 'bloqueada' => 'dark'][$acceso];
                    $dias = $e->diasParaVencer();
                @endphp
                <tr>
                    <td>
                        <div class="fw-semibold">{{ $e->razon_social }}</div>
                        <div class="small text-muted">{{ $e->cuit_formateado }} · {{ $e->email }}</div>
                    </td>
                    <td>{{ $e->plan?->nombre ?? '—' }}</td>
                    <td>
                        <span class="badge text-bg-{{ $color }}">{{ \App\Models\Empresa::ESTADOS_SUSCRIPCION[$e->estado_suscripcion] }}</span>
                        @if($acceso === 'gracia')<div class="small text-warning">en gracia</div>@endif
                        @if($acceso === 'solo_lectura')<div class="small text-danger">solo lectura</div>@endif
                    </td>
                    <td>
                        {{ $e->fecha_vencimiento?->format('d/m/Y') ?? '—' }}
                        @if($dias !== null)
                            <div class="small {{ $dias < 0 ? 'text-danger' : ($dias <= 7 ? 'text-warning' : 'text-muted') }}">
                                {{ $dias < 0 ? 'hace ' . abs($dias) . ' días' : "en {$dias} días" }}
                            </div>
                        @endif
                    </td>
                    <td class="text-center">{{ $e->usuarios_count }}</td>
                    <td class="small">{{ $e->created_at->format('d/m/Y') }}</td>
                    <td class="text-end text-nowrap">
                        <form method="POST" action="{{ route('admin.suscriptores.ingresar', $e) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary" title="Ingresar como soporte"><i class="bi bi-box-arrow-in-right"></i></button>
                        </form>
                        <a href="{{ route('admin.suscriptores.edit', $e) }}" class="btn btn-sm btn-outline-secondary" title="Suscripción y pagos"><i class="bi bi-credit-card"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No hay empresas registradas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($empresas->hasPages())
        <div class="card-body">{{ $empresas->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
