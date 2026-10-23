@php
    $esVenta = $circuito === 'venta';
    $fmt = fn ($v) => (float) $v != 0 ? number_format((float) $v, 2, ',', '.') : '';
    $filasAli = old('alicuotas', $alicuotas);
    if (empty($filasAli)) {
        $filasAli = [['alicuota_id' => 5, 'neto' => 0, 'iva' => 0]];
    }
    $discrimina = [];
    foreach (array_keys($tipos) as $t) {
        $discrimina[$t] = \App\Support\Afip::discriminaIva($t, $circuito);
    }
    $datosTerceros = $terceros->mapWithKeys(fn ($t) => [$t->id => [
        'cuenta'    => $t->cuenta_imputacion_id,
        'operacion' => $t->operacion_iva,
        'condicion' => $t->condicion_iva_texto,
        'doc'       => $t->documento_formateado,
    ]]);
    $volver = request()->fullUrl();
@endphp

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    .importe { text-align: right; font-variant-numeric: tabular-nums; }
    #total-comprobante { font-size: 1.4rem; font-weight: 700; font-variant-numeric: tabular-nums; }
</style>
@endpush

<div class="card mb-3">
    <div class="card-body row g-3">
        <div class="col-md-7">
            <label class="form-label d-flex justify-content-between">
                <span>{{ $esVenta ? 'Cliente' : 'Proveedor' }} *</span>
                <a href="{{ route('terceros.create', ['tipo' => $esVenta ? 'cliente' : 'proveedor', 'volver' => $volver]) }}" class="small">
                    <i class="bi bi-plus-lg"></i> Nuevo {{ $esVenta ? 'cliente' : 'proveedor' }}
                </a>
            </label>
            <select name="tercero_id" id="tercero_id" class="form-select @error('tercero_id') is-invalid @enderror" required>
                <option value="">Buscar…</option>
                @foreach($terceros as $t)
                    <option value="{{ $t->id }}" @selected(old('tercero_id', $comprobante->tercero_id) == $t->id)>{{ $t->razon_social }} · {{ $t->documento_formateado }}</option>
                @endforeach
            </select>
            @error('tercero_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="form-text" id="info-tercero"></div>
        </div>
        <div class="col-md-5">
            <label class="form-label">Tipo de comprobante *</label>
            <select name="tipo_comprobante" id="tipo_comprobante" class="form-select @error('tipo_comprobante') is-invalid @enderror">
                @foreach($tipos as $v => $t)
                    <option value="{{ $v }}" @selected((int) old('tipo_comprobante', $comprobante->tipo_comprobante) === $v)>{{ $t }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-4 col-md-2">
            <label class="form-label">Punto de venta *</label>
            <input type="number" name="punto_venta" value="{{ old('punto_venta', $comprobante->punto_venta) }}" min="1" max="99999" class="form-control @error('punto_venta') is-invalid @enderror" required>
        </div>
        <div class="col-8 col-md-3">
            <label class="form-label">Número *</label>
            <input type="number" name="numero" value="{{ old('numero', $comprobante->numero) }}" min="1" max="99999999" class="form-control @error('numero') is-invalid @enderror" required>
            @error('numero')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">Fecha *</label>
            <input type="date" name="fecha" id="fecha" value="{{ old('fecha', $comprobante->fecha?->format('Y-m-d')) }}" class="form-control @error('fecha') is-invalid @enderror" required>
            @error('fecha')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">Período IVA *</label>
            <input type="month" name="periodo_iva" id="periodo_iva" value="{{ old('periodo_iva', $comprobante->periodo_iva) }}" class="form-control @error('periodo_iva') is-invalid @enderror" required>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label">CAE</label>
            <input type="text" name="cae" value="{{ old('cae', $comprobante->cae) }}" maxlength="14" class="form-control @error('cae') is-invalid @enderror">
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label">Vto. CAE</label>
            <input type="date" name="vto_cae" value="{{ old('vto_cae', $comprobante->vto_cae?->format('Y-m-d')) }}" class="form-control px-1">
        </div>

        <div class="col-md-4">
            <label class="form-label">{{ $esVenta ? 'Tipo de operación (IVA)' : 'Concepto (IVA)' }} *</label>
            <select name="operacion_iva" id="operacion_iva" class="form-select @error('operacion_iva') is-invalid @enderror">
                @foreach($esVenta ? \App\Support\Afip::OPERACIONES_VENTA : \App\Support\Afip::CONCEPTOS_COMPRA as $v => $t)
                    <option value="{{ $v }}" @selected((int) old('operacion_iva', $comprobante->operacion_iva ?? 1) === $v)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-8">
            <label class="form-label">Imputación contable *</label>
            <select name="cuenta_imputacion_id" id="cuenta_imputacion_id" class="form-select @error('cuenta_imputacion_id') is-invalid @enderror">
                @foreach($cuentas as $c)
                    <option value="{{ $c->id }}" @selected(old('cuenta_imputacion_id', $comprobante->cuenta_imputacion_id) == $c->id)>{{ $c->codigo }} - {{ $c->nombre }}</option>
                @endforeach
            </select>
            @error('cuenta_imputacion_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="form-text">{{ $esVenta ? 'Cuenta de ventas / ingresos' : 'Cuenta de compra, gasto o bien de uso' }}. Se propone la habitual del {{ $esVenta ? 'cliente' : 'proveedor' }}.</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Importes</div>
            <div class="card-body">
                <div id="bloque-iva">
                    @error('alicuotas')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror
                    <table class="table table-sm mb-2" id="tabla-alicuotas">
                        <thead><tr><th style="width: 120px;">Alícuota</th><th class="text-end">Neto gravado</th><th class="text-end">IVA</th><th style="width: 30px;"></th></tr></thead>
                        <tbody>
                        @foreach($filasAli as $i => $a)
                            <tr>
                                <td>
                                    <select name="alicuotas[{{ $i }}][alicuota_id]" class="form-select form-select-sm alicuota">
                                        @foreach(\App\Support\Afip::ALICUOTAS as $id => $pct)
                                            <option value="{{ $id }}" data-pct="{{ $pct }}" @selected((int) ($a['alicuota_id'] ?? 5) === $id)>{{ str_replace('.', ',', $pct) }} %</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td><input type="text" inputmode="decimal" name="alicuotas[{{ $i }}][neto]" value="{{ $fmt($a['neto'] ?? 0) }}" class="form-control form-control-sm importe neto"></td>
                                <td>
                                    <input type="text" inputmode="decimal" name="alicuotas[{{ $i }}][iva]" value="{{ $fmt($a['iva'] ?? 0) }}" class="form-control form-control-sm importe iva @error("alicuotas.$i.iva") is-invalid @enderror">
                                    @error("alicuotas.$i.iva")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </td>
                                <td><button type="button" class="btn btn-sm btn-link text-danger p-0 quitar-ali" tabindex="-1"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" id="agregar-ali"><i class="bi bi-plus-lg"></i> Otra alícuota</button>
                </div>

                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small mb-1" id="label-no-gravado">No gravado</label>
                        <input type="text" inputmode="decimal" name="no_gravado" value="{{ $fmt(old('no_gravado', $comprobante->no_gravado)) }}" class="form-control form-control-sm importe extra">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Exento</label>
                        <input type="text" inputmode="decimal" name="exento" value="{{ $fmt(old('exento', $comprobante->exento)) }}" class="form-control form-control-sm importe extra">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Percepción IVA</label>
                        <input type="text" inputmode="decimal" name="percepcion_iva" value="{{ $fmt(old('percepcion_iva', $comprobante->percepcion_iva)) }}" class="form-control form-control-sm importe extra">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Percepción IIBB</label>
                        <input type="text" inputmode="decimal" name="percepcion_iibb" value="{{ $fmt(old('percepcion_iibb', $comprobante->percepcion_iibb)) }}" class="form-control form-control-sm importe extra">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Otros tributos / imp. internos</label>
                        <input type="text" inputmode="decimal" name="otros_tributos" value="{{ $fmt(old('otros_tributos', $comprobante->otros_tributos)) }}" class="form-control form-control-sm importe extra">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <div class="text-muted small">Total del comprobante</div>
                <div id="total-comprobante">$ 0,00</div>
                <div class="small text-muted mb-3" id="detalle-total"></div>
                @error('total')<div class="alert alert-danger py-1 small">{{ $message }}</div>@enderror

                <label class="form-label small mb-1">Observaciones</label>
                <input type="text" name="observaciones" value="{{ old('observaciones', $comprobante->observaciones) }}" maxlength="255" class="form-control form-control-sm mb-3">

                <div class="mt-auto d-flex flex-wrap gap-2">
                    <button name="accion" value="guardar" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                    @unless($comprobante->exists)
                        <button name="accion" value="otro" class="btn btn-outline-primary"><i class="bi bi-plus-circle me-1"></i>Guardar y cargar otro</button>
                    @endunless
                    <a href="{{ $comprobante->exists ? route('comprobantes.show', [$circuito, $comprobante]) : route('comprobantes.index', $circuito) }}" class="btn btn-link">Cancelar</a>
                </div>
                <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Al guardar se genera automáticamente el asiento contable.</div>
            </div>
        </div>
    </div>
</div>

<template id="tpl-ali">
    <tr>
        <td>
            <select name="alicuotas[__i__][alicuota_id]" class="form-select form-select-sm alicuota">
                @foreach(\App\Support\Afip::ALICUOTAS as $id => $pct)
                    <option value="{{ $id }}" data-pct="{{ $pct }}">{{ str_replace('.', ',', $pct) }} %</option>
                @endforeach
            </select>
        </td>
        <td><input type="text" inputmode="decimal" name="alicuotas[__i__][neto]" class="form-control form-control-sm importe neto"></td>
        <td><input type="text" inputmode="decimal" name="alicuotas[__i__][iva]" class="form-control form-control-sm importe iva"></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger p-0 quitar-ali" tabindex="-1"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
(function () {
    const DISCRIMINA = @json($discrimina);
    const TERCEROS = @json($datosTerceros);
    const esNuevo = {{ $comprobante->exists ? 'false' : 'true' }};
    const tbody = document.querySelector('#tabla-alicuotas tbody');
    let indice = tbody.querySelectorAll('tr').length;

    function aCentavos(v) {
        v = (v || '').toString().trim();
        if (!v) return 0;
        if (v.includes(',')) v = v.replace(/\./g, '').replace(',', '.');
        const n = parseFloat(v);
        return isNaN(n) ? 0 : Math.round(n * 100);
    }
    const fmt = c => (c / 100).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const cuentaTs = new TomSelect('#cuenta_imputacion_id', { maxOptions: 80 });
    const terceroTs = new TomSelect('#tercero_id', {
        maxOptions: 80,
        onChange: function (id) {
            const t = TERCEROS[id];
            document.getElementById('info-tercero').textContent = t ? t.condicion + ' · ' + t.doc : '';
            if (t && t.cuenta && esNuevo) cuentaTs.setValue(String(t.cuenta));
            const op = document.getElementById('operacion_iva');
            if ({{ $esVenta ? 'false' : 'true' }} && t && t.operacion && esNuevo && op.querySelector('option[value="' + t.operacion + '"]')) op.value = String(t.operacion);
        },
    });
    if (terceroTs.getValue()) {
        const t = TERCEROS[terceroTs.getValue()];
        if (t) document.getElementById('info-tercero').textContent = t.condicion + ' · ' + t.doc;
    }

    // Comprobantes que no discriminan IVA: se oculta la grilla de alícuotas
    const tipo = document.getElementById('tipo_comprobante');
    function actualizarTipo() {
        const disc = DISCRIMINA[tipo.value];
        document.getElementById('bloque-iva').classList.toggle('d-none', !disc);
        document.getElementById('label-no-gravado').textContent = disc ? 'No gravado' : 'Importe (sin IVA discriminado)';
        if (!disc) tbody.querySelectorAll('input').forEach(i => i.value = '');
        recalcular();
    }
    tipo.addEventListener('change', actualizarTipo);

    // El período IVA acompaña a la fecha mientras no se cambie a mano
    const fecha = document.getElementById('fecha'), periodo = document.getElementById('periodo_iva');
    let periodoManual = !esNuevo;
    periodo.addEventListener('change', () => periodoManual = true);
    fecha.addEventListener('change', () => { if (!periodoManual && fecha.value) periodo.value = fecha.value.slice(0, 7); });

    function recalcular() {
        let neto = 0, iva = 0, extra = 0;
        tbody.querySelectorAll('tr').forEach(tr => {
            neto += aCentavos(tr.querySelector('.neto').value);
            iva += aCentavos(tr.querySelector('.iva').value);
        });
        document.querySelectorAll('.extra').forEach(i => extra += aCentavos(i.value));
        document.getElementById('total-comprobante').textContent = '$ ' + fmt(neto + iva + extra);
        document.getElementById('detalle-total').textContent = DISCRIMINA[tipo.value]
            ? 'Neto gravado ' + fmt(neto) + ' + IVA ' + fmt(iva) + (extra ? ' + otros ' + fmt(extra) : '')
            : '';
    }

    // Al cargar el neto se calcula el IVA (se puede corregir a mano)
    tbody.addEventListener('input', function (e) {
        if (e.target.classList.contains('neto')) {
            const tr = e.target.closest('tr');
            const pct = parseFloat(tr.querySelector('.alicuota').selectedOptions[0].dataset.pct);
            const c = Math.round(aCentavos(e.target.value) * pct / 100);
            tr.querySelector('.iva').value = c ? fmt(c) : '';
        }
        recalcular();
    });
    tbody.addEventListener('change', function (e) {
        if (e.target.classList.contains('alicuota')) {
            const tr = e.target.closest('tr');
            tr.querySelector('.neto').dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
    tbody.addEventListener('click', function (e) {
        const b = e.target.closest('.quitar-ali');
        if (!b) return;
        if (tbody.querySelectorAll('tr').length > 1) b.closest('tr').remove();
        else b.closest('tr').querySelectorAll('input').forEach(i => i.value = '');
        recalcular();
    });
    document.getElementById('agregar-ali').addEventListener('click', function () {
        tbody.insertAdjacentHTML('beforeend', document.getElementById('tpl-ali').innerHTML.replace(/__i__/g, indice++));
        tbody.lastElementChild.querySelector('.alicuota').value = '4';
        tbody.lastElementChild.querySelector('.neto').focus();
    });

    document.addEventListener('input', e => { if (e.target.classList.contains('extra')) recalcular(); });
    document.addEventListener('focusout', e => {
        if (!e.target.classList || !e.target.classList.contains('importe')) return;
        const c = aCentavos(e.target.value);
        e.target.value = c ? fmt(c) : '';
    });

    // Enter no envía el formulario
    tipo.closest('form').addEventListener('keydown', e => {
        if (e.key === 'Enter' && e.target.tagName === 'INPUT') e.preventDefault();
    });

    actualizarTipo();
})();
</script>
@endpush
