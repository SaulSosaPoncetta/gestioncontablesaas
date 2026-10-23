@extends('layouts.app')

@section('title', 'Posición de IVA')
@section('header', 'Libros IVA')

@section('content')
@include('iva._nav')

@php
    $mt = fn ($v) => number_format((float) $v, 2, ',', '.');
    $cerrada = $p['cerrada'];
    $tieneAnterior = $p['anterior'] !== null;
    $puedeCerrar = auth()->user()->puede('contador');
    $periodoTxt = \Carbon\Carbon::createFromFormat('Y-m-d', $periodo . '-01')->format('m/Y');
@endphp

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Posición de IVA · {{ $periodoTxt }}</span>
                @if($cerrada)
                    <span class="badge text-bg-secondary"><i class="bi bi-lock-fill"></i> Cerrada el {{ $cerrada->created_at->format('d/m/Y') }}</span>
                @else
                    <span class="badge text-bg-warning">Borrador (período abierto)</span>
                @endif
            </div>
            <table class="table mb-0">
                <tbody>
                    <tr>
                        <td>Débito fiscal <span class="small text-muted">(IVA ventas)</span></td>
                        <td class="num">{{ $mt($p['debito_fiscal']) }}</td>
                    </tr>
                    <tr>
                        <td>(−) Crédito fiscal <span class="small text-muted">(IVA compras)</span></td>
                        <td class="num">{{ $mt($p['credito_fiscal']) }}</td>
                    </tr>
                    <tr>
                        <td>(−) Saldo a favor técnico del período anterior</td>
                        <td class="num">{{ $mt($p['saldo_tecnico_anterior']) }}</td>
                    </tr>
                    <tr class="table-light fw-semibold">
                        <td>Impuesto determinado</td>
                        <td class="num">{{ $mt($p['impuesto_determinado']) }}</td>
                    </tr>
                    <tr>
                        <td>(−) Percepciones de IVA sufridas</td>
                        <td class="num">{{ $mt($p['percepciones']) }}</td>
                    </tr>
                    <tr>
                        <td>(−) Retenciones de IVA sufridas</td>
                        <td class="num">{{ $mt($p['retenciones']) }}</td>
                    </tr>
                    <tr>
                        <td>(−) Saldo de libre disponibilidad anterior</td>
                        <td class="num">{{ $mt($p['saldo_libre_anterior']) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    @if((float) $p['a_pagar'] > 0)
                        <tr class="table-danger fw-bold fs-6"><td>SALDO A PAGAR</td><td class="num">$ {{ $mt($p['a_pagar']) }}</td></tr>
                    @else
                        <tr class="table-success fw-bold fs-6"><td>Sin saldo a pagar</td><td class="num">$ 0,00</td></tr>
                    @endif
                    @if((float) $p['saldo_tecnico_favor'] > 0)
                        <tr class="table-success"><td>Saldo a favor técnico (pasa al período siguiente)</td><td class="num">{{ $mt($p['saldo_tecnico_favor']) }}</td></tr>
                    @endif
                    @if((float) $p['saldo_libre_favor'] > 0)
                        <tr class="table-success"><td>Saldo de libre disponibilidad (pasa al período siguiente)</td><td class="num">{{ $mt($p['saldo_libre_favor']) }}</td></tr>
                    @endif
                </tfoot>
            </table>
        </div>

        @if($cerrada && $cerrada->asiento)
            <p class="small mt-2">
                <i class="bi bi-journal-check"></i> Asiento de liquidación:
                <a href="{{ route('asientos.show', $cerrada->asiento) }}">N° {{ $cerrada->asiento->numero }}</a>
                @if($cerrada->asiento->estaAnulado())(anulado)@endif
            </p>
        @endif
    </div>

    <div class="col-lg-5">
        @if($cerrada)
            <div class="card mb-3">
                <div class="card-body small">
                    <p>El período está <strong>cerrado</strong>: sus comprobantes de compra y venta ya no se pueden cargar, modificar ni anular.</p>
                    @if($puedeCerrar)
                        <form method="POST" action="{{ route('iva.reabrir', $cerrada) }}"
                              data-confirm="Se anulará el asiento de liquidación y se habilitarán nuevamente los comprobantes del período. ¿Reabrir?">
                            @csrf
                            <button class="btn btn-sm btn-outline-warning"><i class="bi bi-unlock me-1"></i>Reabrir período</button>
                        </form>
                        <div class="form-text">Solo se puede reabrir el último período cerrado.</div>
                    @endif
                </div>
            </div>
        @elseif($puedeCerrar)
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">Cerrar período</div>
                <div class="card-body">
                    <form method="GET" id="form-recalc"></form>
                    <form method="POST" action="{{ route('iva.cerrar') }}">
                        @csrf
                        <input type="hidden" name="periodo" value="{{ $periodo }}">

                        @unless($tieneAnterior)
                            <div class="alert alert-info small py-2">
                                No hay un período anterior cerrado. Si venía con saldos a favor de declaraciones anteriores, cárguelos acá.
                            </div>
                            <div class="mb-2">
                                <label class="form-label small mb-1">Saldo a favor técnico anterior</label>
                                <input type="text" inputmode="decimal" name="saldo_tecnico_anterior" form="form-recalc" value="{{ $manual['saldo_tecnico_anterior'] ? $mt($manual['saldo_tecnico_anterior']) : '' }}" class="form-control form-control-sm text-end">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small mb-1">Saldo de libre disponibilidad anterior</label>
                                <input type="text" inputmode="decimal" name="saldo_libre_anterior" form="form-recalc" value="{{ $manual['saldo_libre_anterior'] ? $mt($manual['saldo_libre_anterior']) : '' }}" class="form-control form-control-sm text-end">
                            </div>
                        @endunless

                        <div class="mb-3">
                            <label class="form-label small mb-1">Otras retenciones de IVA sufridas (sin certificado cargado)</label>
                            <input type="text" inputmode="decimal" name="retenciones" form="form-recalc" value="{{ $manual['retenciones'] ? $mt($manual['retenciones']) : '' }}" class="form-control form-control-sm text-end">
                            <div class="form-text">Los certificados cargados en Retenciones › Sufridas ya se suman solos ($ {{ $mt($p['retenciones_certificados'] ?? 0) }} en este período).</div>
                        </div>

                        <input type="hidden" name="periodo" value="{{ $periodo }}" form="form-recalc">
                        <button type="submit" form="form-recalc" class="btn btn-sm btn-outline-secondary mb-3"><i class="bi bi-arrow-repeat me-1"></i>Recalcular</button>

                        {{-- Copia los valores manuales al formulario de cierre --}}
                        <input type="hidden" name="saldo_tecnico_anterior" value="{{ $manual['saldo_tecnico_anterior'] }}">
                        <input type="hidden" name="saldo_libre_anterior" value="{{ $manual['saldo_libre_anterior'] }}">
                        <input type="hidden" name="retenciones" value="{{ $manual['retenciones'] }}">

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="generar_asiento" value="1" id="gen" checked>
                            <label class="form-check-label small" for="gen">Registrar el asiento de liquidación al {{ \Carbon\Carbon::createFromFormat('Y-m-d', $periodo . '-01')->endOfMonth()->format('d/m/Y') }}</label>
                        </div>

                        <button class="btn btn-primary w-100" onclick="return confirm('Cerrar el período {{ $periodoTxt }}? Los comprobantes del período quedarán bloqueados.')">
                            <i class="bi bi-lock me-1"></i>Cerrar período {{ $periodoTxt }}
                        </button>
                        <div class="form-text">Se cierra con los importes que se ven en pantalla. Si cargó retenciones o saldos, presione antes «Recalcular».</div>
                    </form>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header bg-white fw-semibold small">Últimos períodos cerrados</div>
            <table class="table table-sm mb-0 small">
                <thead class="table-light"><tr><th>Período</th><th class="text-end">A pagar</th><th class="text-end">Saldo a favor</th></tr></thead>
                <tbody>
                @forelse($cerradas as $c)
                    <tr>
                        <td><a href="{{ route('iva.posicion', ['periodo' => $c->periodo]) }}">{{ $c->periodo_texto }}</a></td>
                        <td class="num">{{ (float) $c->a_pagar ? $mt($c->a_pagar) : '' }}</td>
                        <td class="num">{{ ((float) $c->saldo_tecnico_favor + (float) $c->saldo_libre_favor) ? $mt((float) $c->saldo_tecnico_favor + (float) $c->saldo_libre_favor) : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted text-center">Todavía no se cerró ningún período.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
