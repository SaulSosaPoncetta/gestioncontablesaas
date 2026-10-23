@php $fmt = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp

<div class="card mb-3">
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label">Impuesto *</label>
            <select name="impuesto" class="form-select">
                @foreach(\App\Models\RegimenRetencion::IMPUESTOS as $v => $t)
                    <option value="{{ $v }}" @selected(old('impuesto', $regimen->impuesto) === $v)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Código *</label>
            <input type="text" name="codigo" value="{{ old('codigo', $regimen->codigo) }}" maxlength="10" class="form-control @error('codigo') is-invalid @enderror" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Descripción *</label>
            <input type="text" name="descripcion" value="{{ old('descripcion', $regimen->descripcion) }}" maxlength="255" class="form-control @error('descripcion') is-invalid @enderror" required>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Alícuota inscripto %</label>
            <input type="text" inputmode="decimal" name="alicuota_inscripto" value="{{ $fmt(old('alicuota_inscripto', $regimen->alicuota_inscripto)) }}" class="form-control text-end">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Alícuota no inscripto %</label>
            <input type="text" inputmode="decimal" name="alicuota_no_inscripto" value="{{ $fmt(old('alicuota_no_inscripto', $regimen->alicuota_no_inscripto)) }}" class="form-control text-end">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Mínimo no sujeto</label>
            <input type="text" inputmode="decimal" name="minimo_no_sujeto" value="{{ $fmt(old('minimo_no_sujeto', $regimen->minimo_no_sujeto)) }}" class="form-control text-end">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label">Mínimo de retención</label>
            <input type="text" inputmode="decimal" name="minimo_retencion" value="{{ $fmt(old('minimo_retencion', $regimen->minimo_retencion)) }}" class="form-control text-end">
            <div class="form-text">Por debajo de este importe no se retiene.</div>
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="acumula_mensual" value="1" id="acum" @checked(old('acumula_mensual', $regimen->acumula_mensual))>
                <label class="form-check-label" for="acum">Acumula los pagos del mes por proveedor (Ganancias RG 830)</label>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" @checked(old('activo', $regimen->activo))>
                <label class="form-check-label" for="activo">Activo</label>
            </div>
        </div>
    </div>
</div>
