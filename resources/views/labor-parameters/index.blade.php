@extends('layouts.app')
@section('title','Parámetros laborales')
@section('breadcrumb','Administración / Parámetros laborales')
@section('content')
<div class="page-head"><div><h1>Parámetros laborales por año</h1><p>La fecha de retiro determina la vigencia. Los cálculos guardados conservan los valores con los que se liquidaron.</p></div></div>
@foreach($parameters as $parameter)
<section class="surface details-card">
 <div class="surface-head"><div><h2>Vigencia {{ $parameter->year }}</h2><p>Valores de referencia y aportes del trabajador</p></div></div>
 <form class="catalog-form" method="POST" action="{{ route('labor-parameters.update',$parameter) }}">@csrf @method('PUT')
  @include('labor-parameters.fields',['parameter'=>$parameter,'prefix'=>'year-'.$parameter->id])
  <div class="form-actions"><button class="btn primary" type="submit">Guardar vigencia</button></div>
 </form>
 <form method="POST" action="{{ route('labor-parameters.destroy',$parameter) }}" onsubmit="return confirm('¿Eliminar esta vigencia sin contratos asociados?')">@csrf @method('DELETE')<button class="btn danger" type="submit">Eliminar vigencia</button></form>
</section>
@endforeach
<section class="surface details-card">
 <div class="surface-head"><div><h2>Nueva vigencia</h2><p>Registra los valores y las fuentes oficiales antes de liquidar contratos de otro año.</p></div></div>
 <form class="catalog-form" method="POST" action="{{ route('labor-parameters.store') }}">@csrf
  @include('labor-parameters.fields',['parameter'=>new \App\Models\LaborParameter(),'prefix'=>'new-year'])
  <div class="form-actions"><button class="btn primary" type="submit">Agregar vigencia</button></div>
 </form>
</section>
@endsection
