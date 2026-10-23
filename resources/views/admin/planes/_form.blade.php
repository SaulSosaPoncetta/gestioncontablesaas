<div class="card mb-3">
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label">Nombre *</label>
            <input type="text" name="nombre" value="{{ old('nombre', $plan->nombre) }}" class="form-control @error('nombre') is-invalid @enderror" required maxlength="60">
        </div>
        <div class="col-md-3">
            <label class="form-label">Precio mensual *</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" step="0.01" min="0" name="precio_mensual" value="{{ old('precio_mensual', $plan->precio_mensual) }}" class="form-control @error('precio_mensual') is-invalid @enderror" required>
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Orden</label>
            <input type="number" min="0" max="255" name="orden" value="{{ old('orden', $plan->orden ?? 0) }}" class="form-control">
        </div>
        <div class="col-12">
            <label class="form-label">Descripción</label>
            <input type="text" name="descripcion" value="{{ old('descripcion', $plan->descripcion) }}" class="form-control" maxlength="255">
        </div>
        <div class="col-md-3">
            <label class="form-label">Máx. usuarios</label>
            <input type="number" min="1" name="max_usuarios" value="{{ old('max_usuarios', $plan->max_usuarios) }}" class="form-control">
            <div class="form-text">Vacío = ilimitados.</div>
        </div>
        <div class="col-md-9">
            <label class="form-label d-block">Módulos incluidos</label>
            <div class="form-check"><input class="form-check-input" type="checkbox" checked disabled><label class="form-check-label text-muted">Contabilidad completa (siempre incluido)</label></div>
            @foreach(\App\Models\Plan::MODULOS as $clave => $nombre)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="modulo_{{ $clave }}" value="1" id="mod_{{ $clave }}" @checked(old('modulo_' . $clave, $plan->tieneModulo($clave)))>
                    <label class="form-check-label" for="mod_{{ $clave }}">{{ $nombre }}</label>
                </div>
            @endforeach
        </div>
        <div class="col-12">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" @checked(old('activo', $plan->activo))>
                <label class="form-check-label" for="activo">Visible en el registro de nuevas empresas</label>
            </div>
        </div>
    </div>
</div>
