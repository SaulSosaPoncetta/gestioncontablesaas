@extends('pdf._layout')

@section('titulo', 'POSICIÓN DE IVA')
@section('subtitulo', 'Período ' . \Carbon\Carbon::createFromFormat('Y-m-d', $periodo . '-01')->format('m/Y') . ($p['cerrada'] ? ' · cerrado' : ' · borrador'))

@push('estilos')
<style>
    body { font-size: 10pt; }
    .liq td { padding: 6px 8px; border-bottom: .5px solid #ddd; }
    .det td { font-weight: bold; background: #f1f4f8; }
    .final td { font-weight: bold; font-size: 11pt; background: #dfe7f1; border-top: 1.5px solid #1e3a5f; border-bottom: 2.5px double #1e3a5f; }
</style>
@endpush

@section('contenido')
@php $mt = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp
<table class="liq" style="width: 75%; margin: 20px auto 0;">
    <tr><td>Débito fiscal</td><td class="num">{{ $mt($p['debito_fiscal']) }}</td></tr>
    <tr><td>(−) Crédito fiscal</td><td class="num">{{ $mt($p['credito_fiscal']) }}</td></tr>
    <tr><td>(−) Saldo a favor técnico anterior</td><td class="num">{{ $mt($p['saldo_tecnico_anterior']) }}</td></tr>
    <tr class="det"><td>Impuesto determinado</td><td class="num">{{ $mt($p['impuesto_determinado']) }}</td></tr>
    <tr><td>(−) Percepciones de IVA sufridas</td><td class="num">{{ $mt($p['percepciones']) }}</td></tr>
    <tr><td>(−) Retenciones de IVA sufridas</td><td class="num">{{ $mt($p['retenciones']) }}</td></tr>
    <tr><td>(−) Saldo de libre disponibilidad anterior</td><td class="num">{{ $mt($p['saldo_libre_anterior']) }}</td></tr>
    <tr class="final"><td>SALDO A PAGAR</td><td class="num">$ {{ $mt($p['a_pagar']) }}</td></tr>
    <tr><td>Saldo a favor técnico (al período siguiente)</td><td class="num">{{ $mt($p['saldo_tecnico_favor']) }}</td></tr>
    <tr><td>Saldo de libre disponibilidad (al período siguiente)</td><td class="num">{{ $mt($p['saldo_libre_favor']) }}</td></tr>
</table>

<p class="muted" style="width: 75%; margin: 14px auto 0;">
    Liquidación de control interno. La declaración jurada se presenta ante ARCA con el formulario 2051 (IVA Simple).
</p>
@endsection
