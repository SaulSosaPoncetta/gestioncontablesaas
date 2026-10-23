<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <ul class="nav nav-tabs border-0">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('estados.situacion') ? 'active' : '' }}" href="{{ route('estados.situacion') }}"><i class="bi bi-bank me-1"></i>Situación Patrimonial</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('estados.resultados') ? 'active' : '' }}" href="{{ route('estados.resultados') }}"><i class="bi bi-graph-up-arrow me-1"></i>Estado de Resultados</a>
        </li>
    </ul>
    <div class="btn-group btn-group-sm">
        <a href="{{ request()->fullUrlWithQuery(['formato' => 'pdf']) }}" target="_blank" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
        <a href="{{ request()->fullUrlWithQuery(['formato' => 'csv']) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
    </div>
</div>

@push('styles')
<style>
    .estado { max-width: 900px; }
    .estado td { padding: .3rem .5rem; }
    .estado .fila-titulo td { font-weight: 600; padding-top: .6rem; }
    .estado .fila-total td { font-weight: 600; border-top: 1px solid #adb5bd; }
    .estado .fila-subtotal td { font-weight: 700; background: #f1f4f8; border-top: 1px solid #6c757d; }
    .estado .fila-resultado td { font-weight: 700; font-size: 1.05rem; background: #e2ebf6; border-top: 2px solid #1e3a5f; border-bottom: 3px double #1e3a5f; }
    .estado .seccion td { font-weight: 700; font-size: 1rem; background: #1e3a5f; color: #fff; }
    .estado .gran-total td { font-weight: 700; background: #e2ebf6; border-top: 2px solid #1e3a5f; border-bottom: 3px double #1e3a5f; }
    .estado .col-ant { color: #6c757d; }
</style>
@endpush
