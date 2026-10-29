<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><title>@yield('title','Inicio') · Blindem</title><link rel="stylesheet" href="{{ asset('css/app.css') }}"><script defer src="{{ asset('js/app.js') }}"></script></head>
<body>
<svg class="svg-defs" xmlns="http://www.w3.org/2000/svg"><defs>
<symbol id="i-home" viewBox="0 0 24 24"><path d="m3 10 9-7 9 7v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 21v-8h6v8"/></symbol>
<symbol id="i-users" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></symbol>
<symbol id="i-building" viewBox="0 0 24 24"><rect x="3" y="2" width="18" height="20" rx="2"/><path d="M8 6h.01M16 6h.01M8 11h.01M16 11h.01M8 16h.01M16 16h.01M10 22v-4h4v4"/></symbol>
<symbol id="i-file" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/></symbol>
<symbol id="i-chart" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="m8 16 4-5 4 2 4-7"/></symbol>
<symbol id="i-settings" viewBox="0 0 24 24"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.89l.07.07-2 2-.08-.07A1.7 1.7 0 0 0 16 18.6l-1 .4V21h-3v-2l-1-.4a1.7 1.7 0 0 0-1.7.3l-.08.07-2-2 .07-.08a1.7 1.7 0 0 0 .31-1.7l-.4-1H5v-3h2.2l.4-1a1.7 1.7 0 0 0-.3-1.7l-.08-.08 2-2 .08.08a1.7 1.7 0 0 0 1.7.3l1-.4V4h3v2.2l1 .4a1.7 1.7 0 0 0 1.7-.3l.08-.08 2 2-.08.08A1.7 1.7 0 0 0 19.4 10l.4 1H22v3h-2.2z" transform="translate(-1 -1) scale(.96)"/></symbol>
<symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></symbol>
<symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
<symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
<symbol id="i-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
<symbol id="i-download" viewBox="0 0 24 24"><path d="M12 3v12m-5-5 5 5 5-5M4 17v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></symbol>
<symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
<symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12 5 5L20 7"/></symbol>
<symbol id="i-wallet" viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="15" rx="2"/><path d="M3 10h18M6 3h12"/></symbol>
<symbol id="i-bell" viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></symbol>
<symbol id="i-logout" viewBox="0 0 24 24"><path d="M10 17l5-5-5-5M15 12H3M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/></symbol>
<symbol id="i-shield" viewBox="0 0 24 24"><path d="m12 22 7-4V6l-7-4-7 4v12z"/><path d="m9 12 2 2 4-4"/></symbol>
<symbol id="i-filter" viewBox="0 0 24 24"><path d="M4 5h16L14 12v6l-4 2v-8z"/></symbol>
<symbol id="i-edit" viewBox="0 0 24 24"><path d="M12 20H4v-8L16 0l8 8-12 12z" transform="translate(1 2) scale(.83)"/><path d="m13 6 5 5"/></symbol>
<symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
<symbol id="i-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></symbol>
</defs></svg>
<div class="app-shell"><aside class="sidebar" id="sidebar">
 <a href="{{ route('dashboard') }}" class="brand"><span class="brand-mark"><span></span><span></span><span></span><span></span></span><span>blindem<span class="brand-dot">.</span></span></a>
 <div class="nav-caption">ESPACIO DE TRABAJO</div>
 <nav class="nav-links">
 <a class="nav-link {{ request()->routeIs('dashboard')?'active':'' }}" href="{{ route('dashboard') }}"><svg><use href="#i-home"/></svg> Resumen</a>
 <a class="nav-link {{ request()->routeIs('contracts.*','settlements.*')?'active':'' }}" href="{{ route('contracts.index') }}"><svg><use href="#i-file"/></svg> Contratos <span class="nav-counter">{{ \App\Models\Contract::count() }}</span></a>
 <a class="nav-link {{ request()->routeIs('workers.*')?'active':'' }}" href="{{ route('workers.index') }}"><svg><use href="#i-users"/></svg> Trabajadores</a>
 <a class="nav-link {{ request()->routeIs('companies.*')?'active':'' }}" href="{{ route('companies.index') }}"><svg><use href="#i-building"/></svg> Empresas</a>
 </nav>
 @if(auth()->user()->isAdmin())
 <div class="nav-caption nav-caption-second">ADMINISTRACIÓN</div>
 <nav class="nav-links">
 <a class="nav-link {{ request()->is('catalogos/documentos')?'active':'' }}" href="{{ route('catalogs.index','documentos') }}"><svg><use href="#i-settings"/></svg> Documentos</a>
 <a class="nav-link {{ request()->is('catalogos/cargos')?'active':'' }}" href="{{ route('catalogs.index','cargos') }}"><svg><use href="#i-settings"/></svg> Cargos</a>
 <a class="nav-link {{ request()->is('catalogos/contratos')?'active':'' }}" href="{{ route('catalogs.index','contratos') }}"><svg><use href="#i-settings"/></svg> Tipos de contrato</a>
 <a class="nav-link {{ request()->is('catalogos/retiros')?'active':'' }}" href="{{ route('catalogs.index','retiros') }}"><svg><use href="#i-settings"/></svg> Motivos de retiro</a>
 </nav>@endif
 <div class="sidebar-bottom"><div class="support-card"><div class="support-icon"><svg><use href="#i-shield"/></svg></div><b>Liquidaciones seguras</b><small>Cálculos con historial, trazabilidad y exportación a Excel.</small><span class="support-pill"><i class="live-dot"></i> Motor v1.0</span></div>
 <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-link logout" type="submit"><svg><use href="#i-logout"/></svg> Cerrar sesión</button></form></div>
 </aside><div class="main-area"><header class="topbar"><div class="top-left"><button id="mobile-menu" class="icon-button mobile-only" type="button" aria-label="Abrir menú"><svg><use href="#i-menu"/></svg></button><div class="breadcrumb">Workspace <span>/</span> <strong>@yield('breadcrumb','Resumen')</strong></div></div><div class="top-actions"><span class="top-date"><svg><use href="#i-calendar"/></svg> {{ now()->translatedFormat('d M Y') }}</span><span class="icon-button" title="Sistema operativo"><svg><use href="#i-bell"/></svg></span><span class="user-chip"><span class="avatar">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</span><span><b>{{ auth()->user()->name }}</b><small>{{ auth()->user()->isAdmin()?'Administrador':'Colaborador' }}</small></span><svg><use href="#i-down"/></svg></span></div></header>
 <main class="page-content">@if(session('success'))<div class="flash success"><svg><use href="#i-check"/></svg>{{ session('success') }}</div>@endif @if(session('error'))<div class="flash error">{{ session('error') }}</div>@endif @if($errors->any())<div class="flash error"><b>Revisa los siguientes campos:</b><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
 @yield('content')</main><footer class="app-footer"><span>© {{ date('Y') }} Blindem · Gestión laboral</span><span>Construido con Laravel · Colombia</span></footer></div></div>
</body></html>
