<!DOCTYPE html>
<html lang="es">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <link rel="icon" href="/favicon.svg" type="image/svg+xml">
 <meta name="csrf-token" content="{{ csrf_token() }}">
 <title>@yield('title','Resumen') · Liquidaciones</title>
 @include('partials.theme-script')
 @include('partials.page-transition-script')
 {{-- Runs before first paint so the saved sidebar width and collapsed state never jump. --}}
 <script>
 (() => {
  const root = document.documentElement;
  try {
   const width = parseInt(localStorage.getItem('sidebar-width'), 10);
   if (width >= 220 && width <= 400) root.style.setProperty('--sidebar-w', width + 'px');
   if (localStorage.getItem('sidebar-collapsed') === '1') root.classList.add('sidebar-collapsed');
  } catch (error) {}
 })();
 </script>
 <link rel="preconnect" href="https://fonts.googleapis.com">
 <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
 <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;500;600&family=Geist+Mono:wght@400;500;600&family=SN+Pro:wght@500;600&display=swap">
 <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
 <script defer src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
 <script defer src="{{ asset('js/ui.js') }}?v={{ filemtime(public_path('js/ui.js')) }}"></script>
 <script defer src="{{ asset('js/controls.js') }}?v={{ filemtime(public_path('js/controls.js')) }}"></script>
 <script type="module" src="{{ asset('js/validation.js') }}?v={{ filemtime(public_path('js/validation.js')) }}"></script>
 <script defer src="{{ asset('js/ai.js') }}?v={{ filemtime(public_path('js/ai.js')) }}"></script>
 <script defer src="{{ asset('js/nav.js') }}?v={{ filemtime(public_path('js/nav.js')) }}"></script>
 @include('partials.offline-script')
 <script defer src="{{ asset('js/command.js') }}?v={{ filemtime(public_path('js/command.js')) }}"></script>
 <script defer src="{{ asset('js/guided.js') }}?v={{ filemtime(public_path('js/guided.js')) }}"></script>
 <link rel="stylesheet" href="{{ asset('css/blobatar-motion.css') }}">
 <script type="module" src="{{ asset('js/sounds.js') }}?v={{ filemtime(public_path('js/sounds.js')) }}"></script>
 <script type="module" src="{{ asset('js/agent.js') }}?v={{ filemtime(public_path('js/agent.js')) }}"></script>
</head>
<body>
<a class="skip-link" href="#main-content">Ir al contenido</a>
<div class="app-shell">
 <aside class="sidebar" id="sidebar" aria-label="Navegación principal">
  @php
   $user = auth()->user();
   // Sidebar sections: the active one carries the indicator, which View Transitions slide from link to link.
   $pendingSettlements = \App\Models\Contract::where('status', 'terminated')->doesntHave('settlements')->count();
   $sections = [
    ['label' => 'Resumen', 'icon' => 'house', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
    ['label' => 'Contratos', 'icon' => 'file-text', 'href' => route('contracts.index'), 'active' => request()->routeIs('contracts.*', 'settlements.*'), 'count' => \App\Models\Contract::count(), 'alert' => $pendingSettlements ? $pendingSettlements.' sin liquidar' : null],
    ['label' => 'Trabajadores', 'icon' => 'users-round', 'href' => route('workers.index'), 'active' => request()->routeIs('workers.*'), 'count' => \App\Models\Worker::count()],
    ['label' => 'Empresas', 'icon' => 'building-2', 'href' => route('companies.index'), 'active' => request()->routeIs('companies.*'), 'count' => \App\Models\Company::count()],
   ];
   $catalogs = ['documentos' => 'Documentos', 'cargos' => 'Cargos', 'contratos' => 'Tipos de contrato', 'retiros' => 'Motivos de retiro'];
   $inCatalogs = request()->routeIs('catalogs.*');
  @endphp
  <div class="sidebar-head">
   <a href="{{ route('dashboard') }}" class="workspace-name" aria-label="Liquidaciones · Resumen"><span class="brand-mark" aria-hidden="true"><svg class="logo-mark" focusable="false" viewBox="0 0 697 462"><use href="{{ $iconSprite }}#logo"/></svg></span><span class="brand-text">Liquidaciones<small>Gestión laboral · Colombia</small></span></a>
   <button id="sidebar-collapse" class="sidebar-collapse" type="button" aria-controls="sidebar" aria-expanded="true" aria-label="Contraer menú" title="Contraer menú" aria-keyshortcuts="Meta+B Control+B"><svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#sidebar-left"/></svg></button>
  </div>
  <div class="nav-caption">Espacio de trabajo</div>
  <nav class="nav-links" aria-label="Gestión laboral">
   @foreach($sections as $section)
   <a class="nav-link {{ $section['active']?'active':'' }}" @if($section['active']) aria-current="page" @endif href="{{ $section['href'] }}">@if($section['active'])<span class="nav-indicator" aria-hidden="true"></span>@endif<svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#{{ $section['icon'] }}"/></svg> <span class="nav-label">{{ $section['label'] }}</span>@if(!empty($section['alert']))<span class="nav-alert" title="{{ $section['alert'] }}"><span class="sr-only">{{ $section['alert'] }}</span></span>@endif @isset($section['count'])<span class="nav-counter">{{ $section['count'] }}</span>@endisset</a>
   @endforeach
  </nav>
  @if($user->isAdmin())
  <div class="nav-caption nav-caption-second">Administración</div>
  <nav class="nav-links" aria-label="Administración">
   <a class="nav-link {{ request()->routeIs('labor-parameters.*')?'active':'' }}" @if(request()->routeIs('labor-parameters.*')) aria-current="page" @endif href="{{ route('labor-parameters.index') }}">@if(request()->routeIs('labor-parameters.*'))<span class="nav-indicator" aria-hidden="true"></span>@endif<svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#calendar-days"/></svg> <span class="nav-label">Parámetros laborales</span></a>
   <a class="nav-link {{ request()->routeIs('users.*')?'active':'' }}" @if(request()->routeIs('users.*')) aria-current="page" @endif href="{{ route('users.index') }}">@if(request()->routeIs('users.*'))<span class="nav-indicator" aria-hidden="true"></span>@endif<svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#user"/></svg> <span class="nav-label">Usuarios</span></a>
   <details class="nav-group" @if($inCatalogs) open @endif>
    <summary class="nav-link {{ $inCatalogs?'is-within':'' }}"><svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#database"/></svg> <span class="nav-label">Catálogos</span><svg aria-hidden="true" class="icon nav-chevron" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#chevron-down"/></svg></summary>
    <div class="nav-sub">
     @foreach($catalogs as $catalogType => $catalogLabel)
     @php $current = request()->is('catalogos/'.$catalogType); @endphp
     <a class="nav-link {{ $current?'active':'' }}" @if($current) aria-current="page" @endif href="{{ route('catalogs.index',$catalogType) }}">@if($current)<span class="nav-indicator" aria-hidden="true"></span>@endif<span class="nav-label">{{ $catalogLabel }}</span></a>
     @endforeach
    </div>
   </details>
  </nav>
  @endif
  <div class="sidebar-bottom account">
   <button id="account-trigger" class="account-trigger" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="account-panel">@include('partials.user-avatar',['name'=>$user->name])<span class="account-text"><b>{{ $user->name }}</b><small>{{ $user->isAdmin()?'Administrador':'Colaborador' }}</small></span><svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#unfold"/></svg></button>
   <div id="account-panel" class="account-panel t-dropdown" role="group" aria-label="Cuenta y preferencias">
   <div class="account-head"><b>{{ $user->name }}</b><small>{{ $user->email }}</small></div>
   <a class="nav-link {{ request()->routeIs('profile.*')?'active':'' }}" href="{{ route('profile.edit') }}"><svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#user"/></svg> Perfil y seguridad</a>
   <button id="theme-toggle" class="nav-link theme-toggle" type="button" aria-pressed="false"><span class="t-icon-swap" data-state="a"><svg aria-hidden="true" class="icon t-icon" data-icon="a" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}?v={{ filemtime(public_path('icons/hugeicons.svg')) }}#sun"/></svg><svg aria-hidden="true" class="icon t-icon" data-icon="b" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}?v={{ filemtime(public_path('icons/hugeicons.svg')) }}#moon"/></svg></span>Modo oscuro<span class="theme-switch" aria-hidden="true"></span></button>
   <button id="sound-toggle" class="nav-link theme-toggle" type="button" aria-pressed="true"><span class="t-icon-swap" data-state="a"><svg aria-hidden="true" class="icon t-icon" data-icon="a" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}?v={{ filemtime(public_path('icons/hugeicons.svg')) }}#volume"/></svg><svg aria-hidden="true" class="icon t-icon" data-icon="b" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}?v={{ filemtime(public_path('icons/hugeicons.svg')) }}#volume-off"/></svg></span>Sonido<span class="theme-switch" aria-hidden="true"></span></button>
   <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link logout" type="submit"><svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#log-out"/></svg> Cerrar sesión</button></form>
   </div>
  </div>
 </aside>
 <div id="sidebar-resizer" class="sidebar-resizer" role="separator" tabindex="0" aria-orientation="vertical" aria-controls="sidebar" aria-label="Ancho del menú" aria-valuemin="220" aria-valuemax="400" aria-valuenow="264" title="Arrastra para ajustar · doble clic para restablecer"></div>
 <button class="sidebar-backdrop" id="sidebar-backdrop" type="button" aria-label="Cerrar menú" hidden></button>
 <div class="main-area @yield('page-class')">
  <header class="topbar"><div class="topbar-inner">
   <div class="top-left">
    <button id="mobile-menu" class="icon-button mobile-only" type="button" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false"><svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#menu"/></svg></button>
    @php
     // "Contratos / #12" → linked section + current page; sections without a page of their own stay plain text.
     $crumbs = explode(' / ', html_entity_decode(trim($__env->yieldContent('breadcrumb', 'Resumen')), ENT_QUOTES));
     $crumbLinks = ['Contratos' => route('contracts.index'), 'Trabajadores' => route('workers.index'), 'Empresas' => route('companies.index'), 'Usuarios' => auth()->user()->isAdmin() ? route('users.index') : null];
    @endphp
    <nav class="breadcrumb" aria-label="Ubicación"><a class="crumb-home" href="{{ route('dashboard') }}" aria-label="Resumen"><svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#house"/></svg></a>@if($crumbs !== ['Resumen'])@foreach($crumbs as $crumb)<span class="crumb-sep" aria-hidden="true">/</span>@if($loop->last)<strong aria-current="page">{{ $crumb }}</strong>@elseif(isset($crumbLinks[$crumb]))<a class="crumb-link" href="{{ $crumbLinks[$crumb] }}">{{ $crumb }}</a>@else<span class="crumb-text">{{ $crumb }}</span>@endif @endforeach @else<span class="crumb-sep" aria-hidden="true">/</span><strong aria-current="page">Resumen</strong>@endif</nav>
   </div>
   <div class="top-actions">
    <button type="button" class="command-trigger" data-command-open aria-haspopup="dialog" aria-controls="command" aria-keyshortcuts="Meta+K Control+K"><svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#search"/></svg><span>Buscar o ir a…</span><span class="command-trigger-keys" aria-hidden="true"><kbd data-mod>⌘</kbd><kbd>K</kbd></span></button>
    <span class="top-date"><svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#calendar-days"/></svg> {{ now()->translatedFormat('d M Y') }}</span>
    <div class="notif">
     <button id="notif-trigger" class="icon-button notif-trigger" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="notif-panel" data-count="{{ $unreadNotifications ?? 0 }}" aria-label="Notificaciones"><svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#bell"/></svg><span class="notif-badge" @if(!($unreadNotifications ?? 0)) hidden @endif>{{ ($unreadNotifications ?? 0) > 9 ? '9+' : ($unreadNotifications ?? 0) }}</span></button>
     <section id="notif-panel" class="notif-panel t-dropdown" role="dialog" aria-label="Notificaciones" data-url="{{ route('notifications.index') }}" hidden>
      <header class="notif-head"><h2>Notificaciones</h2><button type="button" class="notif-read-all" data-notif-read-all data-url="{{ route('notifications.read-all') }}"><svg aria-hidden="true" class="icon" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#check-check"/></svg> Marcar todo leído</button></header>
      <div class="notif-tabs" role="tablist" aria-label="Filtrar notificaciones"><button type="button" role="tab" data-notif-filter="all" aria-selected="true">Todas</button><button type="button" role="tab" data-notif-filter="unread" aria-selected="false">Sin leer</button><button type="button" role="tab" data-notif-filter="alert" aria-selected="false">Pendientes</button></div>
      <ul class="notif-list" aria-live="polite"></ul>
     </section>
    </div>
   </div>
  </div></header>
  <div class="main-scroll" id="main-scroll">
  <main class="page-content @yield('page-class')" id="main-content" tabindex="-1">
   @php $flashToasts = array_values(array_filter([session('success') ? ['type' => 'success', 'title' => session('success')] : null, session('error') ? ['type' => 'error', 'title' => session('error')] : null])); @endphp
   @if($flashToasts)<script type="application/json" id="flash-toasts">@json($flashToasts)</script>@endif
   <noscript>
    @if(session('success'))<div class="flash success" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="flash error" role="alert">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="flash error" role="alert"><b>Revisa los siguientes campos:</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
   </noscript>
   @yield('content')
  </main>
  <footer class="app-footer"><span>Gestión laboral</span><span>Valores en pesos colombianos</span></footer>
  </div>
 </div>
</div>
@include('partials.agent')
@include('partials.command')
<script type="application/json" id="validation-errors">@json($errors->messages())</script>
</body>
</html>
