@extends('layouts.app')

@php
    $esVenta = $circuito === 'venta';
    $m = fn ($v) => number_format((float) $v, 2, ',', '.');
    $badge = ['nuevo' => 'success', 'duplicado' => 'secondary', 'error' => 'danger', 'omitido' => 'warning'];
    $textoEstado = ['nuevo' => 'A importar', 'duplicado' => 'Ya cargado', 'error' => 'Error', 'omitido' => 'Omitido'];
    $nuevos = collect($filas)->where('estado', 'nuevo');
@endphp

@section('title', 'Revisar importación')
@section('header', 'Revisar importación · ' . ($esVenta ? 'Emitidos (ventas)' : 'Recibidos (compras)'))

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    .tabla-imp td { font-size: .82rem; white-space: nowrap; }
    .tabla-imp .msg { white-space: normal; min-width: 220px; }
</style>
@endpush

@section('content')
@if(session('erroresImportacion'))
    <div class="card border-danger mb-3">
        <div class="card-header bg-danger text-white">No se pudieron importar {{ count(session('erroresImportacion')) }} comprobantes</div>
        <ul class="list-group list-group-flush small">
            @foreach(session('erroresImportacion') as $linea => $error)
                <li class="list-group-item">Línea {{ $linea }}: {{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    <span class="small text-muted"><i class="bi bi-file-earmark-spreadsheet"></i> {{ $archivo }}</span>
    @foreach($textoEstado as $estado => $texto)
        @if($conteo[$estado] ?? 0)
            <span class="badge text-bg-{{ $badge[$estado] }}">{{ $texto }}: {{ $conteo[$estado] }}</span>
        @endif
    @endforeach
    <span class="ms-auto small">Total a importar: <strong>$ {{ $m($nuevos->sum(fn ($f) => \App\Support\Afip::esNotaCredito($f['tipo']) ? -$f['total'] : $f['total'])) }}</strong></span>
</div>

<form method="POST" action="{{ route('importacion.confirmar', $token) }}">
    @csrf

    <div class="card mb-3">
        <div class="card-header bg-white fw-semibold">Opciones de importación</div>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Imputación contable por defecto *</label>
                <select name="cuenta_imputacion_id" id="cuenta_imp" class="form-select @error('cuenta_imputacion_id') is-invalid @enderror">
                    @foreach($cuentas as $c)
                        <option value="{{ $c->id }}" @selected(old('cuenta_imputacion_id', $cuentaDefecto) == $c->id)>{{ $c->codigo }} - {{ $c->nombre }}</option>
                    @endforeach
                </select>
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="usar_habitual" value="1" id="habitual" checked>
                    <label class="form-check-label small" for="habitual">Si el {{ $esVenta ? 'cliente' : 'proveedor' }} tiene imputación habitual, usar esa</label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">{{ $esVenta ? 'Tipo de operación' : 'Concepto (IVA)' }}</label>
                <select name="operacion_iva" class="form-select">
                    @foreach($esVenta ? \App\Support\Afip::OPERACIONES_VENTA : \App\Support\Afip::CONCEPTOS_COMPRA as $v => $t)
                        <option value="{{ $v }}" @selected((int) old('operacion_iva', 1) === $v)>{{ $t }}</option>
                    @endforeach
                </select>
                @unless($esVenta)<div class="form-text">Se usa el habitual del proveedor si lo tiene.</div>@endunless
            </div>
            <div class="col-md-3">
                <label class="form-label">Período IVA</label>
                <input type="month" name="periodo" value="{{ old('periodo') }}" class="form-control">
                <div class="form-text">Vacío = el mes de la fecha de cada comprobante.</div>
            </div>
        </div>
    </div>

    @error('seleccion')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror

    <div class="card mb-3">
        <div class="table-responsive" style="max-height: 60vh;">
            <table class="table table-sm table-hover align-middle mb-0 tabla-imp">
                <thead class="table-light sticky-top">
                    <tr>
                        <th><input type="checkbox" class="form-check-input" id="todos" checked title="Seleccionar todos"></th>
                        <th>Línea</th><th>Estado</th><th>Fecha</th><th>Comprobante</th><th>{{ $esVenta ? 'Cliente' : 'Proveedor' }}</th>
                        <th class="text-end">Neto grav.</th><th class="text-end">IVA</th><th class="text-end">No grav./Exento</th><th class="text-end">Otros</th><th class="text-end">Total</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($filas as $f)
                    @php
                        $neto = array_sum(array_column($f['alicuotas'], 'neto'));
                        $esNc = $f['tipo'] && \App\Support\Afip::esNotaCredito($f['tipo']);
                        $otros = $f['perc_iva'] + $f['perc_iibb'] + $f['otros_tributos'];
                    @endphp
                    <tr class="{{ $f['estado'] !== 'nuevo' ? 'text-muted' : '' }}">
                        <td>
                            @if($f['estado'] === 'nuevo')
                                <input type="checkbox" class="form-check-input fila" name="seleccion[]" value="{{ $f['linea'] }}" checked>
                            @endif
                        </td>
                        <td>{{ $f['linea'] }}</td>
                        <td><span class="badge text-bg-{{ $badge[$f['estado']] }}">{{ $textoEstado[$f['estado']] }}</span></td>
                        <td>{{ $f['fecha'] ? \Carbon\Carbon::parse($f['fecha'])->format('d/m/Y') : '—' }}</td>
                        <td class="font-monospace">{{ $f['tipo'] ? \App\Support\Afip::abreviatura($f['tipo']) : '?' }} {{ \App\Support\Afip::numeroFormateado($f['pv'], $f['numero']) }}</td>
                        <td>
                            {{ \Illuminate\Support\Str::limit($f['tercero_nombre'] ?? $f['denominacion'], 32) }}
                            <div class="text-muted" style="font-size: .72rem;">{{ $f['nro_doc'] ?? 'sin documento' }}</div>
                        </td>
                        <td class="num">{{ $neto ? $m($neto) : '' }}</td>
                        <td class="num">{{ $f['iva'] ? $m($f['iva']) : '' }}
                            @if(count($f['alicuotas']) > 1)<i class="bi bi-layers text-info" title="Varias alícuotas"></i>@endif
                        </td>
                        <td class="num">{{ ($f['no_gravado'] + $f['exento']) ? $m($f['no_gravado'] + $f['exento']) : '' }}</td>
                        <td class="num">{{ $otros ? $m($otros) : '' }}</td>
                        <td class="num fw-semibold {{ $esNc ? 'text-danger' : '' }}">{{ $esNc ? '-' : '' }}{{ $m($f['total']) }}</td>
                        <td class="msg small {{ $f['estado'] === 'error' ? 'text-danger' : 'text-muted' }}">{{ implode(' ', $f['mensajes'] ?? []) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2 align-items-center">
        <button class="btn btn-primary" {{ $nuevos->isEmpty() ? 'disabled' : '' }}
                onclick="return confirm('Se importarán los comprobantes seleccionados y se generarán sus asientos. ¿Continuar?')">
            <i class="bi bi-cloud-download me-1"></i>Importar seleccionados (<span id="cant">{{ $nuevos->count() }}</span>)
        </button>
        <a href="{{ route('importacion.form', ['circuito' => $circuito]) }}" class="btn btn-outline-secondary">Subir otro archivo</a>
    </div>
</form>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    new TomSelect('#cuenta_imp', { maxOptions: 80 });
    const todos = document.getElementById('todos');
    const contar = () => document.getElementById('cant').textContent = document.querySelectorAll('.fila:checked').length;
    todos.addEventListener('change', () => { document.querySelectorAll('.fila').forEach(c => c.checked = todos.checked); contar(); });
    document.querySelectorAll('.fila').forEach(c => c.addEventListener('change', contar));
</script>
@endpush
