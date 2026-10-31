<!DOCTYPE html>
<html lang="es">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <title>Inicia sesión · Liquidaciones</title>
 <link rel="preload" href="{{ asset('fonts/inter-medium.ttf') }}" as="font" type="font/ttf" crossorigin>
 <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
 <script defer src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</head>
<body class="login-page">
 <main class="login-shell">
  <div class="login-form-wrap">
   <h1>Inicia sesión</h1>
   <p>Ingresa a tu espacio de gestión laboral.</p>
   @if($errors->any())<div class="flash error" role="alert">{{ $errors->first() }}</div>@endif
   <form method="POST" action="{{ route('login.submit') }}" data-loading-label="Ingresando…">
    @csrf
    <div class="field"><label class="field-label" for="field-email">Correo electrónico</label><input id="field-email" type="email" name="email" value="{{ old('email') }}" placeholder="nombre@empresa.com" autocomplete="email" required autofocus></div>
    <div class="field"><label class="field-label" for="field-password">Contraseña</label><input id="field-password" type="password" name="password" placeholder="Ingresa tu contraseña" autocomplete="current-password" required></div>
    <label class="check-label"><input type="checkbox" name="remember" value="1"> Mantener sesión iniciada</label>
    <button type="submit" class="btn primary full">Iniciar sesión</button>
   </form>
   <p class="login-tip">Acceso privado para personal autorizado.</p>
  </div>
 </main>
 <script type="application/json" id="validation-errors">@json($errors->messages())</script>
</body>
</html>
