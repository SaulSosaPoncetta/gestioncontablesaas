<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inicio') · MiGestión Contable</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        :root { --sidebar-w: 250px; --brand: #1e3a5f; }
        body { background: #f4f6f9; font-size: .925rem; }
        .sidebar {
            width: var(--sidebar-w); min-height: 100vh; background: var(--brand);
            position: fixed; top: 0; left: 0; z-index: 1030; transition: transform .2s; overflow-y: auto; max-height: 100vh;
        }
        .sidebar .brand { color: #fff; font-weight: 600; font-size: 1.1rem; padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,.1); }
        .sidebar .brand small { display: block; font-weight: 400; font-size: .75rem; opacity: .7; }
        .sidebar .nav-link { color: rgba(255,255,255,.75); padding: .55rem 1.25rem; border-left: 3px solid transparent; }
        .sidebar .nav-link:hover { color: #fff; background: rgba(255,255,255,.05); }
        .sidebar .nav-link.active { color: #fff; background: rgba(255,255,255,.1); border-left-color: #4fa3f7; }
        .sidebar .nav-link.disabled { color: rgba(255,255,255,.3); }
        .sidebar .nav-section { color: rgba(255,255,255,.45); font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; padding: 1rem 1.25rem .35rem; }
        .sidebar.admin { background: #2b2f36; }
        .main { margin-left: var(--sidebar-w); min-height: 100vh; }
        .topbar { background: #fff; border-bottom: 1px solid #e3e6ea; }
        .soporte-bar { background: #fff3cd; border-bottom: 1px solid #ffe69c; font-size: .85rem; }
        .card { border: 0; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        .table th { font-weight: 600; font-size: .8rem; text-transform: uppercase; color: #6c757d; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: none; }
            .main { margin-left: 0; }
        }
    </style>
    @stack('styles')
</head>
<body>
@php
    $usuario = auth()->user();
    $esAdminEmpresa = $empresaActiva && $usuario->puede('administrador');
    $plan = $empresaActiva?->plan;
    $estadoAcceso = $estadoAcceso ?? null;
@endphp

<nav class="sidebar d-flex flex-column {{ ! $empresaActiva ? 'admin' : '' }}" id="sidebar">
    <div class="brand">
        <i class="bi bi-journal-bookmark-fill me-1"></i> MiGestión
        <small>{{ $empresaActiva ? 'Sistema Contable' : 'Administración SaaS' }}</small>
    </div>

    <ul class="nav flex-column mt-2 mb-4">
        @if($empresaActiva)
            <li><a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="bi bi-speedometer2 me-2"></i>Inicio</a></li>

            <li class="nav-section">Contabilidad</li>
            <li><a href="{{ route('cuentas.index') }}" class="nav-link {{ request()->routeIs('cuentas.*') ? 'active' : '' }}"><i class="bi bi-diagram-3 me-2"></i>Plan de cuentas</a></li>
            <li><a href="{{ route('asientos.index') }}" class="nav-link {{ request()->routeIs('asientos.*') ? 'active' : '' }}"><i class="bi bi-journal-text me-2"></i>Asientos</a></li>
            <li><a href="{{ route('libros.diario') }}" class="nav-link {{ request()->routeIs('libros.*') ? 'active' : '' }}"><i class="bi bi-book me-2"></i>Libros y balances</a></li>
            <li><a href="{{ route('estados.situacion') }}" class="nav-link {{ request()->routeIs('estados.*') ? 'active' : '' }}"><i class="bi bi-bar-chart-line me-2"></i>Estados contables</a></li>
            <li><a href="{{ route('ajuste.index') }}" class="nav-link {{ request()->routeIs('ajuste.*') ? 'active' : '' }}"><i class="bi bi-graph-up-arrow me-2"></i>Ajuste por inflación</a></li>

            <li class="nav-section">Operaciones</li>
            @php $conIva = $empresaActiva->tieneModulo('iva') || $usuario->es_superadmin; @endphp
            <li>
                <a href="{{ route('comprobantes.index', 'venta') }}" class="nav-link {{ request()->routeIs('comprobantes.*') && request()->route('circuito') === 'venta' ? 'active' : '' }}">
                    <i class="bi bi-cart-check me-2"></i>Ventas @unless($conIva)<i class="bi bi-lock-fill ms-1 small"></i>@endunless
                </a>
            </li>
            <li>
                <a href="{{ route('comprobantes.index', 'compra') }}" class="nav-link {{ request()->routeIs('comprobantes.*') && request()->route('circuito') === 'compra' ? 'active' : '' }}">
                    <i class="bi bi-bag me-2"></i>Compras @unless($conIva)<i class="bi bi-lock-fill ms-1 small"></i>@endunless
                </a>
            </li>
            <li><a href="{{ route('terceros.index') }}" class="nav-link {{ request()->routeIs('terceros.*') ? 'active' : '' }}"><i class="bi bi-person-vcard me-2"></i>Clientes y proveedores</a></li>
            <li>
                <a href="{{ route('importacion.form') }}" class="nav-link {{ request()->routeIs('importacion.*') ? 'active' : '' }}">
                    <i class="bi bi-cloud-download me-2"></i>Importar de ARCA
                    @unless($empresaActiva->tieneModulo('integraciones') || $usuario->es_superadmin)<i class="bi bi-lock-fill ms-1 small"></i>@endunless
                </a>
            </li>

            <li class="nav-section">Impuestos</li>
            <li>
                <a href="{{ route('iva.libro', 'venta') }}" class="nav-link {{ request()->routeIs('iva.*') ? 'active' : '' }}"><i class="bi bi-receipt me-2"></i>Libros IVA
                    @unless($conIva)<i class="bi bi-lock-fill ms-1 small"></i>@endunless
                </a>
            </li>
            <li>
                <a href="{{ route('retenciones.index', 'practicada') }}" class="nav-link {{ request()->routeIs('retenciones.*') || request()->routeIs('regimenes.*') ? 'active' : '' }}"><i class="bi bi-percent me-2"></i>Retenciones / Percep.
                    @unless($empresaActiva->tieneModulo('retenciones') || $usuario->es_superadmin)<i class="bi bi-lock-fill ms-1 small"></i>@endunless
                </a>
            </li>

            <li class="nav-section">Configuración</li>
            @if($esAdminEmpresa)
                <li><a href="{{ route('empresa.edit') }}" class="nav-link {{ request()->routeIs('empresa.*') ? 'active' : '' }}"><i class="bi bi-building me-2"></i>Mi empresa</a></li>
            @endif
            <li><a href="{{ route('ejercicios.index') }}" class="nav-link {{ request()->routeIs('ejercicios.*', 'cierre.*') ? 'active' : '' }}"><i class="bi bi-calendar-range me-2"></i>Ejercicios</a></li>
            @if($esAdminEmpresa && ($empresaActiva->tieneModulo('integraciones') || $usuario->es_superadmin))
                <li><a href="{{ route('integraciones.index') }}" class="nav-link {{ request()->routeIs('integraciones.*') ? 'active' : '' }}"><i class="bi bi-plug me-2"></i>Integraciones</a></li>
            @endif
            @if($usuario->puede('contador'))
                <li><a href="{{ route('config.cuentas') }}" class="nav-link {{ request()->routeIs('config.*') ? 'active' : '' }}"><i class="bi bi-gear me-2"></i>Cuentas automáticas</a></li>
            @endif
            @if($esAdminEmpresa)
                <li><a href="{{ route('usuarios.index') }}" class="nav-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}"><i class="bi bi-people me-2"></i>Usuarios</a></li>
            @endif
            <li><a href="{{ route('suscripcion.show') }}" class="nav-link {{ request()->routeIs('suscripcion.*') ? 'active' : '' }}"><i class="bi bi-credit-card me-2"></i>Mi suscripción</a></li>
        @endif

        @if($usuario->es_superadmin)
            <li class="nav-section">Administración SaaS</li>
            <li><a href="{{ route('admin.suscriptores.index') }}" class="nav-link {{ request()->routeIs('admin.suscriptores.*') ? 'active' : '' }}"><i class="bi bi-buildings me-2"></i>Suscriptores</a></li>
            <li><a href="{{ route('admin.planes.index') }}" class="nav-link {{ request()->routeIs('admin.planes.*') ? 'active' : '' }}"><i class="bi bi-tags me-2"></i>Planes</a></li>
            <li><a href="{{ route('admin.indices.index') }}" class="nav-link {{ request()->routeIs('admin.indices.*') ? 'active' : '' }}"><i class="bi bi-percent me-2"></i>Índices de inflación</a></li>
        @endif
    </ul>
</nav>

<div class="main d-flex flex-column">

    {{-- Superadmin dentro de una empresa cliente --}}
    @if($usuario->es_superadmin && $empresaActiva)
        <div class="soporte-bar px-3 py-1 d-flex align-items-center gap-2">
            <i class="bi bi-headset"></i>
            Modo soporte: está viendo <strong>{{ $empresaActiva->razon_social }}</strong>
            <form method="POST" action="{{ route('admin.suscriptores.salir') }}" class="ms-auto">
                @csrf
                <button class="btn btn-sm btn-outline-dark py-0">Salir al panel</button>
            </form>
        </div>
    @endif

    <header class="topbar px-3 py-2 d-flex align-items-center gap-2 flex-wrap">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('show')">
            <i class="bi bi-list"></i>
        </button>

        @if($empresaActiva)
            <div class="fw-semibold">
                <i class="bi bi-building me-1 text-muted"></i>{{ $empresaActiva->razon_social }}
                <span class="text-muted fw-normal small ms-1">{{ $empresaActiva->cuit_formateado }}</span>
            </div>

            {{-- Selector de ejercicio --}}
            @php $ejerciciosEmpresa = \App\Models\Ejercicio::orderByDesc('fecha_inicio')->get(); @endphp
            <div class="dropdown">
                <button class="btn btn-sm btn-light border dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-calendar-range me-1"></i>
                    @if($ejercicioActivo)
                        Ej. {{ $ejercicioActivo->numero }}: {{ $ejercicioActivo->fecha_inicio->format('d/m/Y') }} - {{ $ejercicioActivo->fecha_cierre->format('d/m/Y') }}
                        @unless($ejercicioActivo->estaAbierto())<span class="badge text-bg-secondary ms-1">Cerrado</span>@endunless
                    @else
                        Sin ejercicio
                    @endif
                </button>
                <ul class="dropdown-menu shadow-sm">
                    @forelse($ejerciciosEmpresa as $ej)
                        <li>
                            <form method="POST" action="{{ route('ejercicios.seleccionar', $ej) }}">
                                @csrf
                                <button class="dropdown-item {{ $ejercicioActivo?->id === $ej->id ? 'active' : '' }}">
                                    N° {{ $ej->numero }} · {{ $ej->fecha_inicio->format('d/m/Y') }} - {{ $ej->fecha_cierre->format('d/m/Y') }}
                                    @unless($ej->estaAbierto())<small>(cerrado)</small>@endunless
                                </button>
                            </form>
                        </li>
                    @empty
                        <li><span class="dropdown-item-text text-muted">No hay ejercicios</span></li>
                    @endforelse
                    @if($usuario->puede('contador'))
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('ejercicios.create') }}"><i class="bi bi-plus-lg me-1"></i>Nuevo ejercicio</a></li>
                    @endif
                </ul>
            </div>

            @if($plan)
                <span class="badge rounded-pill text-bg-light border d-none d-md-inline">Plan {{ $plan->nombre }}</span>
            @endif
        @endif

        <div class="ms-auto dropdown">
            <button class="btn btn-sm btn-link text-decoration-none text-dark dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle me-1"></i>{{ $usuario->name }}
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><span class="dropdown-item-text small text-muted">
                    {{ $usuario->es_superadmin ? 'Superadministrador' : (\App\Models\User::ROLES[$usuario->rolEn($empresaActiva)] ?? '') }}
                </span></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="dropdown-item"><i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión</button>
                    </form>
                </li>
            </ul>
        </div>
    </header>

    <main class="flex-grow-1 p-3 p-lg-4">

        {{-- Avisos de suscripción --}}
        @if($empresaActiva && ! request()->routeIs('suscripcion.*'))
            @php $dias = $empresaActiva->diasParaVencer(); @endphp
            @if($estadoAcceso === 'solo_lectura')
                <div class="alert alert-danger d-flex align-items-center gap-2">
                    <i class="bi bi-lock-fill"></i>
                    <div>Su suscripción está vencida. La cuenta está en <strong>modo solo lectura</strong>.
                        <a href="{{ route('suscripcion.show') }}" class="alert-link">Renovar</a></div>
                </div>
            @elseif($estadoAcceso === 'gracia')
                <div class="alert alert-warning d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>Su suscripción venció el {{ $empresaActiva->fecha_vencimiento->format('d/m/Y') }}.
                        En {{ config('saas.dias_gracia') - abs($dias) }} días la cuenta pasará a solo lectura.
                        <a href="{{ route('suscripcion.show') }}" class="alert-link">Renovar</a></div>
                </div>
            @elseif($empresaActiva->estado_suscripcion === 'prueba' && $dias !== null && $dias <= 7)
                <div class="alert alert-info d-flex align-items-center gap-2">
                    <i class="bi bi-hourglass-split"></i>
                    <div>Le quedan <strong>{{ $dias }} días</strong> de prueba gratis.
                        <a href="{{ route('suscripcion.show') }}" class="alert-link">Contratar un plan</a></div>
                </div>
            @endif
        @endif

        @if(View::hasSection('header'))
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h1 class="h4 mb-0">@yield('header')</h1>
                <div>@yield('actions')</div>
            </div>
        @endif

        @include('layouts.partials.alerts')

        @yield('content')
    </main>

    <footer class="text-center text-muted small py-3">
        MiGestión Contable · {{ date('Y') }}
    </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Confirmación genérica para formularios con data-confirm
    document.addEventListener('submit', function (e) {
        const msg = e.target.dataset.confirm;
        if (msg && !confirm(msg)) e.preventDefault();
    });
</script>
@stack('scripts')
</body>
</html>
