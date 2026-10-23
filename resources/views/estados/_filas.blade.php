{{--
    Filas de un estado contable (se usa en pantalla y en PDF).
    Variables: $filas (array de objetos tipo/texto/sangria/importe/anterior), $comparativo (bool)
    Importes negativos entre paréntesis.
--}}
@php
    $fmtImporte = function ($v) {
        if ($v === null) return '';
        if (round($v, 2) == 0) return '-';
        return $v < 0 ? '(' . number_format(-$v, 2, ',', '.') . ')' : number_format($v, 2, ',', '.');
    };
@endphp
@foreach($filas as $f)
    <tr class="fila-{{ $f->tipo }}">
        <td style="padding-left: {{ 6 + $f->sangria * 16 }}px;">{{ $f->texto }}</td>
        <td class="num">{{ $f->tipo === 'titulo' ? '' : $fmtImporte($f->importe) }}</td>
        @if($comparativo)
            <td class="num col-ant">{{ $f->tipo === 'titulo' ? '' : $fmtImporte($f->anterior) }}</td>
        @endif
    </tr>
@endforeach
