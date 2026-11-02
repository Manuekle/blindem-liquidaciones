<!DOCTYPE html>
<html lang="es">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <link rel="icon" href="/favicon.svg" type="image/svg+xml">
 <title>@yield('title') · Blindem Liquidaciones</title>
 {{-- Only the sign-in page is indexable; password recovery screens carry tokens or add nothing to search. --}}
 @include('partials.seo', ['robots' => request()->routeIs('login') ? null : 'noindex, nofollow'])
 @include('partials.theme-script')
 @include('partials.page-transition-script')
 <link rel="preconnect" href="https://fonts.googleapis.com">
 <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
 <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600&family=Geist+Mono:wght@400;500;600&family=SN+Pro:wght@500;600&display=swap">
 <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
 <script defer src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
 <script defer src="{{ asset('js/ui.js') }}?v={{ filemtime(public_path('js/ui.js')) }}"></script>
 <script defer src="{{ asset('js/controls.js') }}?v={{ filemtime(public_path('js/controls.js')) }}"></script>
 <script defer src="{{ asset('js/halftone.js') }}?v={{ filemtime(public_path('js/halftone.js')) }}"></script>
 <script type="module" src="{{ asset('js/validation.js') }}?v={{ filemtime(public_path('js/validation.js')) }}"></script>
 @include('partials.offline-script')
</head>
<body class="login-page">
 <main class="login-shell">
  <section class="login-form-wrap">
   <a class="login-brand" href="{{ route('login') }}"><span class="brand-mark" aria-hidden="true"><svg class="logo-mark" focusable="false" viewBox="0 0 697 462"><use href="{{ $iconSprite }}#logo"/></svg></span><span><b>Liquidaciones</b><small>Gestión laboral · Colombia</small></span></a>
   <h1>@yield('heading')</h1>
   <p class="login-lede">@yield('lede')</p>
   @if(session('status'))<div class="flash success" role="status">{{ session('status') }}</div>@endif
   <noscript>@if($errors->any())<div class="flash error" role="alert">{{ $errors->first() }}</div>@endif</noscript>
   @yield('content')
  </section>
  <div class="login-art" aria-hidden="true">
   <canvas data-halftone></canvas>
   <div class="login-art-caption"><span>Prestaciones · Vacaciones · Indemnización</span><span>Colombia</span></div>
  </div>
 </main>
 <script type="application/json" id="validation-errors">@json($errors->messages())</script>
</body>
</html>
