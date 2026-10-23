<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · MiGestión Contable</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #1e3a5f 0%, #2d5a8c 100%); min-height: 100vh; }
        .login-card { max-width: 400px; width: 100%; border: 0; border-radius: .75rem; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">

<div class="card login-card shadow-lg">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <i class="bi bi-journal-bookmark-fill text-primary" style="font-size: 2.5rem;"></i>
            <h1 class="h4 mt-2 mb-0">MiGestión Contable</h1>
            <p class="text-muted small">Ingrese con su usuario</p>
        </div>

        @if(session('status'))
            <div class="alert alert-success small">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <div class="input-group has-validation">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input id="email" type="email" name="email" value="{{ old('email') }}"
                           class="form-control @error('email') is-invalid @enderror" required autofocus autocomplete="username">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-group has-validation">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input id="password" type="password" name="password"
                           class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label small" for="remember">Recordarme</label>
                </div>
                @if(Route::has('password.request'))
                    <a class="small" href="{{ route('password.request') }}">¿Olvidó su contraseña?</a>
                @endif
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-box-arrow-in-right me-1"></i> Ingresar
            </button>
        </form>

        @if(config('saas.registro_abierto') && Route::has('register'))
            <hr class="my-4">
            <p class="text-center small mb-0">
                ¿Todavía no tiene cuenta?
                <a href="{{ route('register') }}" class="fw-semibold">Pruebe gratis {{ config('saas.dias_prueba') }} días</a>
            </p>
        @endif
    </div>
</div>

</body>
</html>
