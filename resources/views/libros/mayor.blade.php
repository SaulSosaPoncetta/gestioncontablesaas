@extends('layouts.app')

@section('title', 'Libro Mayor')
@section('header', 'Libros y balances')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
@endpush

@section('content')
@include('libros._nav', ['exportable' => $mayor !== null || $todas])

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET" id="form-mayor">
            <div class="col-md-6">
                <label class="form-label small mb-1">Cuenta</label>
                <select name="cuenta" id="sel-cuenta" class="form-select form-select-sm">
                    <option value="">Seleccione una cuenta…</option>
                    <option value="todas" @selected($todas)>— Todas las cuentas con movimientos (solo PDF / Excel) —</option>
                    @foreach($cuentas as $c)
                        <option value="{{ $c->id }}" @selected($cuenta?->id === $c->id)>{{ $c->codigo }} - {{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Consultar</button>
            </div>
        </form>
    </div>
</div>

@if($todas)
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-1"></i>
        El mayor de todas las cuentas se genera en <strong>PDF</strong> o <strong>Excel</strong> con los botones de arriba.
    </div>
@elseif(! $mayor)
    <div class="card"><div class="card-body text-center text-muted py-5">
        <i class="bi bi-journal-bookmark" style="font-size: 2.5rem;"></i>
        <p class="mt-2 mb-0">Elija una cuenta para ver su mayor.</p>
    </div></div>
@else
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between flex-wrap gap-2">
            <div>
                <span class="font-monospace text-muted me-2">{{ $mayor['cuenta']->codigo }}</span>
                <span class="fw-semibold">{{ $mayor['cuenta']->nombre }}</span>
                <span class="small text-muted ms-2">saldo habitual {{ $mayor['cuenta']->saldo_habitual }}</span>
            </div>
            <div class="small">
                Saldo al {{ $hasta->format('d/m/Y') }}:
                <strong class="{{ $mayor['saldo'] < 0 ? 'text-danger' : '' }}">
                    $ {{ number_format(abs($mayor['saldo']), 2, ',', '.') }} {{ $mayor['saldo'] > 0 ? 'D' : ($mayor['saldo'] < 0 ? 'A' : '') }}
                </strong>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 100px;">Fecha</th>
                        <th style="width: 80px;">Asiento</th>
                        <th>Concepto</th>
                        <th class="text-end" style="width: 140px;">Debe</th>
                        <th class="text-end" style="width: 140px;">Haber</th>
                        <th class="text-end" style="width: 160px;">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-light">
                        <td colspan="3" class="fst-italic">Saldo anterior al {{ $desde->format('d/m/Y') }}</td>
                        <td></td><td></td>
                        <td class="num">{{ number_format(abs($mayor['anterior']), 2, ',', '.') }} {{ $mayor['anterior'] > 0 ? 'D' : ($mayor['anterior'] < 0 ? 'A' : '') }}</td>
                    </tr>
                    @forelse($mayor['movimientos'] as $m)
                        <tr>
                            <td>{{ $m->fecha->format('d/m/Y') }}</td>
                            <td><a href="{{ route('asientos.show', $m->asiento_id) }}" class="text-decoration-none font-monospace">{{ $m->numero }}</a></td>
                            <td>{{ $m->concepto }}@if($m->detalle)<span class="small text-muted"> — {{ $m->detalle }}</span>@endif</td>
                            <td class="num">{{ $m->debe > 0 ? number_format($m->debe, 2, ',', '.') : '' }}</td>
                            <td class="num">{{ $m->haber > 0 ? number_format($m->haber, 2, ',', '.') : '' }}</td>
                            <td class="num {{ $m->saldo < 0 ? 'text-danger' : '' }}">{{ number_format(abs($m->saldo), 2, ',', '.') }} {{ $m->saldo > 0 ? 'D' : ($m->saldo < 0 ? 'A' : '') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">Sin movimientos en el período.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="table-light fw-semibold">
                    <tr>
                        <td colspan="3" class="text-end">Totales del período</td>
                        <td class="num">{{ number_format($mayor['debe'], 2, ',', '.') }}</td>
                        <td class="num">{{ number_format($mayor['haber'], 2, ',', '.') }}</td>
                        <td class="num">{{ number_format(abs($mayor['saldo']), 2, ',', '.') }} {{ $mayor['saldo'] > 0 ? 'D' : ($mayor['saldo'] < 0 ? 'A' : '') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    new TomSelect('#sel-cuenta', {
        maxOptions: 80,
        onChange: function (v) { if (v && v !== 'todas') document.getElementById('form-mayor').submit(); },
    });
</script>
@endpush
