@extends('pdf._layout')

@section('titulo', 'LIBRO DIARIO')

@push('estilos')
<style>
    .cab td { background: #f1f4f8; font-weight: bold; border-top: 1px solid #b8c4d2; padding-top: 4px; }
    .haber { padding-left: 22px; }
    .anulado td { color: #999; }
</style>
@endpush

@section('contenido')
<table>
    <thead>
        <tr>
            <th style="width: 75px;">Código</th>
            <th>Cuenta</th>
            <th class="num" style="width: 95px;">Debe</th>
            <th class="num" style="width: 95px;">Haber</th>
        </tr>
    </thead>
    <tbody>
    @foreach($asientos as $a)
        <tr class="cab {{ $a->estaAnulado() ? 'anulado' : '' }}">
            <td colspan="4">
                Asiento N° {{ $a->numero }} &nbsp;—&nbsp; {{ $a->fecha->format('d/m/Y') }} &nbsp;—&nbsp; {{ $a->concepto }}
                @if($a->estaAnulado()) &nbsp;[ANULADO: {{ $a->motivo_anulacion }}]@endif
            </td>
        </tr>
        @unless($a->estaAnulado())
            @foreach($a->lineas as $l)
                <tr>
                    <td class="mono muted">{{ $l->cuenta->codigo }}</td>
                    <td class="{{ (float) $l->haber > 0 ? 'haber' : '' }}">
                        {{ (float) $l->haber > 0 ? 'a ' : '' }}{{ $l->cuenta->nombre }}
                        @if($l->detalle)<span class="muted"> — {{ $l->detalle }}</span>@endif
                    </td>
                    <td class="num">{{ (float) $l->debe > 0 ? number_format($l->debe, 2, ',', '.') : '' }}</td>
                    <td class="num">{{ (float) $l->haber > 0 ? number_format($l->haber, 2, ',', '.') : '' }}</td>
                </tr>
            @endforeach
        @endunless
    @endforeach
        <tr class="total">
            <td colspan="2" style="text-align: right;">TOTALES DEL PERÍODO ({{ $totales['asientos'] }} asientos)</td>
            <td class="num">{{ number_format($totales['debe'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($totales['haber'], 2, ',', '.') }}</td>
        </tr>
    </tbody>
</table>
@endsection
