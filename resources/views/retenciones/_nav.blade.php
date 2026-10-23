<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('retenciones.*') && request()->route('tipo') === 'practicada' ? 'active' : '' }}" href="{{ route('retenciones.index', 'practicada') }}">
            <i class="bi bi-box-arrow-up-right me-1"></i>Practicadas (como agente)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('retenciones.*') && request()->route('tipo') === 'sufrida' ? 'active' : '' }}" href="{{ route('retenciones.index', 'sufrida') }}">
            <i class="bi bi-box-arrow-in-down-left me-1"></i>Sufridas (certificados recibidos)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('retenciones.exportar') ? 'active' : '' }}" href="{{ route('retenciones.exportar') }}">
            <i class="bi bi-cloud-upload me-1"></i>Resumen y exportación
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('regimenes.*') ? 'active' : '' }}" href="{{ route('regimenes.index') }}">
            <i class="bi bi-sliders me-1"></i>Regímenes
        </a>
    </li>
</ul>
