@extends('layouts.app')

@section('title', 'Regímenes de retención')
@section('header', 'Retenciones y percepciones')

@section('actions')
    @if(auth()->user()->puede('contador'))
        <a href="{{ route('regimenes.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Nuevo régimen</a>
        <form method="POST" action="{{ route('regimenes.habituales') }}" class="d-inline" data-confirm="Se agregarán regímenes de referencia (Ganancias 078, 094, 031, 116; IVA 212; ARBA). Deberá completar mínimos y verificar alícuotas. ¿Continuar?">
            @csrf
            <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-magic me-1"></i>Cargar habituales</button>
        </form>
    @endif
@endsection

@section('content')
@include('retenciones._nav')
@php $m = fn ($v) => number_format((float) $v, 2, ',', '.'); @endphp

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Impuesto</th><th>Código</th><th>Descripción</th>
                    <th class="text-end">Alíc. insc.</th><th class="text-end">Alíc. no insc.</th>
                    <th class="text-end">Mín. no sujeto</th><th class="text-end">Mín. retención</th>
                    <th class="text-center">Acum. mes</th><th class="text-center">Usos</th><th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($regimenes as $r)
                <tr class="{{ $r->activo ? '' : 'text-muted' }}">
                    <td>{{ \App\Models\RegimenRetencion::IMPUESTOS[$r->impuesto] ?? $r->impuesto }}</td>
                    <td class="font-monospace">{{ $r->codigo }}</td>
                    <td>{{ $r->descripcion }} @unless($r->activo)<span class="badge text-bg-light border">inactivo</span>@endunless</td>
                    <td class="num">{{ $m($r->alicuota_inscripto) }}%</td>
                    <td class="num">{{ $m($r->alicuota_no_inscripto) }}%</td>
                    <td class="num">{{ $m($r->minimo_no_sujeto) }}</td>
                    <td class="num">{{ $m($r->minimo_retencion) }}</td>
                    <td class="text-center">@if($r->acumula_mensual)<i class="bi bi-check-lg text-success"></i>@endif</td>
                    <td class="text-center">{{ $r->retenciones_count }}</td>
                    <td class="text-end">
                        @if(auth()->user()->puede('contador'))
                            <a href="{{ route('regimenes.edit', $r) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-muted py-4">No hay regímenes configurados. Use «Cargar habituales» para empezar.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2">
    Con <strong>acumulación mensual</strong> (Ganancias, RG 830) la retención se calcula sobre los pagos del mes al mismo proveedor, menos el mínimo no sujeto y lo ya retenido.
    Los regímenes con escala (por ejemplo, honorarios) se cargan con importe manual.
</p>
@endsection
