@extends('pdf._layout')

@section('titulo', 'ESTADO DE SITUACIÓN PATRIMONIAL')
@section('subtitulo', 'al ' . $fecha->format('d/m/Y') . ' · en pesos')

@push('estilos')
<style>
    body { font-size: 9pt; }
    .seccion td { background: #1e3a5f; color: #fff; font-weight: bold; padding: 4px 6px; }
    .fila-titulo td { font-weight: bold; padding-top: 5px; }
    .fila-total td { font-weight: bold; border-top: .6px solid #888; }
    .fila-subtotal td { font-weight: bold; background: #eef2f7; border-top: 1px solid #333; }
    .gran-total td { font-weight: bold; background: #dfe7f1; border-top: 1.5px solid #1e3a5f; border-bottom: 2.5px double #1e3a5f; }
    .col-ant { color: #666; }
</style>
@endpush

@section('contenido')
@php
    $comp = $anterior !== null;
    $m = fn ($v) => $v === null ? '' : number_format($v, 2, ',', '.');
@endphp
<table>
    <thead>
        <tr>
            <th>Concepto</th>
            <th class="num" style="width: 110px;">{{ $fecha->format('d/m/Y') }}</th>
            @if($comp)<th class="num" style="width: 110px;">{{ $anterior->fecha_cierre->format('d/m/Y') }}</th>@endif
        </tr>
    </thead>
    <tbody>
    @foreach(['1', '2', '3'] as $raiz)
        @php $s = $secciones[$raiz]; @endphp
        <tr class="seccion"><td colspan="{{ $comp ? 3 : 2 }}">{{ $s['titulo'] }}</td></tr>
        @include('estados._filas', ['filas' => $s['filas'], 'comparativo' => $comp])
        <tr class="fila-subtotal">
            <td>TOTAL {{ $s['titulo'] }}</td>
            <td class="num">{{ $m($s['total']) }}</td>
            @if($comp)<td class="num col-ant">{{ $m($s['anterior']) }}</td>@endif
        </tr>
        <tr><td colspan="{{ $comp ? 3 : 2 }}" style="height: 6px;"></td></tr>
    @endforeach
        <tr class="gran-total">
            <td>TOTAL PASIVO + PATRIMONIO NETO</td>
            <td class="num">{{ $m($pasivoMasPn) }}</td>
            @if($comp)<td class="num col-ant">{{ $m($pasivoMasPnAnt) }}</td>@endif
        </tr>
    </tbody>
</table>

<table style="margin-top: 70px;">
    <tr>
        <td style="width: 50%; text-align: center;">______________________________<br>Firma responsable</td>
        <td style="width: 50%; text-align: center;">______________________________<br>Contador Público</td>
    </tr>
</table>
@endsection
