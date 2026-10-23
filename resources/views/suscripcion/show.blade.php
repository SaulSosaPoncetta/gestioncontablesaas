@extends('layouts.app')

@section('title', 'Mi suscripción')
@section('header', 'Mi suscripción')

@section('content')
@php
    $badge = match ($estado) {
        'ok'           => ['success', $empresa->estado_suscripcion === 'prueba' ? 'En prueba' : 'Activa'],
        'gracia'       => ['warning', 'Vencida (período de gracia)'],
        'solo_lectura' => ['danger', 'Vencida · solo lectura'],
        'bloqueada'    => ['dark', \App\Models\Empresa::ESTADOS_SUSCRIPCION[$empresa->estado_suscripcion] ?? 'Bloqueada'],
    };
    $contacto = config('saas.contacto');
@endphp

@if($estado === 'bloqueada')
    <div class="alert alert-danger">
        <i class="bi bi-slash-circle me-1"></i>
        El acceso de su empresa está <strong>{{ strtolower($badge[1]) }}</strong>. Comuníquese con nosotros para reactivarlo.
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="text-muted small">Plan actual</div>
                        <div class="fs-4 fw-semibold">{{ $empresa->plan?->nombre ?? 'Sin plan' }}</div>
                        @if($empresa->plan)
                            <div class="text-muted">{{ $empresa->plan->precio_formateado }} por mes</div>
                        @endif
                    </div>
                    <span class="badge text-bg-{{ $badge[0] }} fs-6">{{ $badge[1] }}</span>
                </div>

                <dl class="row mb-0 small">
                    <dt class="col-6 text-muted fw-normal">
                        {{ $empresa->estado_suscripcion === 'prueba' ? 'Prueba gratis hasta' : 'Abonado hasta' }}
                    </dt>
                    <dd class="col-6 fw-semibold">{{ $empresa->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</dd>

                    @if($dias !== null)
                        <dt class="col-6 text-muted fw-normal">{{ $dias >= 0 ? 'Días restantes' : 'Días vencida' }}</dt>
                        <dd class="col-6 fw-semibold {{ $dias < 0 ? 'text-danger' : '' }}">{{ abs($dias) }}</dd>
                    @endif

                    <dt class="col-6 text-muted fw-normal">Usuarios</dt>
                    <dd class="col-6">{{ $empresa->usuarios()->count() }} de {{ $empresa->plan?->max_usuarios ?? 'ilimitados' }}</dd>
                </dl>

                @if($empresa->plan)
                    <hr>
                    <ul class="list-unstyled small mb-0">
                        <li><i class="bi bi-check2 text-success"></i> Contabilidad completa (plan de cuentas, asientos, libros y balances)</li>
                        @foreach(\App\Models\Plan::MODULOS as $clave => $nombre)
                            <li class="{{ $empresa->tieneModulo($clave) ? '' : 'text-muted' }}">
                                <i class="bi bi-{{ $empresa->tieneModulo($clave) ? 'check2 text-success' : 'lock' }}"></i> {{ $nombre }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Renovar o cambiar de plan</div>
            <div class="card-body">
                <p class="small text-muted">Realice el pago del abono y envíenos el comprobante. Lo acreditamos en el día y su vencimiento se extiende automáticamente.</p>

                @if($contacto['datos_transferencia'])
                    <div class="bg-light rounded p-3 small mb-3" style="white-space: pre-line;">{{ $contacto['datos_transferencia'] }}</div>
                @endif

                <div class="d-flex flex-wrap gap-2 mb-4">
                    @if($contacto['whatsapp'])
                        <a href="https://wa.me/{{ $contacto['whatsapp'] }}?text={{ urlencode('Hola, quiero renovar el abono de ' . $empresa->razon_social . ' (CUIT ' . $empresa->cuit_formateado . ')') }}"
                           target="_blank" class="btn btn-success btn-sm"><i class="bi bi-whatsapp me-1"></i>Enviar comprobante por WhatsApp</a>
                    @endif
                    <a href="mailto:{{ $contacto['email'] }}?subject={{ rawurlencode('Abono ' . $empresa->razon_social . ' - CUIT ' . $empresa->cuit_formateado) }}"
                       class="btn btn-outline-primary btn-sm"><i class="bi bi-envelope me-1"></i>{{ $contacto['email'] }}</a>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th>Plan</th><th class="text-center">Usuarios</th><th class="text-center">IVA</th><th class="text-center">Retenc.</th><th class="text-center">Integr.</th><th class="text-end">Precio/mes</th></tr>
                        </thead>
                        <tbody>
                        @foreach($planes as $p)
                            <tr class="{{ $empresa->plan_id === $p->id ? 'table-primary' : '' }}">
                                <td class="fw-semibold">{{ $p->nombre }}</td>
                                <td class="text-center">{{ $p->max_usuarios ?? '∞' }}</td>
                                @foreach(['iva', 'retenciones', 'integraciones'] as $m)
                                    <td class="text-center"><i class="bi bi-{{ $p->tieneModulo($m) ? 'check-lg text-success' : 'dash text-muted' }}"></i></td>
                                @endforeach
                                <td class="text-end">{{ $p->precio_formateado }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header bg-white fw-semibold">Historial de pagos</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th>Fecha</th><th>Período</th><th>Plan</th><th>Medio</th><th class="text-end">Importe</th></tr>
            </thead>
            <tbody>
            @forelse($pagos as $pago)
                <tr>
                    <td>{{ $pago->fecha->format('d/m/Y') }}</td>
                    <td>{{ $pago->periodo_desde->format('d/m/Y') }} al {{ $pago->periodo_hasta->format('d/m/Y') }}</td>
                    <td>{{ $pago->plan?->nombre ?? '—' }}</td>
                    <td>{{ \App\Models\Pago::MEDIOS[$pago->medio] ?? $pago->medio }}</td>
                    <td class="num">$ {{ number_format($pago->importe, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-3">Todavía no hay pagos registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
