@extends('layouts.app')
@section('title','Resumen')
@section('content')
@php
 $activeShare = $contracts ? round($active/$contracts*100) : 0;
 $money = fn ($value) => number_format($value, 0, ',', '.');
 $todo = $pendingCount + $expiringCount;
 $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->before(' ');
@endphp
<div class="page-head dash-head">
 <div>
  <p class="dash-cut mono">Corte · {{ now()->format('d/m/Y') }}</p>
  <h1>Hola, {{ $firstName }}</h1>
  <p>{{ $todo ? ($todo === 1 ? 'Tienes 1 contrato pendiente de revisión.' : "Tienes {$todo} contratos pendientes de revisión.") : 'Todo al día: no hay retiros sin liquidar ni contratos por vencer.' }}</p>
 </div>
 <a href="{{ route('contracts.create') }}" class="btn primary"><svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#plus"/></svg> Nuevo contrato</a>
</div>

<div class="dash-top">
 <section class="dash-ledger" aria-labelledby="ledger-title">
  <div class="dash-ledger-main">
   <div class="dash-ledger-label"><h2 id="ledger-title">Liquidado histórico</h2><span class="pill mono">{{ $settlementCount }} {{ $settlementCount === 1 ? 'liquidación' : 'liquidaciones' }}</span></div>
   <p class="dash-figure mono"><span aria-hidden="true">$</span>{{ $money($netSettlements) }}<span class="sr-only"> pesos</span></p>
   <p class="dash-figure-meta">Este mes <b class="mono">${{ $money($monthSettlements) }}</b>@if($settlementCount) · promedio <b class="mono">${{ $money($netSettlements / $settlementCount) }}</b> por liquidación @endif</p>
   <div class="dash-split-block">
    @if($contracts)
    <div class="dash-split-head"><span>Contratos activos</span><b class="mono">{{ $activeShare }}%</b></div>
    <div class="dash-split" aria-hidden="true"><i class="is-active" style="flex-grow:{{ max($active, 0.001) }}"></i><i style="flex-grow:{{ max($terminated, 0.001) }}"></i></div>
    <div class="dash-legend"><span><i class="legend-dot success-bg" aria-hidden="true"></i>Activos <b class="mono">{{ $active }}</b></span><span><i class="legend-dot muted-bg" aria-hidden="true"></i>Retirados <b class="mono">{{ $terminated }}</b></span></div>
    @else
    <p class="dash-figure-meta">La distribución de contratos aparecerá cuando registres el primero.</p>
    @endif
   </div>
  </div>
  <dl class="dash-stub">
   <div><dt>Contratos</dt><dd class="mono">{{ $money($contracts) }}</dd></div>
   <div><dt>Activos</dt><dd class="mono">{{ $money($active) }}</dd></div>
   <div><dt>Retirados</dt><dd class="mono">{{ $money($terminated) }}</dd></div>
   <div><dt>Trabajadores</dt><dd class="mono">{{ $money($workers) }}</dd></div>
   <div><dt>Empresas</dt><dd class="mono">{{ $money($companies) }}</dd></div>
  </dl>
 </section>

 <section class="surface dash-queue" aria-labelledby="queue-title">
  <div class="surface-head"><div><h2 id="queue-title">Pendientes</h2><p>Lo que requiere una acción</p></div><span class="dash-count mono {{ $todo ? 'has-items' : '' }}">{{ $todo }}</span></div>
  @if($todo)
  @if($pendingCount)
  <div class="dash-queue-group">
   <h3>Retiros sin liquidar <span class="mono">{{ $pendingCount }}</span></h3>
   <ul>@foreach($pendingList as $c)<li><a href="{{ route('settlements.show',$c) }}"><span><b>{{ $c->worker->full_name }}</b><small>Retiro <span class="mono">{{ $c->end_date?->format('d/m/Y') ?? 'sin fecha' }}</span></small></span><span class="dash-queue-action">Liquidar <svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#arrow-right"/></svg></span></a></li>@endforeach</ul>
   @if($pendingCount > $pendingList->count())<a class="dash-queue-more" href="{{ route('contracts.index',['status'=>'terminated']) }}">Ver contratos retirados</a>@endif
  </div>
  @endif
  @if($expiringCount)
  <div class="dash-queue-group">
   <h3>Término fijo por vencer <span class="mono">{{ $expiringCount }}</span></h3>
   <ul>@foreach($expiringList as $c)@php $days = (int) today()->diffInDays($c->agreed_end_date); @endphp<li><a href="{{ route('contracts.show',$c) }}"><span><b>{{ $c->worker->full_name }}</b><small>Vence <span class="mono">{{ $c->agreed_end_date->format('d/m/Y') }}</span></small></span><span class="dash-days mono {{ $days <= 7 ? 'is-soon' : '' }}">{{ $days === 0 ? 'Hoy' : $days.' d' }}</span></a></li>@endforeach</ul>
  </div>
  @endif
  @else
  <div class="dash-queue-empty"><span aria-hidden="true"><svg class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#check"/></svg></span><p>Todos los retiros están liquidados y ningún contrato a término fijo vence en los próximos 30 días.</p></div>
  @endif
 </section>
</div>

<section class="ai-insights" data-ai="insights" data-url="{{ route('ai.insights') }}" data-contracts-url="{{ url('contratos') }}" aria-labelledby="insights-title"><div class="section-head"><div class="ai-title"><span class="ai-mark" aria-hidden="true"><svg class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#sparkles"/></svg></span><h2 id="insights-title">Para revisar hoy</h2></div><span class="ai-badge" data-ai-provider>IA · {{ app(\App\Services\Ai\AiClient::class)->label() }}</span></div><div class="ai-output" aria-live="polite"></div></section>

<div class="dash-bottom">
 <section class="surface list-surface" aria-labelledby="recent-title">
  <div class="surface-head"><div><h2 id="recent-title">Contratos recientes</h2><p>Últimos registros creados</p></div><a class="btn ghost" href="{{ route('contracts.index') }}">Ver todos <svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#arrow-right"/></svg></a></div>
  <div class="table-scroll"><table class="data-table">
   <thead><tr><th scope="col">Trabajador</th><th scope="col">Empresa / contrato</th><th scope="col">Estado</th><th scope="col">Ingreso</th><th scope="col" class="sticky-actions"><span class="sr-only">Acciones</span></th></tr></thead>
   <tbody>
    @forelse($recent as $c)
    <tr data-href="{{ route('contracts.show',$c) }}"><td><div class="person-cell">@include('partials.avatar',['name'=>$c->worker->full_name])<div><b>{{ $c->worker->full_name }}</b><small class="mono">{{ $c->worker->documentType->code }} {{ $c->worker->document_number }}</small></div></div></td><td><div class="cell-stack"><b>{{ $c->company->name }}</b><small>{{ $c->contractType->name }}</small></div></td><td><span class="status {{ $c->status==='active'?'active':'closed' }}"><i aria-hidden="true"></i>{{ $c->display_status }}</span></td><td class="mono">{{ $c->start_date->format('d/m/Y') }}</td><td class="sticky-actions"><a class="table-action" href="{{ route('contracts.show',$c) }}" aria-label="Ver contrato de {{ $c->worker->full_name }}"><svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#arrow-right"/></svg></a></td></tr>
    @empty
    <tr><td colspan="5" class="empty-row">Aún no hay contratos registrados. <a href="{{ route('contracts.create') }}">Registrar el primero</a></td></tr>
    @endforelse
   </tbody>
  </table></div>
 </section>

 <section class="surface dash-activity" aria-labelledby="activity-title">
  <div class="surface-head"><div><h2 id="activity-title">Nuevos contratos</h2><p>Últimos seis meses</p></div></div>
  @if($months->sum('total') > 0)
  @php $chartMax = max(1, $months->max('total')); $current = $months->last(); @endphp
  <div class="dash-activity-figure"><b class="mono">{{ $months->sum('total') }}</b><span>en el periodo · <b class="mono">{{ $current['total'] }}</b> este mes</span></div>
  <div class="dash-bars" aria-hidden="true">@foreach($months as $month)<div class="{{ $loop->last ? 'is-current' : '' }}"><span class="mono">{{ $month['total'] ?: '' }}</span><i><b style="height:{{ $month['total'] ? max(6, round($month['total']/$chartMax*100,2)) : 0 }}%"></b></i><small>{{ $month['label'] }}</small></div>@endforeach</div>
  <table class="sr-only"><caption>Nuevos contratos por mes</caption><thead><tr><th scope="col">Mes</th><th scope="col">Contratos</th></tr></thead><tbody>@foreach($months as $month)<tr><th scope="row">{{ $month['label'] }}</th><td>{{ $month['total'] }}</td></tr>@endforeach</tbody></table>
  @else
  <div class="chart-empty">No hay contratos creados en los últimos seis meses.</div>
  @endif
 </section>
</div>
@endsection
