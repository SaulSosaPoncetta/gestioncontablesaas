@extends('pdf._layout')

@section('titulo', 'BALANCE DE SUMAS Y SALDOS')
@section('subtitulo', 'Nivel de detalle: ' . $nivel . ($nivel === $maxNivel ? ' (analítico)' : ''))

@push('estilos')
<style>
    .n1 td { font-weight: bold; background: #eef2f7; }
    .n2 td { font-weight: bold; }
    .bl { border-left: .5px solid #ccc; }
</style>
@endpush

@section('contenido')
@php $f2 = fn ($v) => $v != 0 ? number_format($v, 2, ',', '.') : ''; @endphp

<table>
    <thead>
        <tr>
            <th style="width: 70px;">Código</th>
            <th>Cuenta</th>
            <th class="num bl" style="width: 80px;">Sumas Debe</th>
            <th class="num" style="width: 80px;">Sumas Haber</th>
            <th class="num bl" style="width: 80px;">Saldo Deudor</th>
            <th class="num" style="width: 80px;">Saldo Acreedor</th>
        </tr>
    </thead>
    <tbody>
    @foreach($filas as $f)
        <tr class="n{{ $f->cuenta->nivel }} sep">
            <td class="mono">{{ $f->cuenta->codigo }}</td>
            <td style="padding-left: {{ 4 + ($f->cuenta->nivel - 1) * 9 }}px;">{{ $f->cuenta->nombre }}</td>
            <td class="num bl">{{ $f2($f->debe) }}</td>
            <td class="num">{{ $f2($f->haber) }}</td>
            <td class="num bl">{{ $f2($f->deudor) }}</td>
            <td class="num">{{ $f2($f->acreedor) }}</td>
        </tr>
    @endforeach
        <tr class="total">
            <td colspan="2" style="text-align: right;">TOTALES</td>
            <td class="num bl">{{ number_format($totales['debe'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totales['haber'], 2, ',', '.') }}</td>
            <td class="num bl">{{ number_format($totales['deudor'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totales['acreedor'], 2, ',', '.') }}</td>
        </tr>
    </tbody>
</table>
@endsection
