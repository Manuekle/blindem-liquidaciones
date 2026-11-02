@php $date=fn($v)=>$v?\Illuminate\Support\Carbon::parse($v)->format('d/m/Y'):'—'; @endphp
<section class="surface details-card">
 <div class="surface-head"><h2>{{ $saved?'Datos del contrato al guardar':'Datos del contrato' }}</h2></div>
 <dl class="detail-list">
  <div><dt>Tipo de contrato</dt><dd>{{ $display['contract_type_name'] }}</dd></div>
  <div><dt>Cargo</dt><dd>{{ $display['job_title'] }}</dd></div>
  <div><dt>Motivo de retiro</dt><dd>{{ $display['termination_reason'] }}</dd></div>
  <div><dt>NIT de la empresa</dt><dd class="num">{{ $display['company_nit'] }}</dd></div>
  <div><dt>Fecha de ingreso</dt><dd class="num">{{ $date($i['start_date']) }}</dd></div>
  <div><dt>Fecha de retiro o corte</dt><dd class="num">{{ $date($i['end_date']) }}</dd></div>
  @if($i['agreed_end_date']??null)<div><dt>Finalización pactada</dt><dd class="num">{{ $date($i['agreed_end_date']) }}</dd></div>@endif
 </dl>
</section>
