@extends('layouts.app')

@section('title', 'Asientos')
@section('header', 'Asientos contables')

@section('actions')
    @if(auth()->user()->puede('operador') && $ejercicio?->estaAbierto())
        <a href="{{ route('asientos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nuevo asiento</a>
    @endif
    @if(auth()->user()->puede('contador') && $ejercicio?->estaAbierto())
        <form method="POST" action="{{ route('asientos.renumerar') }}" class="d-inline"
              data-confirm="Se renumerarán todos los asientos del ejercicio en orden de fecha (apertura primero, cierre último). ¿Continuar?">
            @csrf
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-sort-numeric-down me-1"></i>Renumerar</button>
        </form>
    @endif
@endsection

@section('content')
@if(! $ejercicio)
    <div class="alert alert-warning">No hay ejercicio seleccionado. <a href="{{ route('ejercicios.create') }}">Cree un ejercicio</a> para empezar a cargar asientos.</div>
@else
    @if($borradores > 0)
        <div class="alert alert-info py-2 small">
            <i class="bi bi-pencil-square me-1"></i>
            Hay <strong>{{ $borradores }}</strong> {{ $borradores === 1 ? 'asiento en borrador' : 'asientos en borrador' }}: no impactan en los libros hasta que se registren.
            <a href="{{ route('asientos.index', ['estado' => 'borrador']) }}" class="alert-link">Ver</a>
        </div>
    @endif

    <div class="card">
        <div class="card-body border-bottom">
            <form class="row g-2" method="GET">
                <div class="col-6 col-md-2">
                    <input type="date" name="desde" value="{{ request('desde') }}" class="form-control form-control-sm" title="Desde">
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" name="hasta" value="{{ request('hasta') }}" class="form-control form-control-sm" title="Hasta">
                </div>
                <div class="col-4 col-md-1">
                    <input type="number" name="numero" value="{{ request('numero') }}" class="form-control form-control-sm" placeholder="N°">
                </div>
                <div class="col-8 col-md-3">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Concepto">
                </div>
                <div class="col-6 col-md-2">
                    <select name="estado" class="form-select form-select-sm">
                        <option value="">Todos los estados</option>
                        @foreach(\App\Models\Asiento::ESTADOS as $v => $t)
                            <option value="{{ $v }}" @selected(request('estado') === $v)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select name="tipo" class="form-select form-select-sm">
                        <option value="">Todos los tipos</option>
                        @foreach(\App\Models\Asiento::TIPOS as $v => $t)
                            <option value="{{ $v }}" @selected(request('tipo') === $v)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <select name="cuenta" class="form-select form-select-sm">
                        <option value="">Cualquier cuenta</option>
                        @foreach($cuentas as $c)
                            <option value="{{ $c->id }}" @selected(request('cuenta') == $c->id)>{{ $c->codigo }} - {{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
                    @if(request()->hasAny(['desde', 'hasta', 'numero', 'q', 'estado', 'tipo', 'cuenta']))
                        <a href="{{ route('asientos.index') }}" class="btn btn-sm btn-link">Limpiar</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 90px;">N°</th>
                        <th style="width: 110px;">Fecha</th>
                        <th>Concepto</th>
                        <th>Tipo</th>
                        <th class="text-end">Importe</th>
                        <th>Estado</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($asientos as $a)
                    <tr class="{{ $a->estaAnulado() ? 'text-muted' : '' }}" style="cursor: pointer;" onclick="if(!event.target.closest('a,button,form')) location='{{ route('asientos.show', $a) }}'">
                        <td class="font-monospace">{{ $a->numero_formateado }}</td>
                        <td>{{ $a->fecha->format('d/m/Y') }}</td>
                        <td>
                            <span class="{{ $a->estaAnulado() ? 'text-decoration-line-through' : '' }}">{{ $a->concepto }}</span>
                            @if($a->origen !== 'manual')<span class="badge text-bg-light border ms-1">{{ $a->origen }}</span>@endif
                        </td>
                        <td class="small">{{ \App\Models\Asiento::TIPOS[$a->tipo] ?? $a->tipo }}</td>
                        <td class="num">{{ number_format($a->total, 2, ',', '.') }}</td>
                        <td>
                            @switch($a->estado)
                                @case('registrado')<span class="badge text-bg-success">Registrado</span>@break
                                @case('borrador')<span class="badge text-bg-warning">Borrador</span>@break
                                @case('anulado')<span class="badge text-bg-secondary">Anulado</span>@break
                            @endswitch
                        </td>
                        <td class="text-end text-nowrap">
                            @if(auth()->user()->puede('operador') && $a->esEditableManualmente())
                                <a href="{{ route('asientos.edit', $a) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            No hay asientos {{ request()->hasAny(['desde', 'hasta', 'numero', 'q', 'estado', 'tipo', 'cuenta']) ? 'con ese criterio' : 'cargados en este ejercicio' }}.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($asientos->hasPages())
            <div class="card-body">{{ $asientos->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
@endif
@endsection
