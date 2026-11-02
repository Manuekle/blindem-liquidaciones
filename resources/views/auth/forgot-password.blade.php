@extends('layouts.guest')
@section('title','Recupera tu contraseña')
@section('heading','Recupera tu contraseña')
@section('lede','Escribe el correo de tu cuenta y te enviaremos un enlace para crear una contraseña nueva.')
@section('content')
<form method="POST" action="{{ route('password.email') }}" data-loading-label="Enviando…">
 @csrf
 <div class="field"><label class="field-label" for="field-email">Correo electrónico</label><input id="field-email" type="email" name="email" value="{{ old('email') }}" placeholder="nombre@empresa.com" autocomplete="email" required autofocus></div>
 <button type="submit" class="btn solid full">Enviar enlace</button>
</form>
@if(config('mail.default') === 'log' && app()->isLocal())<p class="login-tip">En local el correo no se envía: el enlace queda en el log de Laravel ({{ config('logging.default') === 'stderr' ? 'docker compose logs app' : 'storage/logs/laravel.log' }}).</p>@endif
<a class="login-back" href="{{ route('login') }}"><svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#arrow-left"/></svg> Volver a iniciar sesión</a>
@endsection
