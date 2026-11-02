@extends('layouts.guest')
@section('title','Nueva contraseña')
@section('heading','Crea una contraseña nueva')
@section('lede','Usa al menos 8 caracteres, con letras y números. Al guardarla se cerrarán tus otras sesiones.')
@section('content')
<form method="POST" action="{{ route('password.update') }}" data-loading-label="Guardando…">
 @csrf
 <input type="hidden" name="token" value="{{ $token }}">
 <div class="field"><label class="field-label" for="field-email">Correo electrónico</label><input id="field-email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required @if(!old('email', $email)) autofocus @endif></div>
 <div class="field"><label class="field-label" for="field-password">Contraseña nueva</label><input id="field-password" type="password" name="password" placeholder="Mínimo 8 caracteres" autocomplete="new-password" minlength="8" required @if(old('email', $email)) autofocus @endif></div>
 <div class="field"><label class="field-label" for="field-password-confirmation">Confirma la contraseña</label><input id="field-password-confirmation" type="password" name="password_confirmation" placeholder="Repite la contraseña" autocomplete="new-password" minlength="8" required></div>
 <button type="submit" class="btn solid full">Guardar contraseña</button>
</form>
<a class="login-back" href="{{ route('login') }}"><svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#arrow-left"/></svg> Volver a iniciar sesión</a>
@endsection
