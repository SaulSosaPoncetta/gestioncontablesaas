{{-- Pestañas de IVA + selector de período. Variables: $periodo; opcional $exportable --}}
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <ul class="nav nav-tabs border-0">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('iva.libro') && request()->route('circuito') === 'venta' ? 'active' : '' }}"
               href="{{ route('iva.libro', ['circuito' => 'venta', 'periodo' => $periodo]) }}"><i class="bi bi-cart-check me-1"></i>IVA Ventas</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('iva.libro') && request()->route('circuito') === 'compra' ? 'active' : '' }}"
               href="{{ route('iva.libro', ['circuito' => 'compra', 'periodo' => $periodo]) }}"><i class="bi bi-bag me-1"></i>IVA Compras</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('iva.posicion') ? 'active' : '' }}"
               href="{{ route('iva.posicion', ['periodo' => $periodo]) }}"><i class="bi bi-calculator me-1"></i>Posición mensual</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('iva.arca') ? 'active' : '' }}"
               href="{{ route('iva.arca', ['periodo' => $periodo]) }}"><i class="bi bi-cloud-upload me-1"></i>Exportar a ARCA</a>
        </li>
    </ul>

    <div class="d-flex gap-2 align-items-end">
        <form method="GET" class="d-flex gap-1 align-items-end">
            @foreach(request()->except(['periodo', 'formato', 'page']) as $k => $v)
                @if(is_scalar($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
            @endforeach
            @php
                $anterior = \Carbon\Carbon::createFromFormat('Y-m-d', $periodo . '-01')->subMonthNoOverflow()->format('Y-m');
                $siguiente = \Carbon\Carbon::createFromFormat('Y-m-d', $periodo . '-01')->addMonthNoOverflow()->format('Y-m');
            @endphp
            <a href="{{ request()->fullUrlWithQuery(['periodo' => $anterior, 'formato' => null]) }}" class="btn btn-sm btn-light border" title="Mes anterior"><i class="bi bi-chevron-left"></i></a>
            <input type="month" name="periodo" value="{{ $periodo }}" class="form-control form-control-sm" style="width: 150px;" onchange="this.form.submit()">
            <a href="{{ request()->fullUrlWithQuery(['periodo' => $siguiente, 'formato' => null]) }}" class="btn btn-sm btn-light border" title="Mes siguiente"><i class="bi bi-chevron-right"></i></a>
        </form>

        @if($exportable ?? true)
            <div class="btn-group btn-group-sm">
                <a href="{{ request()->fullUrlWithQuery(['formato' => 'pdf', 'periodo' => $periodo]) }}" target="_blank" class="btn btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
                @if(request()->routeIs('iva.libro'))
                    <a href="{{ request()->fullUrlWithQuery(['formato' => 'csv', 'periodo' => $periodo]) }}" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Excel</a>
                @endif
            </div>
        @endif
    </div>
</div>
