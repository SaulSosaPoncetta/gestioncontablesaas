@extends('pdf._layout')

@section('titulo', 'LIBRO MAYOR')
@if($mayores->count() === 1)
    @section('subtitulo', $mayores->first()['cuenta']->codigo . ' - ' . $mayores->first()['cuenta']->nombre)
@endif

@push('estilos')
<style>
    .cuenta { margin-top: 10px; font-weight: bold; font-size: 9.5pt; background: #1e3a5f; color: #fff; padding: 4px 6px; }
    .anterior td { font-style: italic; background: #f7f7f7; }
</style>
@endpush

@section('contenido')
@php $saldoTxt = fn ($v) => number_format(abs($v), 2, ',', '.') . ($v > 0 ? ' D' : ($v < 0 ? ' A' : '')); @endphp

@forelse($mayores as $i => $m)
    <div class="cuenta" @if($i > 0) style="page-break-before: auto;" @endif>
        {{ $m['cuenta']->codigo }} — {{ $m['cuenta']->nombre }}
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 58px;">Fecha</th>
                <th style="width: 42px;">Asiento</th>
                <th>Concepto</th>
                <th class="num" style="width: 82px;">Debe</th>
                <th class="num" style="width: 82px;">Haber</th>
                <th class="num" style="width: 92px;">Saldo</th>
            </tr>
        </thead>
        <tbody>
            <tr class="anterior">
                <td colspan="5">Saldo anterior al {{ $desde->format('d/m/Y') }}</td>
                <td class="num">{{ $saldoTxt($m['anterior']) }}</td>
            </tr>
            @foreach($m['movimientos'] as $mov)
                <tr class="sep">
                    <td>{{ $mov->fecha->format('d/m/Y') }}</td>
                    <td class="mono">{{ $mov->numero }}</td>
                    <td>{{ $mov->concepto }}@if($mov->detalle)<span class="muted"> — {{ $mov->detalle }}</span>@endif</td>
                    <td class="num">{{ $mov->debe > 0 ? number_format($mov->debe, 2, ',', '.') : '' }}</td>
                    <td class="num">{{ $mov->haber > 0 ? number_format($mov->haber, 2, ',', '.') : '' }}</td>
                    <td class="num">{{ $saldoTxt($mov->saldo) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3" style="text-align: right;">Totales</td>
                <td class="num">{{ number_format($m['debe'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($m['haber'], 2, ',', '.') }}</td>
                <td class="num">{{ $saldoTxt($m['saldo']) }}</td>
            </tr>
        </tbody>
    </table>
@empty
    <p class="muted">No hay movimientos en el período.</p>
@endforelse
@endsection
