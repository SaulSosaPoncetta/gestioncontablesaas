@extends('layouts.app')

@section('title', 'Ajuste por inflación')
@section('header', 'Ajuste por inflación (RT 6)')

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-md-8">
                <div class="fw-semibold">{{ $ejercicio->nombre }}</div>
                <div class="small text-muted">
                    Reexpresa las cuentas no monetarias (marcadas como «NM» en el plan de cuentas) a moneda del cierre,
                    con los índices FACPCE mes a mes. La contrapartida es el RECPAM y el capital social se mantiene nominal
                    (su reexpresión va a «Ajuste de capital»).
                </div>
            </div>
            <div class="col-md-4 text-md-end">
                @if($coeficiente)
                    <div class="small text-muted">Coeficiente {{ $mes_base }} → {{ $mes_cierre }}</div>
                    <div class="fs-4 fw-semibold">{{ number_format($coeficiente, 4, ',', '.') }}</div>
                @endif
            </div>
        </div>
    </div>
</div>

@foreach($errores as $e)
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i>{{ $e }}</div>
@endforeach

@if($faltantes)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        Faltan los índices de: <strong>{{ implode(', ', $faltantes) }}</strong>.
        @if(auth()->user()->es_superadmin)
            <a href="{{ route('admin.indices.index') }}" class="alert-link">Cargarlos</a>.
        @else
            Los carga el administrador del sistema apenas la FACPCE los publica.
        @endif
    </div>
@endif

@if($existente)
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-1"></i>
        Ya hay un ajuste registrado: <a href="{{ route('asientos.show', $existente) }}" class="alert-link">asiento N° {{ $existente->numero_formateado }}</a>
        del {{ $existente->fecha->format('d/m/Y') }}. Si lo vuelve a generar, ese asiento se anula y se registra uno nuevo con el cálculo actual.
    </div>
@endif

@if(! $faltantes)
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Cálculo al {{ $ejercicio->fecha_cierre->format('d/m/Y') }}</span>
        @if($filas && ! $errores && auth()->user()->puede('contador') && $ejercicio->admiteFecha($ejercicio->fecha_cierre))
            <form method="POST" action="{{ route('ajuste.generar') }}"
                  data-confirm="{{ $existente ? '¿Anular el ajuste anterior y registrar este?' : '¿Registrar el asiento de ajuste por inflación?' }}">
                @csrf
                <button class="btn btn-primary btn-sm"><i class="bi bi-check2-circle me-1"></i>{{ $existente ? 'Regenerar asiento' : 'Generar asiento' }}</button>
            </form>
        @endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Cuenta</th>
                    <th class="text-end">Saldo histórico</th>
                    <th class="text-end">Saldo reexpresado</th>
                    <th class="text-end">Ajuste</th>
                </tr>
            </thead>
            <tbody>
            @forelse($filas as $f)
                <tr>
                    <td>
                        {{ $f->cuenta->codigo_nombre }}
                        @if($f->destino->id !== $f->cuenta->id)
                            <div class="small text-muted">El ajuste se imputa a {{ $f->destino->codigo_nombre }}</div>
                        @endif
                    </td>
                    <td class="num">{{ number_format($f->historico, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($f->ajustado, 2, ',', '.') }}</td>
                    <td class="num {{ $f->ajuste < 0 ? 'text-danger' : '' }}">{{ number_format($f->ajuste, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No hay saldos de cuentas no monetarias para ajustar.</td></tr>
            @endforelse
            </tbody>
            @if($filas && $recpam)
                <tfoot class="table-light">
                    <tr class="fw-semibold">
                        <td>{{ $recpam->codigo_nombre }}</td>
                        <td colspan="2" class="small text-muted text-end fw-normal">
                            {{ $total_recpam < 0 ? 'Saldo acreedor (ganancia por exposición a la inflación)' : 'Saldo deudor (pérdida por exposición a la inflación)' }}
                        </td>
                        <td class="num">{{ number_format($total_recpam, 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
    <div class="card-body small text-muted border-top">
        Importes en términos de Debe − Haber (positivo = deudor). Las cuentas que se reexpresan se definen en el plan de cuentas
        (monetaria / no monetaria). Se toman los asientos registrados del ejercicio, sin refundición ni cierre.
    </div>
</div>
@endif
@endsection
