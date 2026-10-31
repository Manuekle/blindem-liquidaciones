@extends('layouts.app')
@section('title','Resumen')
@section('content')
<div class="page-head">
 <div><h1>Resumen</h1><p>Contratos, personas y actividad de tu operación.</p></div>
 <a href="{{ route('contracts.create') }}" class="btn primary"><svg aria-hidden="true" class="lucide" focusable="false" viewBox="0 0 24 24"><use href="{{ asset('icons/lucide.svg') }}#plus"/></svg> Nuevo contrato</a>
</div>
<div class="stats-grid metric-strip">
 <div class="stat-card"><div class="stat-label">Contratos</div><div class="stat-value">{{ number_format($contracts,0,',','.') }}</div><div class="stat-meta">Registros históricos</div></div>
 <div class="stat-card"><div class="stat-label">Trabajadores</div><div class="stat-value">{{ number_format($workers,0,',','.') }}</div><div class="stat-meta">Personas registradas</div></div>
 <div class="stat-card"><div class="stat-label">Empresas</div><div class="stat-value">{{ number_format($companies,0,',','.') }}</div><div class="stat-meta">Empleadores registrados</div></div>
 <div class="stat-card"><div class="stat-label">Liquidado histórico</div><div class="stat-value money-small">${{ number_format($netSettlements,0,',','.') }}</div><div class="stat-meta">Liquidaciones guardadas</div></div>
</div>
<section class="surface list-surface dashboard-recent" aria-labelledby="recent-title">
 <div class="surface-head"><div><h2 id="recent-title">Contratos recientes</h2><p>Consulta los últimos registros</p></div><a class="btn ghost" href="{{ route('contracts.index') }}">Ver todos <svg aria-hidden="true" class="lucide" focusable="false" viewBox="0 0 24 24"><use href="{{ asset('icons/lucide.svg') }}#arrow-right"/></svg></a></div>
 <div class="table-scroll"><table class="data-table">
  <thead><tr><th scope="col">Trabajador</th><th scope="col">Empresa</th><th scope="col">Contrato</th><th scope="col">Estado</th><th scope="col">Fecha</th><th scope="col"><span class="sr-only">Acciones</span></th></tr></thead>
  <tbody>
   @forelse($recent as $c)
   <tr><td><div class="person-cell"><div><b>{{ $c->worker->full_name }}</b><small>{{ $c->worker->documentType->code }} · {{ $c->worker->document_number }}</small></div></div></td><td>{{ $c->company->name }}</td><td>{{ $c->contractType->name }}</td><td><span class="status {{ $c->status==='active'?'active':'closed' }}"><i aria-hidden="true"></i>{{ $c->display_status }}</span></td><td>{{ $c->start_date->format('d/m/Y') }}</td><td><a class="table-action" href="{{ route('contracts.show',$c) }}" aria-label="Ver contrato de {{ $c->worker->full_name }}"><svg aria-hidden="true" class="lucide" focusable="false" viewBox="0 0 24 24"><use href="{{ asset('icons/lucide.svg') }}#arrow-right"/></svg></a></td></tr>
   @empty
   <tr><td colspan="6" class="empty-row">Aún no hay contratos registrados. <a href="{{ route('contracts.create') }}">Crear el primero</a></td></tr>
   @endforelse
  </tbody>
 </table></div>
</section>
<div class="dashboard-grid">
 <section class="surface chart-card" aria-labelledby="activity-title">
  <div class="surface-head"><div><h2 id="activity-title">Actividad de contratos</h2><p>Nuevos contratos durante los últimos seis meses</p></div></div>
  @if($months->sum('total') > 0)
  @php $chartMax=(int)(ceil(max(1,$months->max('total'))/4)*4); @endphp
  <div class="chart-content" aria-hidden="true">
   <div class="chart-y">@foreach([4,3,2,1,0] as $tick)<span>{{ number_format($chartMax*$tick/4,0,',','.') }}</span>@endforeach</div>
   <div class="chart-plot">
    <div class="grid-lines"><i></i><i></i><i></i><i></i><i></i></div>
    <div class="chart-bars">@foreach($months as $month)<div class="bar-group"><div class="bar-track"><div class="bar-fill" style="height:{{ round($month['total']/$chartMax*100,2) }}%"><span>{{ $month['total'] }}</span></div></div><small>{{ $month['label'] }}</small></div>@endforeach</div>
   </div>
  </div>
  <table class="sr-only"><caption>Nuevos contratos por mes</caption><thead><tr><th scope="col">Mes</th><th scope="col">Contratos</th></tr></thead><tbody>@foreach($months as $month)<tr><th scope="row">{{ $month['label'] }}</th><td>{{ $month['total'] }}</td></tr>@endforeach</tbody></table>
  @else
  <div class="chart-empty">No hay contratos creados en los últimos seis meses.</div>
  @endif
  <div class="chart-foot"><span><i class="legend-dot accent-bg" aria-hidden="true"></i> Nuevos contratos</span><span>Últimos 6 meses</span></div>
 </section>
 <section class="surface overview-card" aria-labelledby="status-title">
  <div class="surface-head"><div><h2 id="status-title">Estado de contratos</h2><p>Distribución actual</p></div></div>
  @if($contracts)
  <div class="donut-wrap"><div class="donut" style="--active:{{ round($active/$contracts*100,2) }}%" aria-hidden="true"><div><b>{{ number_format($contracts,0,',','.') }}</b><small>Contratos</small></div></div></div>
  <p class="sr-only">{{ $contracts }} contratos en total.</p>
  <div class="status-breakdown"><div><span><i class="legend-dot success-bg" aria-hidden="true"></i> Activos</span><strong>{{ $active }}</strong></div><div><span><i class="legend-dot muted-bg" aria-hidden="true"></i> Retirados</span><strong>{{ $terminated }}</strong></div></div>
  @else
  <div class="chart-empty">Los estados aparecerán cuando registres tu primer contrato.</div>
  @endif
  <a href="{{ route('contracts.index') }}" class="text-link">Ver todos los contratos <svg aria-hidden="true" class="lucide" focusable="false" viewBox="0 0 24 24"><use href="{{ asset('icons/lucide.svg') }}#arrow-right"/></svg></a>
 </section>
</div>

@endsection
