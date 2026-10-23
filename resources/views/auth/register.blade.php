<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta · MiGestión Contable</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1e3a5f 0%, #2d5a8c 100%); min-height: 100vh; }
        .reg-card { max-width: 860px; width: 100%; border: 0; border-radius: .75rem; }
        .plan-opt { cursor: pointer; transition: border-color .15s, box-shadow .15s; }
        .btn-check:checked + .plan-opt { border-color: #0d6efd !important; box-shadow: 0 0 0 .2rem rgba(13,110,253,.2); }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
@php($meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'])

<div class="card reg-card shadow-lg my-4">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <i class="bi bi-journal-bookmark-fill text-primary" style="font-size: 2.2rem;"></i>
            <h1 class="h4 mt-2 mb-1">Cree la cuenta de su empresa</h1>
            <p class="text-muted small mb-0">{{ config('saas.dias_prueba') }} días de prueba gratis · sin tarjeta de crédito</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger small">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <h2 class="h6 text-uppercase text-muted mb-3">1. Elija un plan</h2>
            <div class="row g-3 mb-4">
                @foreach($planes as $plan)
                    <div class="col-md-4">
                        <input type="radio" class="btn-check" name="plan_id" id="plan{{ $plan->id }}" value="{{ $plan->id }}"
                               @checked(old('plan_id', $planElegido) == $plan->id)>
                        <label class="card plan-opt h-100 border" for="plan{{ $plan->id }}">
                            <div class="card-body">
                                <div class="fw-semibold">{{ $plan->nombre }}</div>
                                <div class="fs-5 fw-bold text-primary">{{ $plan->precio_formateado }}<small class="fs-6 text-muted fw-normal"> /mes</small></div>
                                <div class="small text-muted mb-2">{{ $plan->descripcion }}</div>
                                <ul class="list-unstyled small mb-0">
                                    <li><i class="bi bi-check2 text-success"></i> Contabilidad completa</li>
                                    <li><i class="bi bi-check2 text-success"></i> {{ $plan->max_usuarios ? "Hasta {$plan->max_usuarios} usuarios" : 'Usuarios ilimitados' }}</li>
                                    @foreach(\App\Models\Plan::MODULOS as $clave => $nombre)
                                        <li class="{{ $plan->tieneModulo($clave) ? '' : 'text-muted text-decoration-line-through' }}">
                                            <i class="bi bi-{{ $plan->tieneModulo($clave) ? 'check2 text-success' : 'x' }}"></i> {{ $nombre }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </label>
                    </div>
                @endforeach
            </div>

            <h2 class="h6 text-uppercase text-muted mb-3">2. Datos de la empresa</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-8">
                    <label class="form-label">Razón social *</label>
                    <input type="text" name="razon_social" value="{{ old('razon_social') }}" class="form-control @error('razon_social') is-invalid @enderror" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">CUIT *</label>
                    <input type="text" name="cuit" value="{{ old('cuit') }}" class="form-control @error('cuit') is-invalid @enderror" placeholder="30-12345678-9" maxlength="13" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Condición frente al IVA *</label>
                    <select name="condicion_iva" class="form-select" required>
                        @foreach(\App\Models\Empresa::CONDICIONES_IVA as $valor => $texto)
                            <option value="{{ $valor }}" @selected(old('condicion_iva', 'responsable_inscripto') === $valor)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mes de cierre del ejercicio *</label>
                    <select name="mes_cierre" class="form-select" required>
                        @foreach($meses as $i => $mes)
                            <option value="{{ $i + 1 }}" @selected(old('mes_cierre', 12) == $i + 1)>{{ $mes }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <h2 class="h6 text-uppercase text-muted mb-3">3. Su usuario (administrador)</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Nombre y apellido *</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autocomplete="username">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Contraseña *</label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Repetir contraseña *</label>
                    <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                </div>
            </div>

            <div class="form-check mb-4">
                <input class="form-check-input @error('terminos') is-invalid @enderror" type="checkbox" name="terminos" value="1" id="terminos" @checked(old('terminos'))>
                <label class="form-check-label small" for="terminos">Acepto los términos y condiciones del servicio</label>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">
                <i class="bi bi-rocket-takeoff me-1"></i> Crear cuenta y empezar la prueba
            </button>

            <p class="text-center small mt-3 mb-0">
                ¿Ya tiene cuenta? <a href="{{ route('login') }}">Ingresar</a>
            </p>
        </form>
    </div>
</div>

<script>
    document.querySelector('[name="cuit"]').addEventListener('input', function () {
        let v = this.value.replace(/\D/g, '').slice(0, 11);
        if (v.length > 10) v = v.slice(0, 2) + '-' + v.slice(2, 10) + '-' + v.slice(10);
        else if (v.length > 2) v = v.slice(0, 2) + '-' + v.slice(2);
        this.value = v;
    });
</script>
</body>
</html>
