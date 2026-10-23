@extends('pdf._layout')

@section('titulo', 'ESTADO DE RESULTADOS')
@section('subtitulo', 'del ' . $desde->format('d/m/Y') . ' al ' . $hasta->format('d/m/Y') . ' · en pesos')

@push('estilos')
<style>
    body { font-size: 9pt; }
    .fila-titulo td { font-weight: bold; padding-top: 6px; }
    .fila-total td { font-weight: bold; border-top: .6px solid #888; }
    .fila-subtotal td { font-weight: bold; background: #eef2f7; border-top: 1px solid #333; }
    .fila-resultado td { font-weight: bold; font-size: 10pt; background: #dfe7f1; border-top: 1.5px solid #1e3a5f; border-bottom: 2.5px double #1e3a5f; }
    .col-ant { color: #666; }
</style>
@endpush

@section('contenido')
@php $comp = $anterior !== null; @endphp
<table>
    <thead>
        <tr>
            <th>Concepto</th>
            <th class="num" style="width: 110px;">Período actual</th>
            @if($comp)<th class="num" style="width: 110px;">Ejercicio anterior</th>@endif
        </tr>
    </thead>
    <tbody>
        @include('estados._filas', ['filas' => $filas, 'comparativo' => $comp])
    </tbody>
</table>
<p class="muted" style="margin-top: 8px;">Los importes entre paréntesis son egresos o pérdidas.</p>

<table style="margin-top: 70px;">
    <tr>
        <td style="width: 50%; text-align: center;">______________________________<br>Firma responsable</td>
        <td style="width: 50%; text-align: center;">______________________________<br>Contador Público</td>
    </tr>
</table>
@endsection
