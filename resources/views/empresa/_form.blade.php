@php($meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'])
@php($provincias = ['Buenos Aires','CABA','Catamarca','Chaco','Chubut','Córdoba','Corrientes','Entre Ríos','Formosa','Jujuy','La Pampa','La Rioja','Mendoza','Misiones','Neuquén','Río Negro','Salta','San Juan','San Luis','Santa Cruz','Santa Fe','Santiago del Estero','Tierra del Fuego','Tucumán'])

<div class="card mb-3">
    <div class="card-header bg-white fw-semibold">Datos generales</div>
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label">Razón social *</label>
            <input type="text" name="razon_social" value="{{ old('razon_social', $empresa->razon_social) }}" class="form-control @error('razon_social') is-invalid @enderror" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Nombre de fantasía</label>
            <input type="text" name="nombre_fantasia" value="{{ old('nombre_fantasia', $empresa->nombre_fantasia) }}" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">CUIT *</label>
            @if(auth()->user()->es_superadmin)
                <input type="text" name="cuit" value="{{ old('cuit', $empresa->cuit_formateado) }}" class="form-control @error('cuit') is-invalid @enderror" placeholder="30-12345678-9" maxlength="13" required>
            @else
                <input type="text" value="{{ $empresa->cuit_formateado }}" class="form-control" disabled>
                <div class="form-text">Para cambiar el CUIT contacte a soporte.</div>
            @endif
            @error('cuit')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Inicio de actividades</label>
            <input type="date" name="inicio_actividades" value="{{ old('inicio_actividades', $empresa->inicio_actividades?->format('Y-m-d')) }}" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Mes de cierre de ejercicio *</label>
            <select name="mes_cierre" class="form-select" required>
                @foreach($meses as $i => $mes)
                    <option value="{{ $i + 1 }}" @selected(old('mes_cierre', $empresa->mes_cierre) == $i + 1)>{{ $mes }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-white fw-semibold">Situación impositiva</div>
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label">Condición frente al IVA *</label>
            <select name="condicion_iva" class="form-select" required>
                @foreach(\App\Models\Empresa::CONDICIONES_IVA as $valor => $texto)
                    <option value="{{ $valor }}" @selected(old('condicion_iva', $empresa->condicion_iva) === $valor)>{{ $texto }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Condición IIBB *</label>
            <select name="condicion_iibb" class="form-select" required>
                @foreach(\App\Models\Empresa::CONDICIONES_IIBB as $valor => $texto)
                    <option value="{{ $valor }}" @selected(old('condicion_iibb', $empresa->condicion_iibb) === $valor)>{{ $texto }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">N° de inscripción IIBB</label>
            <input type="text" name="nro_iibb" value="{{ old('nro_iibb', $empresa->nro_iibb) }}" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Código de actividad ARCA</label>
            <input type="text" name="actividad_afip" value="{{ old('actividad_afip', $empresa->actividad_afip) }}" maxlength="6" placeholder="Ej: 620100"
                   class="form-control @error('actividad_afip') is-invalid @enderror">
            <div class="form-text">Actividad principal (6 dígitos, F.883). Se usa en la exportación de IVA Simple.</div>
            @error('actividad_afip')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label d-block">Agente de retención / percepción</label>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="agente_retencion_iva" value="1" id="ag_iva" @checked(old('agente_retencion_iva', $empresa->agente_retencion_iva))>
                <label class="form-check-label" for="ag_iva">IVA</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="agente_retencion_ganancias" value="1" id="ag_gan" @checked(old('agente_retencion_ganancias', $empresa->agente_retencion_ganancias))>
                <label class="form-check-label" for="ag_gan">Ganancias</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="agente_recaudacion_iibb" value="1" id="ag_iibb" @checked(old('agente_recaudacion_iibb', $empresa->agente_recaudacion_iibb))>
                <label class="form-check-label" for="ag_iibb">Ingresos Brutos</label>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-white fw-semibold">Domicilio y contacto</div>
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label">Domicilio fiscal</label>
            <input type="text" name="domicilio" value="{{ old('domicilio', $empresa->domicilio) }}" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">Localidad</label>
            <input type="text" name="localidad" value="{{ old('localidad', $empresa->localidad) }}" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">Provincia</label>
            <select name="provincia" class="form-select">
                <option value="">—</option>
                @foreach($provincias as $prov)
                    <option value="{{ $prov }}" @selected(old('provincia', $empresa->provincia) === $prov)>{{ $prov }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Cód. postal</label>
            <input type="text" name="codigo_postal" value="{{ old('codigo_postal', $empresa->codigo_postal) }}" class="form-control">
        </div>
        <div class="col-md-5">
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email', $empresa->email) }}" class="form-control">
        </div>
        <div class="col-md-5">
            <label class="form-label">Teléfono</label>
            <input type="text" name="telefono" value="{{ old('telefono', $empresa->telefono) }}" class="form-control">
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Formatea el CUIT como XX-XXXXXXXX-X mientras se escribe
    document.querySelector('[name="cuit"]')?.addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 10) v = v.slice(0, 2) + '-' + v.slice(2, 10) + '-' + v.slice(10);
        else if (v.length > 2) v = v.slice(0, 2) + '-' + v.slice(2);
        this.value = v;
    });
</script>
@endpush
