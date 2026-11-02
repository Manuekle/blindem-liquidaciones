@extends('layouts.app')
@section('title',$user->exists?'Editar usuario':'Nuevo usuario')
@section('breadcrumb','Usuarios / Formulario')
@section('page-class','form-page')
@section('content')
@php $self = $user->exists && $user->is(auth()->user()); @endphp
@include('partials.back',['href'=>route('users.index'),'label'=>'Usuarios'])
<div class="page-head"><div @if($user->exists) class="head-identity" @endif>@if($user->exists)@include('partials.user-avatar',['name'=>$user->name,'variant'=>'large'])@endif<div><h1>{{ $user->exists?$user->name:'Nuevo usuario' }}</h1><p>@if($user->exists)<span class="status {{ $user->active?'active':'closed' }}"><i></i>{{ $user->active?'Activo':'Desactivado' }}</span> · {{ $user->email }}@else Da acceso a una persona del equipo y define qué puede hacer.@endif</p></div></div></div>
<div class="profile-stack">
<form method="POST" action="{{ $user->exists?route('users.update',$user):route('users.store') }}" class="surface form-panel" data-loading-label="Guardando…">@csrf @if($user->exists)@method('PUT')@endif
 <div class="form-section-title"><div><h2>Datos de acceso</h2><p>El correo es con el que la persona inicia sesión.</p></div></div>
 <div class="form-grid">
  <div class="field"><label class="field-label" for="field-name">Nombre *</label><input id="field-name" name="name" value="{{ old('name',$user->name) }}" required maxlength="255" autocomplete="off" placeholder="Nombre y apellido"></div>
  <div class="field"><label class="field-label" for="field-email">Correo electrónico *</label><input id="field-email" type="email" name="email" value="{{ old('email',$user->email) }}" required maxlength="255" autocomplete="off" placeholder="nombre@empresa.com"></div>
  <div class="field full"><label class="field-label" for="field-role">Rol *</label><select id="field-role" name="role" required @disabled($self)><option value="collaborator" @selected(old('role',$user->role)==='collaborator')>Colaborador · contratos, trabajadores y liquidaciones</option><option value="admin" @selected(old('role',$user->role)==='admin')>Administrador · además parámetros, catálogos, empresas y usuarios</option></select>@if($self)<input type="hidden" name="role" value="{{ $user->role }}"><small>No puedes cambiar tu propio rol.</small>@endif</div>
 </div>
 @unless($user->exists)
 <div class="form-section-title form-section-gap"><div><h2>Contraseña inicial</h2><p>Opcional. Si la dejas vacía, le enviamos un enlace para que cree la suya.</p></div></div>
 <div class="form-grid">
  <div class="field"><label class="field-label" for="field-password">Contraseña</label><input id="field-password" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres"></div>
  <div class="field"><label class="field-label" for="field-password-confirmation">Confirma la contraseña</label><input id="field-password-confirmation" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" placeholder="Repite la contraseña"></div>
 </div>
 @endunless
 <div class="form-actions"><a class="btn ghost" href="{{ route('users.index') }}" data-back>Cancelar</a><button class="btn primary">{{ $user->exists?'Guardar cambios':'Crear usuario' }} <svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#check"/></svg></button></div>
</form>
@if($user->exists && !$self)
<section class="surface form-panel">
 <div class="form-section-title"><div><h2>Acceso</h2><p>{{ $user->active ? 'Desactivar cierra sus sesiones y bloquea el inicio de sesión. Su historial de liquidaciones se conserva.' : 'La cuenta está desactivada: no puede iniciar sesión ni recuperar su contraseña.' }}</p></div></div>
 <div class="btn-group">
  @if($user->active)<form method="POST" action="{{ route('users.reset-link',$user) }}">@csrf<button class="btn" type="submit">Enviar enlace de contraseña</button></form>@endif
  <form method="POST" action="{{ route('users.toggle',$user) }}" @if($user->active) data-confirm-title="Desactivar usuario" data-confirm="{{ $user->name }} no podrá iniciar sesión y se cerrarán sus sesiones abiertas. Puedes reactivarlo después." data-confirm-action="Desactivar" @endif>@csrf @method('PATCH')<button class="btn {{ $user->active?'danger':'solid' }}" type="submit">{{ $user->active?'Desactivar usuario':'Reactivar usuario' }}</button></form>
 </div>
</section>
@endif
</div>
@endsection
