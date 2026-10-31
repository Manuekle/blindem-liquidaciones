<div class="form-grid three">
@foreach(['year'=>'Año','min_wage'=>'Salario mínimo mensual (COP)','transport_allowance'=>'Auxilio de transporte mensual (COP)'] as $name=>$label)
<div class="field"><label class="field-label" for="{{ $prefix }}-{{ $name }}">{{ $label }} *</label><input id="{{ $prefix }}-{{ $name }}" name="{{ $name }}" type="number" min="{{ $name==='year'?1900:($name==='min_wage'?1:0) }}" @if($name==='year')max="2100"@endif value="{{ $parameter->$name }}" required></div>
@endforeach
@foreach(['health'=>'Salud trabajador (%)','pension'=>'Pensión trabajador (%)','interest'=>'Intereses cesantías (%)'] as $name=>$label)
<div class="field"><label class="field-label" for="{{ $prefix }}-{{ $name }}">{{ $label }} *</label><input id="{{ $prefix }}-{{ $name }}" name="{{ $name }}_percent" type="number" min="0" max="100" step="0.001" value="{{ ($parameter->{$name.'_rate'}??($name==='interest'?0.12:0.04))*100 }}" required></div>
@endforeach
</div>
<div class="form-grid">
@foreach(['wage_source'=>'Fuente oficial del salario mínimo','transport_source'=>'Fuente oficial del auxilio de transporte'] as $name=>$label)
<div class="field"><label class="field-label" for="{{ $prefix }}-{{ $name }}">{{ $label }} *</label><input id="{{ $prefix }}-{{ $name }}" name="{{ $name }}" type="url" maxlength="500" value="{{ $parameter->$name }}" placeholder="https://" required></div>
@endforeach
</div>
