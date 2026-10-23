@php
    $filas = old('lineas', $lineas);
    $minimo = $asiento->exists ? 2 : 4;
    while (count($filas) < $minimo) {
        $filas[] = ['cuenta_id' => null, 'debe' => 0, 'haber' => 0, 'detalle' => null];
    }
    $fmt = fn ($v) => (float) $v > 0 ? number_format((float) $v, 2, ',', '.') : '';
    $registrado = $asiento->exists && $asiento->estado === 'registrado';
    $opcionesCuentas = $cuentas->map(fn ($c) => [
        'id'   => $c->id,
        'text' => $c->codigo . ' - ' . $c->nombre . ($c->activa ? '' : ' (inactiva)'),
    ])->values();
@endphp

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style>
    #tabla-lineas td { vertical-align: top; padding: .3rem; }
    #tabla-lineas .importe { text-align: right; font-variant-numeric: tabular-nums; }
    #tabla-lineas .ts-wrapper.form-select { padding: 0; }
    #tabla-lineas .ts-control { min-height: 31px; padding: .2rem .5rem; font-size: .875rem; }
    #totales td { font-weight: 600; font-variant-numeric: tabular-nums; }
    .dif-ok { color: #198754; } .dif-mal { color: #dc3545; }
</style>
@endpush

<div class="card mb-3">
    <div class="card-body row g-3">
        <div class="col-md-3">
            <label class="form-label">Fecha *</label>
            <input type="date" name="fecha" value="{{ old('fecha', $asiento->fecha?->format('Y-m-d')) }}"
                   min="{{ $ejercicio->fecha_inicio->format('Y-m-d') }}" max="{{ $ejercicio->fecha_cierre->format('Y-m-d') }}"
                   class="form-control @error('fecha') is-invalid @enderror" required>
            @error('fecha')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-3">
            <label class="form-label">Tipo *</label>
            <select name="tipo" class="form-select">
                @foreach(\App\Models\Asiento::TIPOS as $v => $t)
                    <option value="{{ $v }}" @selected(old('tipo', $asiento->tipo) === $v)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Concepto *</label>
            <input type="text" name="concepto" value="{{ old('concepto', $asiento->concepto) }}" maxlength="255"
                   class="form-control @error('concepto') is-invalid @enderror" placeholder="Ej: Venta según factura B 0001-00001234" required>
        </div>
    </div>
</div>

@error('lineas')
    <div class="alert alert-danger py-2">{{ $message }}</div>
@enderror

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table table-sm mb-0" id="tabla-lineas">
            <thead class="table-light">
                <tr>
                    <th style="width: 34px;">#</th>
                    <th style="min-width: 320px;">Cuenta</th>
                    <th style="width: 150px;" class="text-end">Debe</th>
                    <th style="width: 150px;" class="text-end">Haber</th>
                    <th style="min-width: 180px;">Detalle</th>
                    <th style="width: 40px;"></th>
                </tr>
            </thead>
            <tbody>
            @foreach($filas as $i => $f)
                <tr class="linea">
                    <td class="text-muted small pt-2 nro">{{ $i + 1 }}</td>
                    <td>
                        <select name="lineas[{{ $i }}][cuenta_id]" class="form-select form-select-sm cuenta @error("lineas.$i.cuenta_id") is-invalid @enderror"
                                data-valor="{{ $f['cuenta_id'] ?? '' }}"></select>
                        @error("lineas.$i.cuenta_id")<div class="text-danger small">{{ $message }}</div>@enderror
                    </td>
                    <td>
                        <input type="text" inputmode="decimal" name="lineas[{{ $i }}][debe]" value="{{ $fmt($f['debe'] ?? 0) }}"
                               class="form-control form-control-sm importe debe @error("lineas.$i.debe") is-invalid @enderror" autocomplete="off">
                        @error("lineas.$i.debe")<div class="text-danger small">{{ $message }}</div>@enderror
                    </td>
                    <td>
                        <input type="text" inputmode="decimal" name="lineas[{{ $i }}][haber]" value="{{ $fmt($f['haber'] ?? 0) }}"
                               class="form-control form-control-sm importe haber" autocomplete="off">
                    </td>
                    <td>
                        <input type="text" name="lineas[{{ $i }}][detalle]" value="{{ $f['detalle'] ?? '' }}" maxlength="255" class="form-control form-control-sm detalle">
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 mt-1 quitar" title="Quitar línea" tabindex="-1"><i class="bi bi-x-lg"></i></button>
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot>
                <tr id="totales" class="table-light">
                    <td></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="agregar"><i class="bi bi-plus-lg"></i> Agregar línea</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="balancear" title="Carga la diferencia en la última línea"><i class="bi bi-distribute-vertical"></i> Balancear</button>
                        <span class="float-end pt-1">Totales</span>
                    </td>
                    <td class="text-end pt-2" id="total-debe">0,00</td>
                    <td class="text-end pt-2" id="total-haber">0,00</td>
                    <td class="pt-2" colspan="2"><span id="diferencia"></span></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<template id="tpl-linea">
    <tr class="linea">
        <td class="text-muted small pt-2 nro"></td>
        <td><select name="lineas[__i__][cuenta_id]" class="form-select form-select-sm cuenta" data-valor=""></select></td>
        <td><input type="text" inputmode="decimal" name="lineas[__i__][debe]" class="form-control form-control-sm importe debe" autocomplete="off"></td>
        <td><input type="text" inputmode="decimal" name="lineas[__i__][haber]" class="form-control form-control-sm importe haber" autocomplete="off"></td>
        <td><input type="text" name="lineas[__i__][detalle]" maxlength="255" class="form-control form-control-sm detalle"></td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger p-0 mt-1 quitar" title="Quitar línea" tabindex="-1"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>

<div class="d-flex flex-wrap gap-2 align-items-center">
    @if($registrado)
        <button name="accion" value="registrar" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
    @else
        <button name="accion" value="registrar" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Registrar</button>
        @unless($asiento->exists)
            <button name="accion" value="registrar_otro" class="btn btn-outline-primary"><i class="bi bi-plus-circle me-1"></i>Registrar y cargar otro</button>
        @endunless
        <button name="accion" value="borrador" class="btn btn-outline-secondary" formnovalidate><i class="bi bi-save me-1"></i>Guardar borrador</button>
    @endif
    <a href="{{ $asiento->exists ? route('asientos.show', $asiento) : route('asientos.index') }}" class="btn btn-link">Cancelar</a>
    <span class="small text-muted ms-auto d-none d-md-inline">
        <kbd>Enter</kbd> en un importe pasa a la línea siguiente · se puede escribir 1234,56 o 1.234,56
    </span>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
(function () {
    const CUENTAS = @json($opcionesCuentas);
    const tbody = document.querySelector('#tabla-lineas tbody');
    const tpl = document.getElementById('tpl-linea');
    let indice = tbody.querySelectorAll('tr.linea').length;

    // --- Importes en formato argentino (se manejan en centavos para no perder precisión) ---
    function aCentavos(v) {
        v = (v || '').toString().trim();
        if (!v) return 0;
        if (v.includes(',')) v = v.replace(/\./g, '').replace(',', '.');
        const n = parseFloat(v);
        return isNaN(n) ? 0 : Math.round(n * 100);
    }
    function formatear(c) {
        return (c / 100).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function initSelect(sel) {
        const ts = new TomSelect(sel, {
            options: CUENTAS,
            valueField: 'id',
            labelField: 'text',
            searchField: ['text'],
            maxOptions: 60,
            placeholder: 'Buscar cuenta por código o nombre…',
            items: sel.dataset.valor ? [sel.dataset.valor] : [],
            onItemAdd: function () {
                // Al elegir la cuenta pasa al importe
                const tr = sel.closest('tr');
                const destino = aCentavos(tr.querySelector('.haber').value) > 0 ? tr.querySelector('.haber') : tr.querySelector('.debe');
                setTimeout(() => destino.focus(), 0);
            },
        });
        return ts;
    }

    function renumerarFilas() {
        tbody.querySelectorAll('tr.linea').forEach((tr, i) => tr.querySelector('.nro').textContent = i + 1);
    }

    function agregarLinea(enfocar) {
        const html = tpl.innerHTML.replace(/__i__/g, indice++);
        tbody.insertAdjacentHTML('beforeend', html);
        const tr = tbody.lastElementChild;
        const ts = initSelect(tr.querySelector('select.cuenta'));
        renumerarFilas();
        if (enfocar) ts.focus();
        return tr;
    }

    function recalcular() {
        let d = 0, h = 0;
        tbody.querySelectorAll('tr.linea').forEach(tr => {
            d += aCentavos(tr.querySelector('.debe').value);
            h += aCentavos(tr.querySelector('.haber').value);
        });
        document.getElementById('total-debe').textContent = formatear(d);
        document.getElementById('total-haber').textContent = formatear(h);
        const dif = document.getElementById('diferencia');
        if (d === 0 && h === 0) {
            dif.textContent = ''; dif.className = '';
        } else if (d === h) {
            dif.innerHTML = '<i class="bi bi-check-circle-fill"></i> Balanceado'; dif.className = 'dif-ok';
        } else {
            dif.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i> Diferencia: ' + formatear(Math.abs(d - h)) + (d > h ? ' (falta Haber)' : ' (falta Debe)');
            dif.className = 'dif-mal';
        }
        return { d, h };
    }

    // Inicialización
    tbody.querySelectorAll('select.cuenta').forEach(initSelect);
    recalcular();

    tbody.addEventListener('input', function (e) {
        if (!e.target.classList.contains('importe')) return;
        // Si se carga el Debe se limpia el Haber de la misma línea y viceversa
        const tr = e.target.closest('tr');
        if (aCentavos(e.target.value) > 0) {
            const otro = e.target.classList.contains('debe') ? tr.querySelector('.haber') : tr.querySelector('.debe');
            otro.value = '';
        }
        recalcular();
    });

    tbody.addEventListener('focusout', function (e) {
        if (!e.target.classList.contains('importe')) return;
        const c = aCentavos(e.target.value);
        e.target.value = c > 0 ? formatear(c) : '';
    });

    tbody.addEventListener('click', function (e) {
        const btn = e.target.closest('.quitar');
        if (!btn) return;
        if (tbody.querySelectorAll('tr.linea').length <= 2) {
            // Mantiene al menos dos líneas: solo la limpia
            const tr = btn.closest('tr');
            tr.querySelector('select.cuenta').tomselect.clear();
            tr.querySelectorAll('input').forEach(i => i.value = '');
        } else {
            btn.closest('tr').remove();
            renumerarFilas();
        }
        recalcular();
    });

    // Enter: en un importe pasa a la línea siguiente (agregándola si hace falta); nunca envía el formulario
    tbody.closest('form').addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'BUTTON') return;
        if (e.target.closest('.ts-wrapper')) return; // lo maneja TomSelect
        e.preventDefault();
        if (!e.target.classList.contains('importe') && !e.target.classList.contains('detalle')) return;
        const tr = e.target.closest('tr');
        let sig = tr.nextElementSibling;
        if (!sig) sig = agregarLinea(false);
        sig.querySelector('select.cuenta').tomselect.focus();
    });

    document.getElementById('agregar').addEventListener('click', () => agregarLinea(true));

    document.getElementById('balancear').addEventListener('click', function () {
        const { d, h } = recalcular();
        if (d === h) return;
        // Busca la última línea sin importe; si no hay, agrega una
        let destino = Array.from(tbody.querySelectorAll('tr.linea')).reverse()
            .find(tr => !aCentavos(tr.querySelector('.debe').value) && !aCentavos(tr.querySelector('.haber').value));
        if (!destino) destino = agregarLinea(false);
        const campo = d > h ? destino.querySelector('.haber') : destino.querySelector('.debe');
        campo.value = formatear(Math.abs(d - h));
        recalcular();
        destino.querySelector('select.cuenta').tomselect.focus();
    });
})();
</script>
@endpush
