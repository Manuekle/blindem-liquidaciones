{{-- Shared shell for HTTP error pages: standalone (no session or auth needed), same card and halftone as the login. --}}
<!DOCTYPE html>
<html lang="es">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <link rel="icon" href="/favicon.svg" type="image/svg+xml">
 <title>@yield('title') · Liquidaciones</title>
 @include('partials.theme-script')
 <link rel="preconnect" href="https://fonts.googleapis.com">
 <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
 <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600&family=Geist+Mono:wght@400;500;600&family=SN+Pro:wght@500;600&display=swap">
 <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
 <script defer src="{{ asset('js/halftone.js') }}?v={{ filemtime(public_path('js/halftone.js')) }}"></script>
</head>
<body class="login-page error-page">
 <main class="login-shell">
  <section class="login-form-wrap error-body">
   <a class="login-brand" href="{{ url('/') }}"><span class="brand-mark" aria-hidden="true"><svg class="logo-mark" focusable="false" viewBox="0 0 697 462"><use href="{{ asset('icons/hugeicons.svg') }}#logo"/></svg></span><span><b>Liquidaciones</b><small>Gestión laboral · Colombia</small></span></a>
   <p class="error-code">Error @yield('code')</p>
   <h1>@yield('heading')</h1>
   <p class="login-lede">@yield('message')</p>
   <div class="error-actions">
    <a class="btn solid" href="{{ url('/') }}">Ir al resumen</a>
    <button type="button" class="btn ghost" onclick="history.length > 1 ? history.back() : location.assign('{{ url('/') }}')">Volver atrás</button>
   </div>
   @hasSection('hint')<p class="login-tip">@yield('hint')</p>@endif
  </section>
  <div class="login-art" aria-hidden="true">
   <canvas data-halftone></canvas>
   <div class="error-figure">@yield('code')</div>
  </div>
 </main>
</body>
</html>
