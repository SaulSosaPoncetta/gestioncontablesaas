<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('titulo')</title>
    <style>
        @page { margin: 95px 35px 45px 35px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 8.5pt; color: #222; }
        header { position: fixed; top: -80px; left: 0; right: 0; height: 70px; border-bottom: 1.5px solid #1e3a5f; }
        footer { position: fixed; bottom: -30px; left: 0; right: 0; height: 20px; font-size: 7pt; color: #777; border-top: .5px solid #ccc; padding-top: 3px; }
        .pagina:after { content: "Hoja " counter(page) " de " counter(pages); }
        header .empresa { font-size: 11pt; font-weight: bold; }
        header .titulo { font-size: 13pt; font-weight: bold; color: #1e3a5f; text-align: right; }
        header td { vertical-align: top; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #e9eef4; font-size: 7.5pt; text-transform: uppercase; padding: 4px; text-align: left; border-bottom: 1px solid #b8c4d2; }
        td { padding: 2.5px 4px; }
        .num { text-align: right; white-space: nowrap; }
        .mono { font-family: "DejaVu Sans Mono", monospace; font-size: 7.5pt; }
        .muted { color: #777; }
        .total td { font-weight: bold; border-top: 1.2px solid #333; background: #f3f5f8; }
        .sep td { border-top: .5px solid #ddd; }
        .no-break { page-break-inside: avoid; }
    </style>
    @stack('estilos')
</head>
<body>
<header>
    <table>
        <tr>
            <td style="width: 60%;">
                <div class="empresa">{{ $empresa->razon_social }}</div>
                <div>CUIT {{ $empresa->cuit_formateado }} · {{ \App\Models\Empresa::CONDICIONES_IVA[$empresa->condicion_iva] ?? '' }}</div>
                @if($empresa->domicilio)<div class="muted">{{ $empresa->domicilio }}{{ $empresa->localidad ? ', ' . $empresa->localidad : '' }}</div>@endif
            </td>
            <td style="width: 40%;">
                <div class="titulo">@yield('titulo')</div>
                <div style="text-align: right;">
                    @if(! empty($ejercicio))Ejercicio N° {{ $ejercicio->numero }} · @endif Período {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}
                </div>
                @hasSection('subtitulo')<div style="text-align: right;" class="muted">@yield('subtitulo')</div>@endif
            </td>
        </tr>
    </table>
</header>

<footer>
    <table>
        <tr>
            <td>Emitido el {{ now()->format('d/m/Y H:i') }} · MiGestión Contable</td>
            <td style="text-align: right;" class="pagina"></td>
        </tr>
    </table>
</footer>

<main>
    @yield('contenido')
</main>
</body>
</html>
