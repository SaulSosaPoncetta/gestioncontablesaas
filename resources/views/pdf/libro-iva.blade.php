@extends('pdf._layout')

@section('titulo', $circuito === 'venta' ? 'LIBRO IVA VENTAS' : 'LIBRO IVA COMPRAS')
@section('subtitulo', 'Período IVA ' . \Carbon\Carbon::createFromFormat('Y-m-d', $periodo . '-01')->format('m/Y'))

@push('estilos')
<style>
    body { font-size: 7pt; }
    th { font-size: 6.3pt; }
    td { padding: 2px 3px; }
    .neg td { color: #b02a37; }
</style>
@endpush

@section('contenido')
@php
    $m = fn ($v) => round($v, 2) == 0 ? '' : number_format($v, 2, ',', '.');
    $mt = fn ($v) => number_format($v, 2, ',', '.');
@endphp
<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Comprobante</th>
            <th>{{ $circuito === 'venta' ? 'Cliente' : 'Proveedor' }}</th>
            <th>CUIT / Doc.</th>
            <th class="num">Neto grav.</th>
            <th class="num">IVA 21%</th>
            <th class="num">IVA 10,5%</th>
            <th class="num">IVA otras</th>
            <th class="num">No grav.</th>
            <th class="num">Exento</th>
            <th class="num">Perc. IVA</th>
            <th class="num">Perc. IIBB</th>
            <th class="num">Otros</th>
            <th class="num">Total</th>
        </tr>
    </thead>
    <tbody>
    @foreach($filas as $f)
        <tr class="sep {{ $f->total < 0 ? 'neg' : '' }}">
            <td>{{ $f->comprobante->fecha->format('d/m/Y') }}</td>
            <td class="mono">{{ $f->comprobante->descripcion_corta }}</td>
            <td>{{ \Illuminate\Support\Str::limit($f->comprobante->tercero->razon_social, 38) }}</td>
            <td>{{ $f->comprobante->tercero->nro_documento }}</td>
            <td class="num">{{ $m($f->neto) }}</td>
            <td class="num">{{ $m($f->iva21) }}</td>
            <td class="num">{{ $m($f->iva105) }}</td>
            <td class="num">{{ $m($f->iva_otras) }}</td>
            <td class="num">{{ $m($f->no_gravado) }}</td>
            <td class="num">{{ $m($f->exento) }}</td>
            <td class="num">{{ $m($f->percepcion_iva) }}</td>
            <td class="num">{{ $m($f->percepcion_iibb) }}</td>
            <td class="num">{{ $m($f->otros) }}</td>
            <td class="num">{{ $mt($f->total) }}</td>
        </tr>
    @endforeach
        <tr class="total">
            <td colspan="4" style="text-align: right;">TOTALES ({{ $filas->count() }} comprobantes)</td>
            <td class="num">{{ $mt($totales['neto']) }}</td>
            <td class="num">{{ $mt($totales['iva21']) }}</td>
            <td class="num">{{ $mt($totales['iva105']) }}</td>
            <td class="num">{{ $mt($totales['iva_otras']) }}</td>
            <td class="num">{{ $mt($totales['no_gravado']) }}</td>
            <td class="num">{{ $mt($totales['exento']) }}</td>
            <td class="num">{{ $mt($totales['percepcion_iva']) }}</td>
            <td class="num">{{ $mt($totales['percepcion_iibb']) }}</td>
            <td class="num">{{ $mt($totales['otros']) }}</td>
            <td class="num">{{ $mt($totales['total']) }}</td>
        </tr>
    </tbody>
</table>

<table style="width: 45%; margin-top: 14px;" class="no-break">
    <thead><tr><th>Alícuota</th><th class="num">Neto gravado</th><th class="num">IVA</th></tr></thead>
    <tbody>
    @foreach($alicuotas as $id => $a)
        <tr class="sep">
            <td>{{ str_replace('.', ',', \App\Support\Afip::ALICUOTAS[$id] ?? 0) }} %</td>
            <td class="num">{{ $mt($a['neto']) }}</td>
            <td class="num">{{ $mt($a['iva']) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endsection
