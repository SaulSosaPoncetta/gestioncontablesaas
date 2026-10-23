@extends('layouts.app')

@section('title', $empresa->razon_social)
@section('header', $empresa->razon_social)

@section('actions')
    <form method="POST" action="{{ route('admin.suscriptores.ingresar', $empresa) }}" class="d-inline">
        @csrf
        <button class="btn btn-sm btn-outline-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Ingresar como soporte</button>
    </form>
    <a href="{{ route('admin.suscriptores.index') }}" class="btn btn-sm btn-outline-secondary">Volver</a>
@endsection

@section('content')
<div class="row g-3">
    {{-- Datos de la empresa --}}
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Empresa</div>
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5 text-muted fw-normal">CUIT</dt><dd class="col-7">{{ $empresa->cuit_formateado }}</dd>
                    <dt class="col-5 text-muted fw-normal">Cond. IVA</dt><dd class="col-7">{{ \App\Models\Empresa::CONDICIONES_IVA[$empresa->condicion_iva] ?? '' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Email</dt><dd class="col-7">{{ $empresa->email ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Teléfono</dt><dd class="col-7">{{ $empresa->telefono ?? '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Localidad</dt><dd class="col-7">{{ trim($empresa->localidad . ', ' . $empresa->provincia, ', ') ?: '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Alta</dt><dd class="col-7">{{ $empresa->created_at->format('d/m/Y H:i') }}</dd>
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold">Usuarios ({{ $empresa->usuarios->count() }})</div>
            <ul class="list-group list-group-flush small">
                @foreach($empresa->usuarios as $u)
                    <li class="list-group-item">
                        <div class="fw-semibold">{{ $u->name }} @unless($u->activo)<span class="badge text-bg-secondary">inactivo</span>@endunless</div>
                        <div class="text-muted">{{ $u->email }} · {{ \App\Models\User::ROLES[$u->pivot->rol] ?? $u->pivot->rol }}</div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    {{-- Suscripción --}}
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Suscripción</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.suscriptores.update', $empresa) }}" class="row g-3">
                    @csrf
                    @method('PUT')
                    <div class="col-md-4">
                        <label class="form-label">Plan</label>
                        <select name="plan_id" class="form-select">
                            @foreach($planes as $p)
                                <option value="{{ $p->id }}" @selected($empresa->plan_id === $p->id)>{{ $p->nombre }} ({{ $p->precio_formateado }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Estado</label>
                        <select name="estado_suscripcion" class="form-select">
                            @foreach(\App\Models\Empresa::ESTADOS_SUSCRIPCION as $v => $t)
                                <option value="{{ $v }}" @selected($empresa->estado_suscripcion === $v)>{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="activa" value="1" id="activa" @checked($empresa->activa)>
                            <label class="form-check-label" for="activa">Empresa habilitada</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Prueba hasta</label>
                        <input type="date" name="prueba_hasta" value="{{ $empresa->prueba_hasta?->format('Y-m-d') }}" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Abonado hasta</label>
                        <input type="date" name="vence_el" value="{{ $empresa->vence_el?->format('Y-m-d') }}" class="form-control">
                        <div class="form-text">Vacío = sin vencimiento (cortesía).</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas internas</label>
                        <textarea name="notas_admin" rows="2" class="form-control">{{ $empresa->notas_admin }}</textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Guardar suscripción</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Registrar pago</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.pagos.store', $empresa) }}" class="row g-3">
                    @csrf
                    <div class="col-md-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" value="{{ today()->format('Y-m-d') }}" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Meses</label>
                        <input type="number" name="meses" value="1" min="1" max="24" class="form-control" required id="meses">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Importe</label>
                        <input type="number" name="importe" step="0.01" min="0" value="{{ $empresa->plan?->precio_mensual }}" class="form-control" required id="importe"
                               data-precio="{{ $empresa->plan?->precio_mensual ?? 0 }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Medio</label>
                        <select name="medio" class="form-select">
                            @foreach(\App\Models\Pago::MEDIOS as $v => $t)
                                <option value="{{ $v }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Referencia / N° operación</label>
                        <input type="text" name="referencia" class="form-control" maxlength="100">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Observaciones</label>
                        <input type="text" name="observaciones" class="form-control" maxlength="255">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-success btn-sm"><i class="bi bi-cash-coin me-1"></i>Registrar pago y extender vencimiento</button>
                        <span class="small text-muted ms-2">Se extiende desde el vencimiento vigente (o desde hoy si ya venció) y la suscripción pasa a Activa.</span>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold">Pagos</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Fecha</th><th>Período</th><th>Plan</th><th>Medio</th><th>Referencia</th><th class="text-end">Importe</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse($pagos as $pago)
                        <tr>
                            <td>{{ $pago->fecha->format('d/m/Y') }}</td>
                            <td class="small">{{ $pago->periodo_desde->format('d/m/Y') }} – {{ $pago->periodo_hasta->format('d/m/Y') }}</td>
                            <td>{{ $pago->plan?->nombre ?? '—' }}</td>
                            <td>{{ \App\Models\Pago::MEDIOS[$pago->medio] ?? $pago->medio }}</td>
                            <td class="small">{{ $pago->referencia }}</td>
                            <td class="num">$ {{ number_format($pago->importe, 2, ',', '.') }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.pagos.destroy', $pago) }}" data-confirm="¿Eliminar este pago? El vencimiento no se modifica automáticamente.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-3">Sin pagos registrados.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Recalcula el importe sugerido según la cantidad de meses
    document.getElementById('meses').addEventListener('input', function () {
        const imp = document.getElementById('importe');
        const precio = parseFloat(imp.dataset.precio || 0);
        if (precio > 0) imp.value = (precio * (parseInt(this.value) || 1)).toFixed(2);
    });
</script>
@endpush
