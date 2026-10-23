@php
    $provincias = ['Buenos Aires','CABA','Catamarca','Chaco','Chubut','Córdoba','Corrientes','Entre Ríos','Formosa','Jujuy','La Pampa','La Rioja','Mendoza','Misiones','Neuquén','Río Negro','Salta','San Juan','San Luis','Santa Cruz','Santa Fe','Santiago del Estero','Tierra del Fuego','Tucumán'];
@endphp

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
@endpush

<div class="card mb-3">
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label">Tipo *</label>
            <select name="tipo" class="form-select">
                @foreach(\App\Models\Tercero::TIPOS as $v => $t)
                    <option value="{{ $v }}" @selected(old('tipo', $tercero->tipo) === $v)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-8">
            <label class="form-label">Razón social / Nombre *</label>
            <input type="text" name="razon_social" value="{{ old('razon_social', $tercero->razon_social) }}" class="form-control @error('razon_social') is-invalid @enderror" required maxlength="255" autofocus>
        </div>
        <div class="col-md-3">
            <label class="form-label">Tipo de documento *</label>
            <select name="tipo_documento" id="tipo_documento" class="form-select">
                @foreach(\App\Support\Afip::TIPOS_DOCUMENTO as $v => $t)
                    <option value="{{ $v }}" @selected((int) old('tipo_documento', $tercero->tipo_documento) === $v)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Número</label>
            <input type="text" name="nro_documento" id="nro_documento" value="{{ old('nro_documento', $tercero->nro_documento) }}" class="form-control @error('nro_documento') is-invalid @enderror" maxlength="13">
            @error('nro_documento')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label class="form-label">Condición frente al IVA *</label>
            <select name="condicion_iva" class="form-select">
                @foreach(\App\Support\Afip::CONDICIONES_IVA as $v => $t)
                    <option value="{{ $v }}" @selected((int) old('condicion_iva', $tercero->condicion_iva) === $v)>{{ $t }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Domicilio</label>
            <input type="text" name="domicilio" value="{{ old('domicilio', $tercero->domicilio) }}" class="form-control" maxlength="255">
        </div>
        <div class="col-md-3">
            <label class="form-label">Localidad</label>
            <input type="text" name="localidad" value="{{ old('localidad', $tercero->localidad) }}" class="form-control" maxlength="100">
        </div>
        <div class="col-md-3">
            <label class="form-label">Provincia</label>
            <select name="provincia" class="form-select">
                <option value="">—</option>
                @foreach($provincias as $p)
                    <option value="{{ $p }}" @selected(old('provincia', $tercero->provincia) === $p)>{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email', $tercero->email) }}" class="form-control" maxlength="255">
        </div>
        <div class="col-md-6">
            <label class="form-label">Teléfono</label>
            <input type="text" name="telefono" value="{{ old('telefono', $tercero->telefono) }}" class="form-control" maxlength="50">
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-white fw-semibold">Imputación contable <span class="small text-muted fw-normal">(opcional: si se deja vacío se usan las cuentas automáticas)</span></div>
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label">Cuenta corriente</label>
            <select name="cuenta_id" class="form-select cuenta-select">
                <option value="">Por defecto (Deudores por ventas / Proveedores)</option>
                @foreach($cuentas as $c)
                    <option value="{{ $c->id }}" @selected(old('cuenta_id', $tercero->cuenta_id) == $c->id)>{{ $c->codigo }} - {{ $c->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Imputación habitual de sus comprobantes</label>
            <select name="cuenta_imputacion_id" class="form-select cuenta-select">
                <option value="">Por defecto (Ventas / Compras y gastos)</option>
                @foreach($cuentas as $c)
                    <option value="{{ $c->id }}" @selected(old('cuenta_imputacion_id', $tercero->cuenta_imputacion_id) == $c->id)>{{ $c->codigo }} - {{ $c->nombre }}</option>
                @endforeach
            </select>
            <div class="form-text">Ej: para la distribuidora de luz, «Servicios (Luz, Gas, Agua)». Se propone al cargar sus facturas.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Concepto habitual de sus compras (IVA Simple)</label>
            <select name="operacion_iva" class="form-select">
                <option value="">Compra de bienes (por defecto)</option>
                @foreach(\App\Support\Afip::CONCEPTOS_COMPRA as $v => $t)
                    <option value="{{ $v }}" @selected((int) old('operacion_iva', $tercero->operacion_iva) === $v)>{{ $t }}</option>
                @endforeach
            </select>
            <div class="form-text">Solo para proveedores: bienes, locaciones, servicios o bienes de uso.</div>
        </div>
        <div class="col-12">
            <label class="form-label">Observaciones</label>
            <textarea name="observaciones" rows="2" class="form-control">{{ old('observaciones', $tercero->observaciones) }}</textarea>
        </div>
        @if($tercero->exists)
            <div class="col-12">
                <input type="hidden" name="activo" value="0">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" @checked(old('activo', $tercero->activo))>
                    <label class="form-check-label" for="activo">Activo</label>
                </div>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
    document.querySelectorAll('.cuenta-select').forEach(el => new TomSelect(el, { maxOptions: 80 }));

    // Formato CUIT XX-XXXXXXXX-X cuando corresponde
    const tipoDoc = document.getElementById('tipo_documento');
    document.getElementById('nro_documento').addEventListener('input', function () {
        if (!['80', '86'].includes(tipoDoc.value)) return;
        let v = this.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 10) v = v.slice(0, 2) + '-' + v.slice(2, 10) + '-' + v.slice(10);
        else if (v.length > 2) v = v.slice(0, 2) + '-' + v.slice(2);
        this.value = v;
    });
</script>
@endpush
