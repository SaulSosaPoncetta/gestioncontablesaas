@extends('layouts.app')

@section('title', 'Índices de inflación')
@section('header', 'Índices de inflación (FACPCE)')

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Cargar un mes</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.indices.store') }}" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-5">
                        <label class="form-label small mb-1">Mes</label>
                        <input type="month" name="periodo" class="form-control form-control-sm" required
                               value="{{ old('periodo', $ultimo ? $ultimo->periodo->copy()->addMonth()->format('Y-m') : now()->format('Y-m')) }}">
                    </div>
                    <div class="col-7">
                        <label class="form-label small mb-1">Índice</label>
                        <input type="text" name="indice" class="form-control form-control-sm" required value="{{ old('indice') }}" placeholder="Ej: 9876,5432">
                    </div>
                    <div class="col-12">
                        <input type="text" name="fuente" class="form-control form-control-sm" value="{{ old('fuente', 'FACPCE') }}" placeholder="Fuente">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold">Importar la serie</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.indices.importar') }}" enctype="multipart/form-data">
                    @csrf
                    <p class="small text-muted mb-2">
                        Una línea por mes: <code>periodo;indice</code>. Sirven <code>2026-01</code>, <code>01/2026</code> o <code>01/01/2026</code>,
                        y el índice con coma o punto decimal. Se puede copiar las dos columnas de la planilla de la FACPCE y pegarlas.
                        Si el mes ya existe, se actualiza.
                    </p>
                    <textarea name="texto" rows="8" class="form-control form-control-sm font-monospace mb-2" placeholder="2026-01;9876,5432&#10;2026-02;10012,3456"></textarea>
                    <div class="mb-2">
                        <input type="file" name="archivo" accept=".csv,.txt" class="form-control form-control-sm">
                    </div>
                    <input type="hidden" name="fuente" value="FACPCE">
                    <button class="btn btn-outline-primary btn-sm"><i class="bi bi-upload me-1"></i>Importar</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between">
                <span class="fw-semibold">Serie cargada</span>
                @if($ultimo)<span class="small text-muted">Último: {{ $ultimo->periodo->format('m/Y') }}</span>@endif
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Mes</th><th class="text-end">Índice</th><th>Fuente</th><th></th></tr></thead>
                    <tbody>
                    @forelse($indices as $i)
                        <tr>
                            <td>{{ $i->periodo->format('m/Y') }}</td>
                            <td class="num">{{ number_format((float) $i->indice, 4, ',', '.') }}</td>
                            <td class="small text-muted">{{ $i->fuente }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.indices.destroy', $i) }}" data-confirm="¿Eliminar el índice de {{ $i->periodo->format('m/Y') }}?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Todavía no hay índices cargados.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($indices->hasPages())
                <div class="card-body">{{ $indices->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
