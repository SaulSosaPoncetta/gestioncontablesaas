@extends('layouts.app')

@section('title', 'Integraciones')
@section('header', 'Integraciones')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
@endpush

@section('content')
@if($tokenNuevo)
    <div class="alert alert-success">
        <div class="fw-semibold mb-1"><i class="bi bi-key me-1"></i>Token de acceso (cópielo ahora, no se vuelve a mostrar)</div>
        <div class="input-group">
            <input type="text" class="form-control font-monospace" id="token" value="{{ $tokenNuevo['token'] }}" readonly>
            <button class="btn btn-outline-success" type="button" onclick="navigator.clipboard.writeText(document.getElementById('token').value); this.textContent='Copiado'">Copiar</button>
        </div>
        <div class="small mt-2">Péguelo en GestiónComercial › Configuración › Integración contable, junto con esta URL: <code>{{ url('/') }}</code></div>
    </div>
@endif

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        @forelse($integraciones as $int)
            <div class="card mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fw-semibold">{{ $int->nombre }}</span>
                        <span class="small text-muted ms-2">{{ \App\Models\Integracion::SISTEMAS[$int->sistema] ?? $int->sistema }}</span>
                    </div>
                    @if($int->activa)
                        <span class="badge text-bg-success">Activa</span>
                    @else
                        <span class="badge text-bg-secondary">Desactivada</span>
                    @endif
                </div>
                <div class="card-body">
                    <div class="small text-muted mb-3">
                        Token <code>{{ $int->token_prefijo }}…</code>
                        · {{ $int->registros_count }} documentos recibidos
                        @if($int->errores_count)· <span class="text-danger">{{ $int->errores_count }} con error</span>@endif
                        · último uso: {{ $int->ultimo_uso_at?->diffForHumans() ?? 'nunca' }}
                    </div>

                    <form method="POST" action="{{ route('integraciones.update', $int) }}" class="row g-2">
                        @csrf @method('PUT')
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Nombre</label>
                            <input type="text" name="nombre" value="{{ $int->nombre }}" class="form-control form-control-sm" required maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Imputación de ventas</label>
                            <select name="cuenta_ventas_id" class="form-select form-select-sm cuenta-select">
                                <option value="">Por defecto (cuentas automáticas)</option>
                                @foreach($cuentas as $c)
                                    <option value="{{ $c->id }}" @selected($int->cuenta_ventas_id == $c->id)>{{ $c->codigo }} - {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Imputación de compras</label>
                            <select name="cuenta_compras_id" class="form-select form-select-sm cuenta-select">
                                <option value="">Por defecto (cuentas automáticas)</option>
                                @foreach($cuentas as $c)
                                    <option value="{{ $c->id }}" @selected($int->cuenta_compras_id == $c->id)>{{ $c->codigo }} - {{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                            <div class="form-check form-switch me-3">
                                <input class="form-check-input" type="checkbox" name="activa" value="1" id="activa{{ $int->id }}" @checked($int->activa)>
                                <label class="form-check-label small" for="activa{{ $int->id }}">Activa</label>
                            </div>
                            <button class="btn btn-sm btn-primary">Guardar</button>
                        </div>
                    </form>
                    <div class="d-flex gap-2 mt-2">
                        <form method="POST" action="{{ route('integraciones.regenerar', $int) }}" data-confirm="El token actual dejará de funcionar. ¿Generar uno nuevo?">
                            @csrf
                            <button class="btn btn-sm btn-outline-warning"><i class="bi bi-arrow-repeat me-1"></i>Nuevo token</button>
                        </form>
                        <form method="POST" action="{{ route('integraciones.destroy', $int) }}" data-confirm="¿Eliminar la integración? Los comprobantes recibidos se conservan.">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="card mb-3"><div class="card-body text-muted">Todavía no hay integraciones configuradas.</div></div>
        @endforelse
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Nueva integración</div>
            <div class="card-body">
                <form method="POST" action="{{ route('integraciones.store') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small mb-1">Sistema</label>
                        <select name="sistema" class="form-select form-select-sm">
                            @foreach(\App\Models\Integracion::SISTEMAS as $v => $t)
                                <option value="{{ $v }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small mb-1">Nombre</label>
                        <input type="text" name="nombre" value="GestiónComercial" class="form-control form-control-sm" required maxlength="100">
                    </div>
                    <button class="btn btn-sm btn-primary w-100"><i class="bi bi-key me-1"></i>Crear y generar token</button>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-body small text-muted">
                <div class="fw-semibold text-body mb-1">Cómo funciona</div>
                Cada factura o nota de crédito que se emite en GestiónComercial se envía sola a la contabilidad y genera su comprobante y su asiento.
                Si el comprobante ya estaba cargado (a mano o importado de ARCA) se vincula y no se duplica.
                <div class="mt-2">API: <code class="text-break">{{ $urlApi }}</code></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Documentos recibidos</span>
        <div class="btn-group btn-group-sm">
            <a href="{{ route('integraciones.index') }}" class="btn btn-outline-secondary {{ ! request('estado') ? 'active' : '' }}">Todos</a>
            <a href="{{ route('integraciones.index', ['estado' => 'error']) }}" class="btn btn-outline-danger {{ request('estado') === 'error' ? 'active' : '' }}">Con error</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Fecha</th><th>Origen</th><th>Documento</th><th>Estado</th><th>Detalle</th></tr></thead>
            <tbody>
            @forelse($registros as $r)
                <tr>
                    <td class="small text-nowrap">{{ $r->updated_at->format('d/m/Y H:i') }}</td>
                    <td class="small">{{ $r->integracion?->nombre }}</td>
                    <td class="small">{{ \App\Models\IntegracionRegistro::TIPOS_ORIGEN[$r->tipo_origen] ?? $r->tipo_origen }} #{{ $r->id_externo }}</td>
                    <td>
                        @if($r->estado === 'ok')
                            <span class="badge text-bg-success">{{ ucfirst($r->accion ?? 'ok') }}</span>
                        @else
                            <span class="badge text-bg-danger">Error</span>
                        @endif
                    </td>
                    <td class="small">
                        @if($r->comprobante)
                            <a href="{{ route('comprobantes.show', [$r->comprobante->circuito, $r->comprobante]) }}">{{ $r->comprobante->descripcion_corta }}</a> ·
                        @endif
                        <span class="{{ $r->estado === 'error' ? 'text-danger' : 'text-muted' }}">{{ $r->mensaje }}</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Todavía no se recibieron documentos.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($registros->hasPages())
        <div class="card-body">{{ $registros->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>document.querySelectorAll('.cuenta-select').forEach(el => new TomSelect(el, { maxOptions: 80 }));</script>
@endpush
