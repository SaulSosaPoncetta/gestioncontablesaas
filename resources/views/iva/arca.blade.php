@extends('layouts.app')

@section('title', 'Exportar a ARCA')
@section('header', 'Libros IVA')

@section('content')
@include('iva._nav', ['exportable' => false])

@php
    $mt = fn ($v) => number_format((float) $v, 2, ',', '.');
    $periodoTxt = \Carbon\Carbon::createFromFormat('Y-m-d', $periodo . '-01')->format('m/Y');
    $datos = $regimen === 'lid' ? $lid : $simple;
    $esExento = $empresaActiva->condicion_iva === 'exento';
@endphp

<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <div class="btn-group">
        <a href="{{ route('iva.arca', ['periodo' => $periodo, 'regimen' => 'iva_simple']) }}"
           class="btn btn-sm {{ $regimen === 'iva_simple' ? 'btn-primary' : 'btn-outline-primary' }}">IVA Simple (F.2051)</a>
        <a href="{{ route('iva.arca', ['periodo' => $periodo, 'regimen' => 'lid']) }}"
           class="btn btn-sm {{ $regimen === 'lid' ? 'btn-primary' : 'btn-outline-primary' }}">Libro IVA Digital (TXT)</a>
    </div>
    <span class="small text-muted">Período {{ $periodoTxt }}</span>
    @unless($cerrado)
        <span class="badge text-bg-warning">El período IVA todavía está abierto</span>
    @endunless
</div>

@if($regimen === 'iva_simple')
    <div class="alert alert-light border small">
        <strong>IVA Simple</strong> — Responsables Inscriptos, desde el período 11/2025.
        Los comprobantes electrónicos ya vienen precargados en ARCA desde «Mis Comprobantes». Estos archivos completan la
        <strong>Apertura de otros conceptos</strong>: débito fiscal por actividad, tipo de operación, sujeto y alícuota, y crédito fiscal por concepto y alícuota.
        Se importan en el F.2051 desde la opción «Importaciones».
        @if($esExento)<br><span class="text-danger">La empresa está registrada como exenta: le corresponde el Libro IVA Digital.</span>@endif
    </div>
@else
    <div class="alert alert-light border small">
        <strong>Libro IVA Digital</strong> (RG 4597) — desde 12/2025 lo presentan solo los <strong>sujetos exentos en IVA</strong> (RG 5707/2025).
        Son 4 archivos TXT de ancho fijo que se importan en «Portal IVA › Libro IVA Digital › Importación de comprobantes».
        @unless($esExento)<br><span class="text-danger">La empresa no está registrada como exenta: normalmente le corresponde IVA Simple.</span>@endunless
    </div>
@endif

@if(! empty($datos['avisos']))
    <div class="alert alert-warning">
        <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Revise antes de importar</div>
        <ul class="mb-0 small">
            @foreach($datos['avisos'] as $a)<li>{{ $a }}</li>@endforeach
        </ul>
    </div>
@endif

@if($regimen === 'iva_simple')
    <div class="row g-3 mb-3">
        @foreach([
            ['Débito fiscal', $simple['resumen']['debito']],
            ['Restitución de débito', $simple['resumen']['rest_debito']],
            ['Crédito fiscal', $simple['resumen']['credito']],
            ['Restitución de crédito', $simple['resumen']['rest_credito']],
            ['Ventas exentas / no gravadas', $simple['resumen']['exento']],
        ] as [$label, $valor])
            <div class="col-6 col-lg">
                <div class="card h-100"><div class="card-body py-2">
                    <div class="small text-muted">{{ $label }}</div>
                    <div class="fw-semibold">$ {{ $mt($valor) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>
@endif

<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Archivos</span>
        <a href="{{ route('iva.arca.descargar', ['archivo' => 'zip_' . $regimen, 'periodo' => $periodo]) }}" class="btn btn-sm btn-success">
            <i class="bi bi-file-earmark-zip me-1"></i>Descargar todo (ZIP)
        </a>
    </div>
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th>Archivo</th><th class="text-center">Registros</th><th class="text-end"></th></tr></thead>
        <tbody>
        @foreach($archivos as $clave => [$reg, $campo, $nombre, $ext])
            @continue($reg !== $regimen)
            <tr>
                <td><i class="bi bi-file-earmark-text me-1 text-muted"></i>{{ $nombre }}.{{ $ext }}</td>
                <td class="text-center">{{ count($datos[$campo]) }}</td>
                <td class="text-end">
                    <a href="{{ route('iva.arca.descargar', ['archivo' => $clave, 'periodo' => $periodo]) }}" class="btn btn-sm btn-outline-secondary {{ count($datos[$campo]) ? '' : 'disabled' }}">
                        <i class="bi bi-download"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-secondary {{ count($datos[$campo]) ? '' : 'disabled' }}" data-bs-toggle="collapse" data-bs-target="#prev-{{ $clave }}">
                        <i class="bi bi-eye"></i>
                    </button>
                </td>
            </tr>
            <tr class="collapse" id="prev-{{ $clave }}">
                <td colspan="3" class="bg-light">
                    <pre class="small mb-0" style="max-height: 220px; overflow: auto;">{{ implode("\n", array_slice($datos[$campo], 0, 50)) }}@if(count($datos[$campo]) > 50)

… ({{ count($datos[$campo]) - 50 }} registros más)@endif</pre>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="small text-muted">
    @if($regimen === 'iva_simple')
        <p class="mb-1">Formato: CSV separado por «;», decimales con coma, sin encabezado. El crédito fiscal computable se informa igual al facturado (no hay prorrateo por operaciones exentas).</p>
        <p class="mb-1">El tipo de operación de las ventas y el concepto de las compras se eligen al cargar cada comprobante. A cada proveedor se le puede definir su concepto habitual.</p>
        <p class="mb-0">El campo «Débito fiscal dación en pago» se informa vacío. Antes de presentar, verifique en el F.2051 que los importes coincidan con su posición de IVA.</p>
    @else
        <p class="mb-0">Formato: TXT de ancho fijo (ventas 266 / 62 y compras 325 / 84 caracteres), importes sin separador decimal, sin acentos. Si un registro no tiene el largo correcto, aparece en los avisos de arriba.</p>
    @endif
</div>
@endsection
