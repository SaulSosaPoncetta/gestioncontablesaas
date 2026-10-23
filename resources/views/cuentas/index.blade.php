@extends('layouts.app')

@section('title', 'Plan de cuentas')
@section('header', 'Plan de cuentas')

@section('actions')
    @if($puedeEditar)
        <a href="{{ route('cuentas.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nuevo rubro</a>
        <a href="{{ route('cuentas.importar.form') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-upload me-1"></i>Importar</a>
    @endif
    @if($total > 0)
        <a href="{{ route('cuentas.exportar') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Exportar CSV</a>
    @endif
@endsection

@push('styles')
<style>
    .arbol td { padding-top: .35rem; padding-bottom: .35rem; }
    .arbol tr:target td { background: #fff3cd; }
    .arbol .toggle { width: 1.25rem; display: inline-block; cursor: pointer; color: #6c757d; }
    .arbol .toggle:hover { color: #0d6efd; }
    .arbol .rubro td { background: #eef2f7; font-weight: 600; }
    .arbol .agrupadora .nombre { font-weight: 600; }
    .arbol .inactiva td { color: #adb5bd; }
    .arbol .acciones .btn { padding: .1rem .35rem; }
    .arbol tr .acciones { visibility: hidden; }
    .arbol tr:hover .acciones { visibility: visible; }
    @media (hover: none) { .arbol tr .acciones { visibility: visible; } }
</style>
@endpush

@section('content')
@if($total === 0)
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-diagram-3 text-muted" style="font-size: 3rem;"></i>
            <h2 class="h5 mt-3">La empresa todavía no tiene plan de cuentas</h2>
            <p class="text-muted">Puede cargar el plan modelo (estructura FACPCE, ~150 cuentas, listo para IVA, retenciones y percepciones) y adaptarlo, o importar el suyo desde un CSV.</p>
            @if($puedeEditar)
                <form method="POST" action="{{ route('cuentas.modelo') }}" class="d-inline">
                    @csrf
                    <button class="btn btn-primary"><i class="bi bi-magic me-1"></i>Cargar plan modelo</button>
                </form>
                <a href="{{ route('cuentas.importar.form') }}" class="btn btn-outline-secondary"><i class="bi bi-upload me-1"></i>Importar CSV</a>
            @endif
        </div>
    </div>
@else
<div class="card">
    <div class="card-body border-bottom">
        <form class="row g-2 align-items-center" method="GET">
            <div class="col-md-4">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Buscar por código o nombre">
            </div>
            <div class="col-md-3">
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Todos los tipos</option>
                    @foreach(\App\Models\Cuenta::TIPOS as $v => $t)
                        <option value="{{ $v }}" @selected(request('tipo') === $v)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Activas e inactivas</option>
                    <option value="activas" @selected(request('estado') === 'activas')>Solo activas</option>
                    <option value="inactivas" @selected(request('estado') === 'inactivas')>Solo inactivas</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
                @if($filtrando)<a href="{{ route('cuentas.index') }}" class="btn btn-sm btn-link">Limpiar</a>@endif
            </div>
            @unless($filtrando)
                <div class="col-auto ms-md-auto">
                    <button type="button" class="btn btn-sm btn-light border" id="expandir"><i class="bi bi-arrows-expand"></i> Expandir</button>
                    <button type="button" class="btn btn-sm btn-light border" id="contraer"><i class="bi bi-arrows-collapse"></i> Contraer</button>
                </div>
            @endunless
        </form>
    </div>

    <div class="card-body py-2 small text-muted border-bottom">
        {{ $total }} cuentas · {{ $imputables }} imputables
        @if($filtrando) · mostrando {{ $cuentas->count() }} resultados @endif
        <span class="ms-3"><span class="badge text-bg-success">I</span> imputable (recibe asientos)</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0 arbol">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 380px;">Cuenta</th>
                    <th>Tipo</th>
                    <th class="text-center">Saldo</th>
                    <th class="text-center">Imput.</th>
                    <th class="text-center" title="Monetaria (para ajuste por inflación)">Monet.</th>
                    <th class="text-end" style="width: 170px;"></th>
                </tr>
            </thead>
            <tbody>
            @forelse($cuentas as $c)
                <tr id="c{{ $c->id }}" data-codigo="{{ $c->codigo }}"
                    class="{{ $c->nivel === 1 ? 'rubro' : '' }} {{ ! $c->imputable ? 'agrupadora' : '' }} {{ ! $c->activa ? 'inactiva' : '' }}">
                    <td>
                        <span style="padding-left: {{ $filtrando ? 0 : ($c->nivel - 1) * 1.4 }}rem;"></span>
                        @if(! $filtrando && $c->hijas_count > 0)
                            <span class="toggle" data-toggle="{{ $c->codigo }}"><i class="bi bi-chevron-down"></i></span>
                        @else
                            <span class="toggle"></span>
                        @endif
                        <span class="font-monospace text-muted me-2">{{ $c->codigo }}</span>
                        <span class="nombre">{{ $c->nombre }}</span>
                        @unless($c->activa)<span class="badge text-bg-light border ms-1">inactiva</span>@endunless
                    </td>
                    <td class="small">{{ \App\Models\Cuenta::TIPOS[$c->tipo] ?? $c->tipo }}</td>
                    <td class="text-center small">{{ $c->saldo_habitual === 'deudor' ? 'D' : 'A' }}</td>
                    <td class="text-center">@if($c->imputable)<span class="badge text-bg-success">I</span>@endif</td>
                    <td class="text-center small">{{ $c->monetaria ? 'M' : 'NM' }}</td>
                    <td class="text-end text-nowrap acciones">
                        @if($puedeEditar)
                            @unless($c->imputable)
                                <a href="{{ route('cuentas.create', ['padre' => $c->id]) }}" class="btn btn-sm btn-outline-primary" title="Agregar subcuenta"><i class="bi bi-plus-lg"></i></a>
                            @endunless
                            <a href="{{ route('cuentas.edit', $c) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('cuentas.estado', $c) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-{{ $c->activa ? 'warning' : 'success' }}" title="{{ $c->activa ? 'Desactivar' : 'Activar' }}">
                                    <i class="bi bi-{{ $c->activa ? 'eye-slash' : 'eye' }}"></i>
                                </button>
                            </form>
                            @if($c->hijas_count === 0)
                                <form method="POST" action="{{ route('cuentas.destroy', $c) }}" class="d-inline" data-confirm="¿Eliminar la cuenta {{ $c->codigo }} - {{ $c->nombre }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No se encontraron cuentas con ese criterio.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const filas = Array.from(document.querySelectorAll('.arbol tbody tr[data-codigo]'));
    if (!filas.length || !document.getElementById('expandir')) return;

    const CLAVE = 'plan_cuentas_contraidas_{{ $empresaActiva->id }}';
    let contraidas;
    try { contraidas = new Set(JSON.parse(localStorage.getItem(CLAVE) || '[]')); } catch (e) { contraidas = new Set(); }

    function ancestros(codigo) {
        const partes = codigo.split('.');
        const lista = [];
        for (let i = 1; i < partes.length; i++) lista.push(partes.slice(0, i).join('.'));
        return lista;
    }

    function refrescar() {
        filas.forEach(function (tr) {
            const oculto = ancestros(tr.dataset.codigo).some(a => contraidas.has(a));
            tr.classList.toggle('d-none', oculto);
            const t = tr.querySelector('[data-toggle]');
            if (t) t.innerHTML = contraidas.has(tr.dataset.codigo) ? '<i class="bi bi-chevron-right"></i>' : '<i class="bi bi-chevron-down"></i>';
        });
        try { localStorage.setItem(CLAVE, JSON.stringify([...contraidas])); } catch (e) {}
    }

    document.querySelectorAll('[data-toggle]').forEach(function (el) {
        el.addEventListener('click', function () {
            const c = el.dataset.toggle;
            contraidas.has(c) ? contraidas.delete(c) : contraidas.add(c);
            refrescar();
        });
    });

    document.getElementById('expandir').addEventListener('click', function () { contraidas.clear(); refrescar(); });
    document.getElementById('contraer').addEventListener('click', function () {
        // Deja visibles rubros y subrubros (niveles 1 y 2)
        contraidas = new Set(Array.from(document.querySelectorAll('[data-toggle]'))
            .map(el => el.dataset.toggle).filter(c => c.split('.').length >= 2));
        refrescar();
    });

    // Si se vuelve de editar una cuenta, expandir su rama
    if (location.hash) {
        const tr = document.querySelector(location.hash);
        if (tr && tr.dataset.codigo) ancestros(tr.dataset.codigo).forEach(a => contraidas.delete(a));
    }

    refrescar();
})();
</script>
@endpush
