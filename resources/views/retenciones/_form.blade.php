@php
    $practicada = $tipo === 'practicada';
    $fmt = fn ($v) => (float) $v != 0 ? number_format((float) $v, 2, ',', '.') : '';
@endphp

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
<style> .importe { text-align: right; font-variant-numeric: tabular-nums; } </style>
@endpush

<div class="card mb-3">
    <div class="card-body row g-3">
        @if($practicada)
            <div class="col-md-8">
                <label class="form-label">Régimen *</label>
                <select name="regimen_id" id="regimen_id" class="form-select @error('regimen_id') is-invalid @enderror" required>
                    <option value="">Seleccione…</option>
                    @foreach($regimenes as $rg)
                        <option value="{{ $rg->id }}" @selected(old('regimen_id', $retencion->regimen_id) == $rg->id)>{{ $rg->nombre }}</option>
                    @endforeach
                </select>
            </div>
        @else
            <div class="col-md-4">
                <label class="form-label">Impuesto *</label>
                <select name="impuesto" class="form-select">
                    @foreach(\App\Models\Retencion::IMPUESTOS as $v => $t)
                        <option value="{{ $v }}" @selected(old('impuesto', $retencion->impuesto) === $v)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">N° de certificado *</label>
                <input type="text" name="numero_certificado" value="{{ old('numero_certificado', $retencion->numero_certificado) }}" maxlength="30" class="form-control @error('numero_certificado') is-invalid @enderror" required>
                @error('numero_certificado')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endif

        <div class="col-md-4">
            <label class="form-label">Fecha *</label>
            <input type="date" name="fecha" id="fecha" value="{{ old('fecha', $retencion->fecha?->format('Y-m-d')) }}" class="form-control @error('fecha') is-invalid @enderror" required>
            @error('fecha')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-8">
            <label class="form-label">{{ $practicada ? 'Proveedor (sujeto retenido)' : 'Cliente (agente que retuvo)' }} *</label>
            <select name="tercero_id" id="tercero_id" class="form-select @error('tercero_id') is-invalid @enderror" required>
                <option value="">Buscar…</option>
                @foreach($terceros as $t)
                    <option value="{{ $t->id }}" @selected(old('tercero_id', $retencion->tercero_id) == $t->id)>{{ $t->razon_social }} · {{ $t->documento_formateado }}</option>
                @endforeach
            </select>
            @error('tercero_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Condición del sujeto</label>
            <select name="condicion" id="condicion" class="form-select">
                <option value="inscripto" @selected(old('condicion', $retencion->condicion) === 'inscripto')>Inscripto</option>
                <option value="no_inscripto" @selected(old('condicion', $retencion->condicion) === 'no_inscripto')>No inscripto</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label">Comprobante relacionado <span class="text-muted small">(opcional)</span></label>
            <select name="comprobante_id" id="comprobante_id" class="form-select @error('comprobante_id') is-invalid @enderror">
                <option value="">Ninguno</option>
                @foreach($comprobantes as $c)
                    <option value="{{ $c->id }}" data-tercero="{{ $c->tercero_id }}" data-total="{{ $c->total }}"
                            @selected(old('comprobante_id', $retencion->comprobante_id) == $c->id)>
                        {{ $c->fecha->format('d/m/Y') }} · {{ $c->descripcion_corta }} · {{ $c->tercero->razon_social }} · $ {{ number_format($c->total, 2, ',', '.') }}
                    </option>
                @endforeach
            </select>
            @error('comprobante_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body row g-3">
        <div class="col-md-3">
            <label class="form-label">Importe del comprobante / pago</label>
            <input type="text" inputmode="decimal" name="importe_comprobante" id="importe_comprobante" value="{{ $fmt(old('importe_comprobante', $retencion->importe_comprobante)) }}" class="form-control importe">
        </div>
        <div class="col-md-3">
            <label class="form-label">Base imponible {{ $practicada ? '*' : '' }}</label>
            <input type="text" inputmode="decimal" name="base_imponible" id="base_imponible" value="{{ $fmt(old('base_imponible', $retencion->base_imponible)) }}" class="form-control importe @error('base_imponible') is-invalid @enderror" {{ $practicada ? 'required' : '' }}>
            @if($practicada)<div class="form-text">Neto del pago (sin IVA).</div>@endif
        </div>
        <div class="col-md-2">
            <label class="form-label">Alícuota %</label>
            <input type="text" inputmode="decimal" name="alicuota" id="alicuota" value="{{ $fmt(old('alicuota', $retencion->alicuota)) }}" class="form-control importe" {{ $practicada ? 'readonly' : '' }}>
        </div>
        <div class="col-md-4">
            <label class="form-label">Importe retenido *</label>
            <input type="text" inputmode="decimal" name="importe" id="importe" value="{{ $fmt(old('importe', $retencion->importe)) }}" class="form-control importe fw-bold @error('importe') is-invalid @enderror" {{ $practicada ? 'readonly' : 'required' }}>
            @error('importe')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @if($practicada)
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" name="importe_manual" value="1" id="importe_manual" @checked(old('importe_manual'))>
                    <label class="form-check-label small" for="importe_manual">Cargar alícuota e importe a mano (escalas, exclusiones, certificados de no retención)</label>
                </div>
            @endif
        </div>
        @if($practicada)
            <div class="col-12">
                <div class="small text-muted" id="detalle-calculo"></div>
            </div>
        @endif
        <div class="col-12">
            <label class="form-label">Observaciones</label>
            <input type="text" name="observaciones" value="{{ old('observaciones', $retencion->observaciones) }}" maxlength="255" class="form-control">
        </div>
    </div>
</div>

<div class="d-flex gap-2">
    <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
    <a href="{{ $retencion->exists ? route('retenciones.show', [$tipo, $retencion]) : route('retenciones.index', $tipo) }}" class="btn btn-outline-secondary">Cancelar</a>
</div>
<p class="small text-muted mt-2"><i class="bi bi-info-circle"></i> Al guardar se registra el asiento contable{{ $practicada ? ' y, si hay importe, se numera el certificado' : '' }}.</p>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
(function () {
    const practicada = {{ $practicada ? 'true' : 'false' }};
    const excluir = {{ $retencion->exists ? $retencion->id : 'null' }};
    const urlCalcular = @json(route('retenciones.calcular'));

    function aCentavos(v) {
        v = (v || '').toString().trim();
        if (!v) return 0;
        if (v.includes(',')) v = v.replace(/\./g, '').replace(',', '.');
        const n = parseFloat(v);
        return isNaN(n) ? 0 : Math.round(n * 100);
    }
    const fmt = c => (c / 100).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const terceroTs = new TomSelect('#tercero_id', { maxOptions: 80, onChange: calcular });
    new TomSelect('#comprobante_id', {
        maxOptions: 100,
        onChange: function (id) {
            const op = document.querySelector('#comprobante_id option[value="' + id + '"]');
            if (!op || !op.dataset.tercero) return;
            if (!terceroTs.getValue()) terceroTs.setValue(op.dataset.tercero);
            const imp = document.getElementById('importe_comprobante');
            if (!aCentavos(imp.value)) imp.value = fmt(Math.round(parseFloat(op.dataset.total) * 100));
        },
    });

    document.querySelectorAll('.importe').forEach(el => el.addEventListener('focusout', function () {
        const c = aCentavos(this.value);
        this.value = c ? fmt(c) : '';
    }));

    if (!practicada) return;

    const manual = document.getElementById('importe_manual');
    const alic = document.getElementById('alicuota');
    const importe = document.getElementById('importe');
    let timer = null;

    function modoManual() {
        alic.readOnly = importe.readOnly = !manual.checked;
        if (!manual.checked) calcular();
    }
    manual.addEventListener('change', modoManual);

    function calcular() {
        if (manual.checked) return;
        clearTimeout(timer);
        timer = setTimeout(async function () {
            const params = new URLSearchParams({
                regimen: document.getElementById('regimen_id').value,
                tercero: terceroTs.getValue(),
                base: document.getElementById('base_imponible').value,
                fecha: document.getElementById('fecha').value,
                condicion: document.getElementById('condicion').value,
            });
            if (excluir) params.set('excluir', excluir);
            try {
                const r = await (await fetch(urlCalcular + '?' + params, { headers: { 'Accept': 'application/json' } })).json();
                importe.value = fmt(Math.round(r.importe * 100));
                alic.value = r.alicuota ? fmt(Math.round(r.alicuota * 100)) : '';
                document.getElementById('detalle-calculo').textContent = r.detalle || '';
            } catch (e) {}
        }, 300);
    }

    ['regimen_id', 'base_imponible', 'fecha', 'condicion'].forEach(id => {
        document.getElementById(id).addEventListener('change', calcular);
        document.getElementById(id).addEventListener('input', calcular);
    });
    modoManual();
})();
</script>
@endpush
