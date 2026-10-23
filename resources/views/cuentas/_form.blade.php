@php
    $bloquearCodigo = $cuenta->exists && (($tieneHijas ?? false) || ($tieneMovimientos ?? false));
@endphp

<div class="card mb-3">
    <div class="card-body row g-3">
        <div class="col-md-8">
            <label class="form-label">Cuenta padre</label>
            <select name="padre_id" id="padre_id" class="form-select @error('padre_id') is-invalid @enderror" {{ $bloquearCodigo ? 'disabled' : '' }}>
                <option value="" data-codigo="">— Ninguna (rubro principal) —</option>
                @foreach($padres as $p)
                    <option value="{{ $p->id }}" data-codigo="{{ $p->codigo }}" data-saldo="{{ $p->saldo_habitual }}"
                            @selected(old('padre_id', $cuenta->padre_id) == $p->id)>
                        {{ str_repeat('· ', $p->nivel - 1) }}{{ $p->codigo }} - {{ $p->nombre }}
                    </option>
                @endforeach
            </select>
            @if($bloquearCodigo)
                <input type="hidden" name="padre_id" value="{{ $cuenta->padre_id }}">
            @endif
            @error('padre_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-4">
            <label class="form-label">Código *</label>
            <input type="text" name="codigo" id="codigo" value="{{ old('codigo', $cuenta->codigo) }}"
                   class="form-control font-monospace @error('codigo') is-invalid @enderror" {{ $bloquearCodigo ? 'readonly' : '' }} required maxlength="30">
            @error('codigo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @if($bloquearCodigo)
                <div class="form-text">No se puede cambiar: la cuenta tiene {{ ($tieneHijas ?? false) ? 'subcuentas' : 'movimientos' }}.</div>
            @else
                <div class="form-text">Ej: 1.1.01.003. Debe estar un nivel debajo del padre.</div>
            @endif
        </div>

        <div class="col-md-8">
            <label class="form-label">Nombre *</label>
            <input type="text" name="nombre" value="{{ old('nombre', $cuenta->nombre) }}" class="form-control @error('nombre') is-invalid @enderror" required maxlength="255" autofocus>
        </div>

        <div class="col-md-4">
            <label class="form-label">Tipo</label>
            <input type="text" id="tipo" class="form-control" disabled
                   value="{{ \App\Models\Cuenta::TIPOS[\App\Models\Cuenta::tipoPorCodigo(old('codigo', $cuenta->codigo) ?: '9')[0]] }}">
            <div class="form-text">Se define por el primer dígito del código.</div>
        </div>

        <div class="col-md-4">
            <label class="form-label">Saldo habitual *</label>
            <select name="saldo_habitual" id="saldo_habitual" class="form-select">
                <option value="deudor" @selected(old('saldo_habitual', $cuenta->saldo_habitual) === 'deudor')>Deudor</option>
                <option value="acreedor" @selected(old('saldo_habitual', $cuenta->saldo_habitual) === 'acreedor')>Acreedor</option>
            </select>
            <div class="form-text">Las regularizadoras (amortizaciones, previsiones) van al revés de su rubro.</div>
        </div>

        <div class="col-md-8">
            <label class="form-label d-block">Características</label>
            <div class="form-check form-switch">
                <input type="hidden" name="imputable" value="0">
                <input class="form-check-input @error('imputable') is-invalid @enderror" type="checkbox" name="imputable" value="1" id="imputable"
                       @checked(old('imputable', $cuenta->imputable)) {{ ($tieneHijas ?? false) ? 'disabled' : '' }}>
                <label class="form-check-label" for="imputable">
                    <strong>Imputable</strong> — recibe movimientos en los asientos (las agrupadoras solo suman a sus subcuentas)
                </label>
                @error('imputable')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-check form-switch">
                <input type="hidden" name="monetaria" value="0">
                <input class="form-check-input" type="checkbox" name="monetaria" value="1" id="monetaria" @checked(old('monetaria', $cuenta->monetaria))>
                <label class="form-check-label" for="monetaria">
                    <strong>Monetaria</strong> — no se ajusta por inflación (caja, créditos, deudas). Desmarcar para bienes de cambio, bienes de uso, PN y resultados.
                </label>
            </div>
            @if($cuenta->exists)
                <div class="form-check form-switch">
                    <input type="hidden" name="activa" value="0">
                    <input class="form-check-input" type="checkbox" name="activa" value="1" id="activa" @checked(old('activa', $cuenta->activa))>
                    <label class="form-check-label" for="activa"><strong>Activa</strong> — las inactivas no se pueden usar en nuevos asientos</label>
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const tipos = @json(\App\Models\Cuenta::TIPOS);
    const porDigito = {1: ['activo','deudor'], 2: ['pasivo','acreedor'], 3: ['patrimonio_neto','acreedor'], 4: ['resultado_positivo','acreedor'], 5: ['resultado_negativo','deudor']};
    const codigo = document.getElementById('codigo');
    const padre = document.getElementById('padre_id');
    const tipo = document.getElementById('tipo');
    const saldo = document.getElementById('saldo_habitual');
    const esNueva = {{ $cuenta->exists ? 'false' : 'true' }};

    function actualizarTipo() {
        const d = parseInt((codigo.value || '').charAt(0));
        const t = porDigito[d] || ['orden', 'deudor'];
        tipo.value = tipos[t[0]];
        return t;
    }

    codigo.addEventListener('input', function () {
        const t = actualizarTipo();
        if (esNueva && !padre.value) saldo.value = t[1];
    });

    // Al cambiar el padre en una cuenta nueva, propone el prefijo del código y el saldo del padre
    padre.addEventListener('change', function () {
        if (!esNueva) return;
        const op = padre.selectedOptions[0];
        const pref = op.dataset.codigo;
        if (pref) {
            codigo.value = pref + '.';
            if (op.dataset.saldo) saldo.value = op.dataset.saldo;
        } else {
            codigo.value = '';
        }
        actualizarTipo();
        codigo.focus();
    });
})();
</script>
@endpush
