@extends('layouts.app')

@section('title', 'Clientes y proveedores')
@section('header', 'Clientes y proveedores')

@section('actions')
    @if(auth()->user()->puede('operador'))
        <a href="{{ route('terceros.create', ['tipo' => 'cliente']) }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nuevo cliente</a>
        <a href="{{ route('terceros.create', ['tipo' => 'proveedor']) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nuevo proveedor</a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="card-body border-bottom">
        <form class="row g-2" method="GET">
            <div class="col-md-5">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Razón social o CUIT / DNI">
            </div>
            <div class="col-6 col-md-3">
                <select name="tipo" class="form-select form-select-sm">
                    <option value="">Clientes y proveedores</option>
                    <option value="cliente" @selected(request('tipo') === 'cliente')>Clientes</option>
                    <option value="proveedor" @selected(request('tipo') === 'proveedor')>Proveedores</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="activos" @selected(request('estado') === 'activos')>Activos</option>
                    <option value="inactivos" @selected(request('estado') === 'inactivos')>Inactivos</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> Buscar</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Razón social</th><th>Documento</th><th>Condición IVA</th><th>Tipo</th><th class="text-center">Comprob.</th><th class="text-end"></th></tr>
            </thead>
            <tbody>
            @forelse($terceros as $t)
                <tr class="{{ $t->activo ? '' : 'text-muted' }}">
                    <td>
                        <div class="fw-semibold">{{ $t->razon_social }}</div>
                        @if($t->localidad)<div class="small text-muted">{{ $t->localidad }}{{ $t->provincia ? ', ' . $t->provincia : '' }}</div>@endif
                    </td>
                    <td class="small">{{ $t->documento_formateado }}</td>
                    <td class="small">{{ $t->condicion_iva_texto }}</td>
                    <td>
                        <span class="badge {{ $t->tipo === 'proveedor' ? 'text-bg-warning' : ($t->tipo === 'ambos' ? 'text-bg-info' : 'text-bg-primary') }}">{{ \App\Models\Tercero::TIPOS[$t->tipo] }}</span>
                        @unless($t->activo)<span class="badge text-bg-light border">inactivo</span>@endunless
                    </td>
                    <td class="text-center">{{ $t->comprobantes_count }}</td>
                    <td class="text-end text-nowrap">
                        @if(auth()->user()->puede('operador'))
                            <a href="{{ route('terceros.edit', $t) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                        @endif
                        @if(auth()->user()->puede('contador') && $t->comprobantes_count === 0)
                            <form method="POST" action="{{ route('terceros.destroy', $t) }}" class="d-inline" data-confirm="¿Eliminar {{ $t->razon_social }}?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No hay clientes ni proveedores cargados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($terceros->hasPages())
        <div class="card-body">{{ $terceros->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
