<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Certificado de retención {{ $r->numero_certificado }}</title>
    <style>
        @page { margin: 40px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 9.5pt; color: #222; }
        h1 { font-size: 14pt; color: #1e3a5f; margin: 0; }
        .caja { border: 1px solid #b8c4d2; padding: 10px 12px; margin-bottom: 12px; }
        .caja h2 { font-size: 9pt; text-transform: uppercase; color: #555; margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 4px; vertical-align: top; }
        .lbl { color: #666; width: 38%; }
        .num { text-align: right; }
        .total td { font-weight: bold; font-size: 11pt; border-top: 1.5px solid #1e3a5f; }
        .firma { margin-top: 70px; text-align: center; }
    </style>
</head>
<body>
@php $m = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp

<table style="margin-bottom: 14px;">
    <tr>
        <td><h1>CERTIFICADO DE RETENCIÓN</h1><div>{{ $r->impuesto_texto }}</div></td>
        <td class="num">
            <div style="font-size: 12pt; font-weight: bold;">N° {{ $r->numero_certificado }}</div>
            <div>Fecha: {{ $r->fecha->format('d/m/Y') }}</div>
        </td>
    </tr>
</table>

<div class="caja">
    <h2>Agente de retención</h2>
    <table>
        <tr><td class="lbl">Razón social</td><td>{{ $empresa->razon_social }}</td></tr>
        <tr><td class="lbl">CUIT</td><td>{{ $empresa->cuit_formateado }}</td></tr>
        <tr><td class="lbl">Domicilio</td><td>{{ trim($empresa->domicilio . ' ' . $empresa->localidad . ' ' . $empresa->provincia) ?: '—' }}</td></tr>
        @if(in_array($r->impuesto, ['iibb_arba', 'iibb_agip', 'iibb_otra']) && $empresa->nro_iibb)
            <tr><td class="lbl">N° IIBB</td><td>{{ $empresa->nro_iibb }}</td></tr>
        @endif
    </table>
</div>

<div class="caja">
    <h2>Sujeto retenido</h2>
    <table>
        <tr><td class="lbl">Razón social</td><td>{{ $r->tercero->razon_social }}</td></tr>
        <tr><td class="lbl">CUIT</td><td>{{ $r->tercero->documento_formateado }}</td></tr>
        <tr><td class="lbl">Domicilio</td><td>{{ trim($r->tercero->domicilio . ' ' . $r->tercero->localidad . ' ' . $r->tercero->provincia) ?: '—' }}</td></tr>
        <tr><td class="lbl">Condición</td><td>{{ $r->condicion === 'no_inscripto' ? 'No inscripto' : 'Inscripto' }}</td></tr>
    </table>
</div>

<div class="caja">
    <h2>Detalle de la retención</h2>
    <table>
        @if($r->regimen)
            <tr><td class="lbl">Régimen</td><td>{{ $r->regimen->codigo }} – {{ $r->regimen->descripcion }}</td></tr>
        @endif
        @if($r->comprobante)
            <tr><td class="lbl">Comprobante</td><td>{{ $r->comprobante->descripcion_corta }} del {{ $r->comprobante->fecha->format('d/m/Y') }}</td></tr>
        @endif
        <tr><td class="lbl">Importe del pago / comprobante</td><td class="num">$ {{ $m($r->importe_comprobante) }}</td></tr>
        <tr><td class="lbl">Base imponible</td><td class="num">$ {{ $m($r->base_imponible) }}</td></tr>
        <tr><td class="lbl">Alícuota</td><td class="num">{{ $m($r->alicuota) }} %</td></tr>
        <tr class="total"><td>IMPORTE RETENIDO</td><td class="num">$ {{ $m($r->importe) }}</td></tr>
    </table>
</div>

<div class="firma">
    ______________________________<br>
    Firma y aclaración del agente de retención
</div>
</body>
</html>
