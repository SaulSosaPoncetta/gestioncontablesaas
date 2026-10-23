@extends('layouts.app')

@section('title', 'Cierre del ejercicio')
@section('header', 'Cierre del ejercicio N° ' . $ejercicio->numero)

@section('actions')
    <a href="{{ route('ejercicios.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Ejercicios</a>
@endsection

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">{{ $ejercicio->nombre }}</div>
            <div class="card-body">
                <p class="mb-2">Al cerrar, el sistema registra automáticamente:</p>
                <ol class="mb-3">
                    <li><strong>Refundición</strong> de las cuentas de resultado contra «Resultado del ejercicio» ({{ $cuentas_resultado }} cuenta(s)).</li>
                    <li><strong>Asiento de cierre</strong> de las cuentas patrimoniales ({{ $cuentas_patrimonio }} cuenta(s) con saldo).</li>
                    <li><strong>Apertura</strong> del
                        @if($siguiente)
                            ejercicio N° {{ $siguiente->numero }} ({{ $siguiente->fecha_inicio->format('d/m/Y') }})
                        @else
                            ejercicio siguiente, que se crea solo ({{ $ejercicio->fecha_cierre->copy()->addDay()->format('d/m/Y') }})
                        @endif
                        con los mismos saldos; el resultado pasa a «Resultados no asignados».
                    </li>
                </ol>
                <div class="p-3 rounded bg-light d-flex justify-content-between align-items-center">
                    <span>Resultado del ejercicio</span>
                    <span class="fs-5 fw-semibold {{ $resultado < 0 ? 'text-danger' : 'text-success' }}">
                        {{ $resultado < 0 ? 'Pérdida' : 'Ganancia' }} $ {{ number_format(abs($resultado), 2, ',', '.') }}
                    </span>
                </div>
                <div class="small text-muted mt-2">
                    Si después hay que corregir algo, el ejercicio se puede reabrir: se anulan esos asientos y vuelve a quedar abierto.
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Controles</div>
            <ul class="list-group list-group-flush">
                @foreach($bloqueos as $b)
                    <li class="list-group-item text-danger"><i class="bi bi-x-circle me-1"></i>{{ $b }}</li>
                @endforeach
                @foreach($avisos as $a)
                    <li class="list-group-item text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>{{ $a }}</li>
                @endforeach
                @if(! $bloqueos && ! $avisos)
                    <li class="list-group-item text-success"><i class="bi bi-check-circle me-1"></i>Todo en orden para cerrar.</li>
                @endif
            </ul>
            <div class="card-body border-top">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="{{ route('libros.sumas') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-table me-1"></i>Sumas y saldos</a>
                    <a href="{{ route('ajuste.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-graph-up-arrow me-1"></i>Ajuste por inflación</a>
                </div>

                @if(! $bloqueos && auth()->user()->puede('administrador'))
                    <form method="POST" action="{{ route('cierre.cerrar', $ejercicio) }}"
                          data-confirm="¿Cerrar el ejercicio N° {{ $ejercicio->numero }}? Se registran la refundición, el cierre y la apertura del siguiente.">
                        @csrf
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="confirmo" value="1" id="confirmo" required>
                            <label class="form-check-label small" for="confirmo">Revisé el balance de sumas y saldos y los estados contables.</label>
                        </div>
                        <button class="btn btn-danger w-100"><i class="bi bi-lock me-1"></i>Cerrar el ejercicio</button>
                    </form>
                @elseif(! auth()->user()->puede('administrador'))
                    <div class="small text-muted">El cierre lo hace un usuario administrador.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
