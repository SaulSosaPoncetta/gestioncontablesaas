@extends('layouts.app')

@section('title', 'Importar Mis Comprobantes')
@section('header', 'Importar «Mis Comprobantes» de ARCA')

@section('content')
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('importacion.analizar') }}" enctype="multipart/form-data">
                    @csrf
                    <label class="form-label fw-semibold">¿Qué va a importar?</label>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="circuito" value="venta" id="c-venta" @checked(old('circuito', $circuito) === 'venta')>
                            <label class="form-check-label" for="c-venta"><strong>Comprobantes emitidos</strong> → ventas</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="circuito" value="compra" id="c-compra" @checked(old('circuito', $circuito) === 'compra')>
                            <label class="form-check-label" for="c-compra"><strong>Comprobantes recibidos</strong> → compras</label>
                        </div>
                    </div>

                    <label class="form-label fw-semibold">Archivo descargado de ARCA</label>
                    <input type="file" name="archivo" accept=".xlsx,.xls,.csv,.txt" class="form-control @error('archivo') is-invalid @enderror" required>
                    @error('archivo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text mb-3">Excel (.xlsx) o CSV, sin modificar. Máximo 10 MB.</div>

                    <button class="btn btn-primary"><i class="bi bi-search me-1"></i>Analizar archivo</button>
                    <span class="small text-muted ms-2">Todavía no se graba nada: primero verá una vista previa.</span>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-body small">
                <div class="fw-semibold mb-2">Cómo obtener el archivo</div>
                <ol class="mb-3">
                    <li>Ingrese a ARCA con clave fiscal › <strong>Mis Comprobantes</strong>.</li>
                    <li>Elija <strong>Emitidos</strong> o <strong>Recibidos</strong> y el rango de fechas (conviene de a un mes).</li>
                    <li>Presione <strong>Buscar</strong> y luego <strong>Exportar</strong> (Excel o CSV).</li>
                    <li>Suba acá el archivo tal cual se descargó.</li>
                </ol>
                <div class="fw-semibold mb-1">Qué hace el sistema</div>
                <ul class="mb-0">
                    <li>Reconoce las columnas por el nombre del encabezado (formato clásico o el nuevo con IVA por alícuota).</li>
                    <li>Si el archivo trae solo el IVA total, deduce la alícuota (o la combinación 21% + 10,5%).</li>
                    <li>Detecta los comprobantes ya cargados y no los duplica.</li>
                    <li>Da de alta los clientes y proveedores que no existan (revise después su condición de IVA).</li>
                    <li>Cada comprobante importado genera su asiento contable.</li>
                    <li>Las monedas extranjeras se convierten a pesos con el tipo de cambio del archivo.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
