@extends('layouts.app')

@section('title', 'Estado de Resultados')
@section('header', 'Estados contables')

@section('content')
@include('estados._nav')

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1">Detalle</label>
                <select name="nivel" class="form-select form-select-sm">
                    <option value="2" @selected($nivel === 2)>Resumido</option>
                    <option value="3" @selected($nivel === 3)>Por rubros</option>
                    <option value="4" @selected($nivel === 4)>Analítico (cuentas)</option>
                </select>
            </div>
            <div class="col-md-3">
                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" name="comparativo" value="1" id="comp" @checked(request()->boolean('comparativo'))>
                    <label class="form-check-label small" for="comp">Comparativo con el ejercicio anterior</label>
                </div>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Consultar</button>
            </div>
        </form>
        @if(request()->boolean('comparativo') && ! $anterior)
            <div class="small text-warning mt-2"><i class="bi bi-info-circle"></i> No hay un ejercicio anterior cargado para comparar.</div>
        @endif
    </div>
</div>

@php $comp = $anterior !== null; @endphp

<div class="card estado">
    <div class="card-header bg-white text-center">
        <div class="fw-semibold">ESTADO DE RESULTADOS</div>
        <div class="small text-muted">del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }} · en pesos</div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Concepto</th>
                    <th class="text-end" style="width: 170px;">Período actual</th>
                    @if($comp)<th class="text-end" style="width: 170px;">Ejercicio anterior</th>@endif
                </tr>
            </thead>
            <tbody>
                @if(empty($filas) || count(array_filter($filas, fn ($f) => $f->tipo === 'linea')) === 0)
                    <tr><td colspan="{{ $comp ? 3 : 2 }}" class="text-center text-muted py-4">No hay movimientos en cuentas de resultado en el período.</td></tr>
                @endif
                @include('estados._filas', ['filas' => $filas, 'comparativo' => $comp])
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    @if($resultado > 0)
        <span class="badge text-bg-success fs-6"><i class="bi bi-arrow-up-right"></i> Ganancia: $ {{ number_format($resultado, 2, ',', '.') }}</span>
    @elseif($resultado < 0)
        <span class="badge text-bg-danger fs-6"><i class="bi bi-arrow-down-right"></i> Pérdida: $ {{ number_format(-$resultado, 2, ',', '.') }}</span>
    @endif
</div>
<p class="small text-muted mt-2">Los egresos se muestran entre paréntesis. Las secciones se arman con el nivel 2 del plan de cuentas (4.1 ventas, 5.1 costo, 5.2 comercialización, 5.3 administración, 4.2/5.4 financieros, 5.5 impuesto); lo que no encaje va a "Otros ingresos y egresos".</p>
@endsection
