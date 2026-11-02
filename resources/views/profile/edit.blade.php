@extends('layouts.app')
@section('title','Perfil y seguridad')
@section('breadcrumb','Cuenta / Perfil y seguridad')
@section('page-class','form-page')
@section('content')
<div class="page-head"><div class="head-identity">@include('partials.user-avatar',['name'=>$user->name,'variant'=>'large'])<div><h1>Perfil y seguridad</h1><p>{{ $user->isAdmin()?'Administrador':'Colaborador' }} · Cuenta creada el {{ $user->created_at?->translatedFormat('d M Y') }}</p></div></div></div>
<div class="profile-stack">
 <form method="POST" action="{{ route('profile.update') }}" class="surface form-panel" data-loading-label="Guardando…">@csrf @method('PUT')
  <div class="form-section-title"><div><h2>Datos personales</h2><p>Tu nombre aparece en la actividad del equipo; el correo es con el que inicias sesión.</p></div></div>
  <div class="form-grid">
   <div class="field"><label class="field-label" for="field-name">Nombre *</label><input id="field-name" name="name" value="{{ old('name',$user->name) }}" required maxlength="255" autocomplete="name"></div>
   <div class="field"><label class="field-label" for="field-email">Correo electrónico *</label><input id="field-email" type="email" name="email" value="{{ old('email',$user->email) }}" required maxlength="255" autocomplete="email"></div>
  </div>
  <div class="form-actions"><button class="btn primary">Guardar datos <svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#check"/></svg></button></div>
 </form>

 <form method="POST" action="{{ route('profile.password') }}" class="surface form-panel" data-loading-label="Actualizando…">@csrf @method('PUT')
  <div class="form-section-title"><div><h2>Contraseña</h2><p>Al menos 8 caracteres, con letras y números. Cambiarla cierra tus sesiones en otros dispositivos.</p></div></div>
  <div class="form-grid">
   <div class="field full"><label class="field-label" for="field-current-password">Contraseña actual *</label><input id="field-current-password" type="password" name="current_password" required autocomplete="current-password"></div>
   <div class="field"><label class="field-label" for="field-password">Contraseña nueva *</label><input id="field-password" type="password" name="password" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres"></div>
   <div class="field"><label class="field-label" for="field-password-confirmation">Confirma la contraseña *</label><input id="field-password-confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" placeholder="Repite la contraseña"></div>
  </div>
  <div class="form-actions"><button class="btn solid">Actualizar contraseña</button></div>
 </form>

 <section class="surface list-surface">
  <div class="surface-head"><div><h2>Sesiones activas</h2><p>Dispositivos donde tu cuenta tiene una sesión abierta.</p></div></div>
  @if($sessions->isEmpty())
  <p class="profile-empty">No hay registro de sesiones para este entorno.</p>
  @else
  <ul class="session-list">
   @foreach($sessions as $session)
   <li><span class="session-icon" aria-hidden="true"><svg class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#{{ $session->current?'check':'clock' }}"/></svg></span><div><b>{{ $session->device }}</b><small>{{ $session->ip ?: 'IP desconocida' }} · @if($session->current)<span class="session-current">Este dispositivo</span>@else activa {{ $session->active->diffForHumans() }}@endif</small></div></li>
   @endforeach
  </ul>
  @endif
  @if($sessions->where('current', false)->isNotEmpty())
  <form method="POST" action="{{ route('profile.sessions') }}" class="session-close" data-loading-label="Cerrando…">@csrf @method('DELETE')
   <div class="field"><label class="field-label" for="field-confirm-password">Confirma con tu contraseña</label><input id="field-confirm-password" type="password" name="confirm_password" required autocomplete="current-password"></div>
   <button class="btn danger" type="submit">Cerrar otras sesiones</button>
  </form>
  @endif
 </section>
</div>
@endsection
