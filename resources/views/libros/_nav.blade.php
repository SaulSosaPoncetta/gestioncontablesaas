{{-- Pestañas de libros + botones de exportación. Variable opcional: $exportable (bool) --}}
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <ul class="nav nav-tabs border-0">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('libros.diario') ? 'active' : '' }}" href="{{ route('libros.diario') }}"><i class="bi bi-journal-text me-1"></i>Libro Diario</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('libros.mayor') ? 'active' : '' }}" href="{{ route('libros.mayor') }}"><i class="bi bi-journal-bookmark me-1"></i>Libro Mayor</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('libros.sumas') ? 'active' : '' }}" href="{{ route('libros.sumas') }}"><i class="bi bi-table me-1"></i>Sumas y Saldos</a>
        </li>
    </ul>

    @if($exportable ?? true)
        <div class="btn-group btn-group-sm">
            <a href="{{ request()->fullUrlWithQuery(['formato' => 'pdf', 'page' => null]) }}" target="_blank" class="btn btn-outline-danger">
                <i class="bi bi-file-earmark-pdf me-1"></i>PDF
            </a>
            <a href="{{ request()->fullUrlWithQuery(['formato' => 'csv', 'page' => null]) }}" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i>Excel
            </a>
        </div>
    @endif
</div>
