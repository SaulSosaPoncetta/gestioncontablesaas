<div class="card mb-3">
    <div class="card-body row g-3">
        @if($ejercicio->exists)
            <div class="col-md-2">
                <label class="form-label">Número</label>
                <input type="text" class="form-control" value="{{ $ejercicio->numero }}" disabled>
            </div>
        @endif
        <div class="col-md-{{ $ejercicio->exists ? 10 : 12 }}">
            <label class="form-label">Descripción</label>
            <input type="text" name="descripcion" value="{{ old('descripcion', $ejercicio->descripcion) }}" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Fecha de inicio *</label>
            <input type="date" name="fecha_inicio" value="{{ old('fecha_inicio', $ejercicio->fecha_inicio?->format('Y-m-d')) }}" class="form-control @error('fecha_inicio') is-invalid @enderror" required>
            @error('fecha_inicio')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Fecha de cierre *</label>
            <input type="date" name="fecha_cierre" value="{{ old('fecha_cierre', $ejercicio->fecha_cierre?->format('Y-m-d')) }}" class="form-control @error('fecha_cierre') is-invalid @enderror" required>
            @error('fecha_cierre')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label">Bloqueado hasta</label>
            <input type="date" name="bloqueado_hasta" value="{{ old('bloqueado_hasta', $ejercicio->bloqueado_hasta?->format('Y-m-d')) }}" class="form-control @error('bloqueado_hasta') is-invalid @enderror">
            <div class="form-text">No se podrán cargar ni modificar asientos con fecha igual o anterior.</div>
            @error('bloqueado_hasta')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>
