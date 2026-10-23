@php($esUnoMismo = $usuario->exists && $usuario->id === auth()->id())

<div class="card mb-3">
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label">Nombre y apellido *</label>
            <input type="text" name="name" value="{{ old('name', $usuario->name) }}" class="form-control @error('name') is-invalid @enderror" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Email *</label>
            <input type="email" name="email" value="{{ old('email', $usuario->email) }}" class="form-control @error('email') is-invalid @enderror" required autocomplete="off">
        </div>
        <div class="col-md-6">
            <label class="form-label">Contraseña {{ $usuario->exists ? '' : '*' }}</label>
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" {{ $usuario->exists ? '' : 'required' }} autocomplete="new-password">
            @if($usuario->exists)<div class="form-text">Dejar en blanco para no cambiarla.</div>@endif
        </div>
        <div class="col-md-6">
            <label class="form-label">Repetir contraseña</label>
            <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
        </div>
        <div class="col-md-6">
            <label class="form-label">Rol *</label>
            <select name="rol" class="form-select" {{ $esUnoMismo ? 'disabled' : '' }}>
                @foreach(\App\Models\User::ROLES as $valor => $texto)
                    <option value="{{ $valor }}" @selected(old('rol', $rol) === $valor)>{{ $texto }}</option>
                @endforeach
            </select>
            @if($esUnoMismo)<div class="form-text">No puede cambiar su propio rol.</div>@endif
        </div>
        @if($usuario->exists && ! $esUnoMismo)
            <div class="col-md-6 d-flex align-items-end">
                <input type="hidden" name="activo" value="0">
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" name="activo" value="1" id="activo" @checked(old('activo', $usuario->activo))>
                    <label class="form-check-label" for="activo">Usuario activo</label>
                </div>
            </div>
        @endif
    </div>
</div>
