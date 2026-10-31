<!DOCTYPE html>
<html lang="es">
<head>
 <meta charset="utf-8">
 <meta name="viewport" content="width=device-width,initial-scale=1">
 <meta name="csrf-token" content="{{ csrf_token() }}">
 <title>@yield('title','Resumen') · Liquidaciones</title>
 <link rel="preload" href="{{ asset('fonts/inter-medium.ttf') }}" as="font" type="font/ttf" crossorigin>
 <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
 <script defer src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</head>
<body>
<a class="skip-link" href="#main-content">Ir al contenido</a>
<div class="app-shell">
 <aside class="sidebar" id="sidebar" aria-label="Navegación principal">
  <a href="{{ route('dashboard') }}" class="workspace-name">Liquidaciones</a>
  <div class="nav-caption">Espacio de trabajo</div>
  <nav class="nav-links" aria-label="Gestión laboral">
   <a class="nav-link {{ request()->routeIs('dashboard')?'active':'' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif href="{{ route('dashboard') }}"><svg aria-hidden="true" class="lucide" focusable="false" viewBox="0 0 24 24"><use href="{{ asset('icons/lucide.svg') }}#house"/></svg> Resumen</a>
   <a class="nav-link {{ request()->routeIs('contracts.*','settlements.*')?'active':'' }}" @if(request()->routeIs('contracts.*','settlements.*')) aria-current="page" @endif href="{{ route('contracts.index') }}"><svg aria-hidden="true" class="lucide" focusable="false" viewBox="0 0 24 24"><use href="{{ asset('icons/lucide.svg') }}#file-text"/></svg> Contratos <span class="nav-counter">{{ \App\Models\Contract::count() }}</span></a>
   <a class="nav-link {{ request()->routeIs('workers.*')?'active':'' }}" @if(request()->routeIs('workers.*')) aria-current="page" @endif href="{{ route('workers.index') }}"><svg aria-hidden="true" class="lucide" focusable="false" viewBox="0 0 24 24"><use href="{{ asset('icons/lucide.svg') }}#users-round"/></svg> Trabajadores</a>
   <a class="nav-link {{ request()->routeIs('companies.*')?'active':'' }}" @if(request()->routeIs('companies.*')) aria-current="page" @endif href="{{ route('companies.index') }}"><svg aria-hidden="true" class="lucide" focusable="false" viewBox="0 0 24 24"><use href="{{ asset('icons/lucide.svg') }}#building-2"/></svg> Empresas</a>
  </nav>
  @if(auth()->user()->isAdmin())
  <div class="nav-caption nav-caption-second">Administración</div>
  <nav class="nav-links" aria-label="Administración">
   <a class="nav-link {{ request()->routeIs('labor-parameters.*')?'active':'' }}" href="{{ route('labor-parameters.index') }}"><svg aria-hidden="true" class="lucide" focusable="false" viewBox="0 0 24 24"><use href="{{ asset('icons/lucide.svg') }}#calendar-days"/></svg> Parámetros laborales</a>
   @foreach(['documentos'=>'Documentos','cargos'=>'Cargos','contratos'=>'Tipos de contrato','retiros'=>'Motivos de retiro'] as $catalogType => $catalogLabel)
   <a class="nav-link {{ request()->is('catalogos/'.$catalogType)?'active':'' }}" @if(request()->is('catalogos/'.$catalogType)) aria-current="page" @endif href="{{ route('catalogs.index',$catalogType) }}"><svg aria-hidden="true" class="lucide" focusable="false" viewBox="0 0 24 24"><use href="{{ asset('icons/lucide.svg') }}#settings-2"/></svg> {{ $catalogLabel }}</a>
   @endforeach
  </nav>
  @endif
  <div class="sidebar-bottom">
   <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link logout" type="submit"><svg aria-hidden="true" class="lucide" focusable="false" viewBox="0 0 24 24"><use href="{{ asset('icons/lucide.svg') }}#log-out"/></svg> Cerrar sesión</button></form>
  </div>
 </aside>
 <button class="sidebar-backdrop" id="sidebar-backdrop" type="button" aria-label="Cerrar menú" hidden></button>
 <div class="main-area @yield('page-class')">
  <header class="topbar"><div class="topbar-inner">
   <div class="top-left">
    <button id="mobile-menu" class="icon-button mobile-only" type="button" aria-label="Abrir menú" aria-controls="sidebar" aria-expanded="false"><svg aria-hidden="true" class="lucide" focusable="false" viewBox="0 0 24 24"><use href="{{ asset('icons/lucide.svg') }}#menu"/></svg></button>
    <div class="breadcrumb"><span class="breadcrumb-label">Espacio de trabajo <span aria-hidden="true">/</span></span><strong>@yield('breadcrumb','Resumen')</strong></div>
   </div>
   <div class="top-actions">
    <span class="top-date"><svg aria-hidden="true" class="lucide" focusable="false" viewBox="0 0 24 24"><use href="{{ asset('icons/lucide.svg') }}#calendar-days"/></svg> {{ now()->translatedFormat('d M Y') }}</span>
    <div class="user-chip"><b>{{ auth()->user()->name }}</b><small>{{ auth()->user()->isAdmin()?'Administrador':'Colaborador' }}</small></div>
   </div>
  </div></header>
  <main class="page-content @yield('page-class')" id="main-content" tabindex="-1">
   @if(session('success'))<div class="flash success" role="status"><svg aria-hidden="true" class="lucide" focusable="false" viewBox="0 0 24 24"><use href="{{ asset('icons/lucide.svg') }}#check"/></svg>{{ session('success') }}</div>@endif
   @if(session('error'))<div class="flash error" role="alert">{{ session('error') }}</div>@endif
   @if($errors->any())<div class="flash error" role="alert"><b>Revisa los siguientes campos:</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
   @yield('content')
  </main>
  <footer class="app-footer"><span>Gestión laboral</span><span>Valores en pesos colombianos</span></footer>
 </div>
</div>
<script type="application/json" id="validation-errors">@json($errors->messages())</script>
</body>
</html>
