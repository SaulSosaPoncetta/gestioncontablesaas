@extends('layouts.app')

@section('title', 'Importar plan de cuentas')
@section('header', 'Importar plan de cuentas')

@section('actions')
    <a href="{{ route('cuentas.index') }}" class="btn btn-outline-secondary btn-sm">Volver</a>
@endsection

@section('content')
@if(session('erroresImportacion'))
    <div class="card border-danger mb-3">
        <div class="card-header bg-danger text-white">Errores encontrados ({{ count(session('erroresImportacion')) }})</div>
        <ul class="list-group list-group-flush small" style="max-height: 300px; overflow-y: auto;">
            @foreach(session('erroresImportacion') as $error)
                <li class="list-group-item">{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <form method="POST" action="{{ route('cuentas.importar') }}" enctype="multipart/form-data">
                    @csrf
                    <label class="form-label fw-semibold">Archivo CSV</label>
                    <input type="file" name="archivo" accept=".csv,.txt" class="form-control @error('archivo') is-invalid @enderror" required>
                    @error('archivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text mb-3">Máximo 2 MB. Separador punto y coma (;) o coma (,).</div>

                    <button class="btn btn-primary"><i class="bi bi-upload me-1"></i>Importar</button>
                    <a href="{{ route('cuentas.plantilla') }}" class="btn btn-link"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Descargar plantilla</a>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body small">
                <div class="fw-semibold mb-2">Formato del archivo</div>
                <table class="table table-sm table-bordered mb-3">
                    <thead class="table-light"><tr><th>Columna</th><th>Contenido</th></tr></thead>
                    <tbody>
                        <tr><td><code>codigo</code> *</td><td>Ej: 1.1.01.001</td></tr>
                        <tr><td><code>nombre</code> *</td><td>Nombre de la cuenta</td></tr>
                        <tr><td><code>imputable</code></td><td>S / N (por defecto N)</td></tr>
                        <tr><td><code>saldo_habitual</code></td><td>D / A (por defecto, el del rubro)</td></tr>
                        <tr><td><code>monetaria</code></td><td>S / N (por defecto S)</td></tr>
                    </tbody>
                </table>
                <ul class="mb-0">
                    <li>La primera fila debe ser el encabezado.</li>
                    <li>El tipo (activo, pasivo, etc.) se toma del primer dígito del código.</li>
                    <li>La cuenta padre se deduce del código: para importar <code>1.1.01.005</code> tiene que existir <code>1.1.01</code> (en el sistema o en el mismo archivo).</li>
                    <li>Si el código ya existe, la cuenta se <strong>actualiza</strong>; si no, se crea.</li>
                    <li>Si hay algún error no se importa nada: se corrige el archivo y se vuelve a subir.</li>
                    <li>Sirve para editar el plan en Excel: exportar, modificar y volver a importar.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
