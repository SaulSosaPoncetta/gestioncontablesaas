@extends('layouts.app')

@section('title', 'Planes')
@section('header', 'Planes')

@section('actions')
    <a href="{{ route('admin.planes.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nuevo plan</a>
@endsection

@section('content')
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Orden</th><th>Plan</th><th class="text-end">Precio/mes</th><th class="text-center">Usuarios</th>
                    <th class="text-center">IVA</th><th class="text-center">Retenc.</th><th class="text-center">Integr.</th>
                    <th class="text-center">Empresas</th><th>Estado</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($planes as $p)
                <tr>
                    <td>{{ $p->orden }}</td>
                    <td>
                        <div class="fw-semibold">{{ $p->nombre }}</div>
                        <div class="small text-muted">{{ $p->descripcion }}</div>
                    </td>
                    <td class="num">{{ $p->precio_formateado }}</td>
                    <td class="text-center">{{ $p->max_usuarios ?? '∞' }}</td>
                    @foreach(['iva', 'retenciones', 'integraciones'] as $m)
                        <td class="text-center"><i class="bi bi-{{ $p->tieneModulo($m) ? 'check-lg text-success' : 'dash text-muted' }}"></i></td>
                    @endforeach
                    <td class="text-center">{{ $p->empresas_count }}</td>
                    <td>
                        @if($p->activo)
                            <span class="badge text-bg-success">Visible</span>
                        @else
                            <span class="badge text-bg-secondary">Oculto</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('admin.planes.edit', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No hay planes.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2">Los planes ocultos no aparecen en el registro, pero las empresas que ya los tienen los conservan.</p>
@endsection
