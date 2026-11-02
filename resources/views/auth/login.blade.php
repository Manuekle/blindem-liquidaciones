@extends('layouts.guest')
@section('title','Inicia sesión')
@section('heading','Inicia sesión')
@section('lede','Continúa para acceder a tu espacio de gestión laboral.')
@section('content')
<form method="POST" action="{{ route('login.submit') }}" data-loading-label="Ingresando…">
 @csrf
 <div class="field"><label class="field-label" for="field-email">Correo electrónico</label><input id="field-email" type="email" name="email" value="{{ old('email') }}" placeholder="nombre@empresa.com" autocomplete="email" required autofocus></div>
 <div class="field"><label class="field-label" for="field-password">Contraseña</label><input id="field-password" type="password" name="password" placeholder="Ingresa tu contraseña" autocomplete="current-password" required></div>
 <div class="login-row"><label class="check-label"><input type="checkbox" name="remember" value="1"> Mantener sesión iniciada</label><a class="login-link" href="{{ route('password.request') }}">¿Olvidaste tu contraseña?</a></div>
 <button type="submit" class="btn solid full">Iniciar sesión</button>
</form>
<p class="login-tip">Acceso privado para personal autorizado.</p>
@endsection
