@extends('layouts.app')

@section('title', 'Ejercicios')
@section('header', 'Ejercicios contables')

@section('actions')
    @if(auth()->user()->puede('contador'))
        <a href="{{ route('ejercicios.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nuevo ejercicio</a>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>N°</th>
                    <th>Descripción</th>
                    <th>Inicio</th>
                    <th>Cierre</th>
                    <th>Bloqueado hasta</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($ejercicios as $ej)
                <tr class="{{ $ejercicioActivo?->id === $ej->id ? 'table-primary' : '' }}">
                    <td class="fw-semibold">{{ $ej->numero }}</td>
                    <td>
                        {{ $ej->descripcion }}
                        @if($ejercicioActivo?->id === $ej->id)<span class="badge text-bg-primary ms-1">En uso</span>@endif
                    </td>
                    <td>{{ $ej->fecha_inicio->format('d/m/Y') }}</td>
                    <td>{{ $ej->fecha_cierre->format('d/m/Y') }}</td>
                    <td>{{ $ej->bloqueado_hasta?->format('d/m/Y') ?? '—' }}</td>
                    <td>
                        @if($ej->estaAbierto())
                            <span class="badge text-bg-success">Abierto</span>
                        @else
                            <span class="badge text-bg-secondary">Cerrado</span>
                            @if($ej->cerrado_at)
                                <div class="small text-muted">con asientos de cierre · {{ $ej->cerrado_at->format('d/m/Y') }}</div>
                            @endif
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if($ejercicioActivo?->id !== $ej->id)
                            <form method="POST" action="{{ route('ejercicios.seleccionar', $ej) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-primary" title="Trabajar con este ejercicio"><i class="bi bi-box-arrow-in-right"></i></button>
                            </form>
                        @endif

                        @if(auth()->user()->puede('contador'))
                            <a href="{{ route('ejercicios.edit', $ej) }}" class="btn btn-sm btn-outline-secondary" title="Editar"><i class="bi bi-pencil"></i></a>
                            @if($ej->estaAbierto())
                                <a href="{{ route('cierre.show', $ej) }}" class="btn btn-sm btn-outline-dark" title="Cierre contable (refundición, cierre y apertura)"><i class="bi bi-journal-check"></i></a>
                            @endif
                        @endif

                        @if(auth()->user()->puede('administrador'))
                            <form method="POST" action="{{ route('ejercicios.estado', $ej) }}" class="d-inline"
                                  data-confirm="{{ $ej->estaAbierto() ? '¿Bloquear el ejercicio sin asientos de cierre? No se podrán registrar nuevos asientos. (Para el cierre contable use el botón de cierre.)' : ($ej->cerrado_at ? '¿Reabrir el ejercicio? Se anulan la refundición, el cierre y la apertura del ejercicio siguiente.' : '¿Reabrir el ejercicio?') }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-{{ $ej->estaAbierto() ? 'warning' : 'success' }}" title="{{ $ej->estaAbierto() ? 'Bloquear sin asientos de cierre' : 'Reabrir' }}">
                                    <i class="bi bi-{{ $ej->estaAbierto() ? 'lock' : 'unlock' }}"></i>
                                </button>
                            </form>

                            <form method="POST" action="{{ route('ejercicios.destroy', $ej) }}" class="d-inline" data-confirm="¿Eliminar el ejercicio N° {{ $ej->numero }}?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        No hay ejercicios cargados.
                        @if(auth()->user()->puede('contador'))<a href="{{ route('ejercicios.create') }}">Crear el primero</a>.@endif
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
