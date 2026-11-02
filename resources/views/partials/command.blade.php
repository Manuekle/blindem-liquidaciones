@php
 // Everything the palette (⌘K) can open or do. `keys` is the two-key sequence that runs it from anywhere (G C = go to
 // contracts); command.js reads this list, so shortcuts and palette never disagree.
 $isAdmin = auth()->user()->isAdmin();
 $routeContract = request()->route()?->parameter('contract');
 $commands = [
  ['group' => 'Ir a', 'title' => 'Resumen', 'href' => route('dashboard'), 'icon' => 'house', 'keys' => 'G R', 'words' => 'inicio dashboard pendientes'],
  ['group' => 'Ir a', 'title' => 'Contratos', 'href' => route('contracts.index'), 'icon' => 'file-text', 'keys' => 'G C', 'words' => 'liquidaciones vinculos'],
  ['group' => 'Ir a', 'title' => 'Trabajadores', 'href' => route('workers.index'), 'icon' => 'users-round', 'keys' => 'G T', 'words' => 'personas empleados'],
  ['group' => 'Ir a', 'title' => 'Empresas', 'href' => route('companies.index'), 'icon' => 'building-2', 'keys' => 'G E', 'words' => 'empleadores nit'],
  ['group' => 'Ir a', 'title' => 'Perfil y seguridad', 'href' => route('profile.edit'), 'icon' => 'user', 'words' => 'cuenta contrasena clave ajustes configuracion sesiones correo'],
 ];
 if ($isAdmin) {
  $commands[] = ['group' => 'Ir a', 'title' => 'Usuarios', 'href' => route('users.index'), 'icon' => 'user', 'keys' => 'G U', 'words' => 'equipo accesos cuentas roles administradores colaboradores'];
  $commands[] = ['group' => 'Ir a', 'title' => 'Parámetros laborales', 'href' => route('labor-parameters.index'), 'icon' => 'calendar-days', 'keys' => 'G P', 'words' => 'salario minimo auxilio transporte vigencia'];
  foreach (['documentos' => 'Documentos', 'cargos' => 'Cargos', 'contratos' => 'Tipos de contrato', 'retiros' => 'Motivos de retiro'] as $type => $label) {
   $commands[] = ['group' => 'Ir a', 'title' => $label, 'detail' => 'Catálogo', 'href' => route('catalogs.index', $type), 'icon' => 'settings-2', 'words' => 'catalogo administracion'];
  }
 }
 if ($routeContract instanceof \App\Models\Contract) {
  $commands[] = ['group' => 'Este contrato', 'title' => 'Ver liquidación', 'href' => route('settlements.show', $routeContract), 'icon' => 'wallet', 'keys' => 'L', 'words' => 'liquidar calcular'];
  $commands[] = ['group' => 'Este contrato', 'title' => 'Editar contrato', 'href' => route('contracts.edit', $routeContract), 'icon' => 'pencil-line', 'keys' => 'E', 'words' => 'modificar cambiar'];
  $commands[] = ['group' => 'Este contrato', 'title' => 'Descargar liquidación en Excel', 'href' => route('settlements.export', $routeContract), 'icon' => 'download', 'words' => 'exportar xlsx'];
 }
 $commands[] = ['group' => 'Crear', 'title' => 'Nuevo contrato', 'detail' => 'Conversación guiada', 'href' => route('contracts.create'), 'icon' => 'plus', 'keys' => 'N C', 'words' => 'crear registrar agregar'];
 $commands[] = ['group' => 'Crear', 'title' => 'Nuevo trabajador', 'detail' => 'Conversación guiada', 'href' => route('workers.create'), 'icon' => 'plus', 'keys' => 'N T', 'words' => 'crear registrar persona empleado'];
 if ($isAdmin) {
  $commands[] = ['group' => 'Crear', 'title' => 'Nueva empresa', 'detail' => 'Conversación guiada', 'href' => route('companies.create'), 'icon' => 'plus', 'keys' => 'N E', 'words' => 'crear registrar empleador'];
  $commands[] = ['group' => 'Crear', 'title' => 'Nuevo usuario', 'href' => route('users.create'), 'icon' => 'plus', 'words' => 'crear invitar acceso cuenta equipo'];
 }
 $commands[] = ['group' => 'Acciones', 'title' => 'Abrir asistente', 'action' => 'agent', 'icon' => 'sparkles', 'keys' => 'Mod J', 'words' => 'ia chat preguntar ayuda'];
 $commands[] = ['group' => 'Acciones', 'title' => 'Cambiar tema claro / oscuro', 'action' => 'theme', 'icon' => 'moon', 'words' => 'modo oscuro claro apariencia'];
 $commands[] = ['group' => 'Acciones', 'title' => 'Activar o silenciar sonidos', 'action' => 'sound', 'icon' => 'volume', 'words' => 'audio silencio'];
 $commands[] = ['group' => 'Acciones', 'title' => 'Ver atajos de teclado', 'action' => 'shortcuts', 'icon' => 'info', 'keys' => '?', 'words' => 'teclas ayuda accesibilidad'];
 $commands[] = ['group' => 'Acciones', 'title' => 'Cerrar sesión', 'action' => 'logout', 'icon' => 'log-out', 'words' => 'salir'];
@endphp
<dialog class="command t-modal" id="command" aria-labelledby="command-title" data-search-url="{{ route('search') }}">
 <h2 class="sr-only" id="command-title">Buscar o ir a</h2>
 <div class="command-search">
  <svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#search"/></svg>
  <input type="text" role="combobox" aria-expanded="true" aria-controls="command-list" aria-autocomplete="list" aria-label="Busca contratos, personas o escribe un comando" placeholder="Busca contratos, personas o escribe un comando…" autocomplete="off" spellcheck="false" maxlength="80">
  <kbd class="command-esc">Esc</kbd>
 </div>
 <div class="command-list" id="command-list" role="listbox" aria-label="Resultados"></div>
 <div class="command-help" hidden>
  <h3>Atajos de teclado</h3>
  <dl>
   <div><dt><kbd data-mod>⌘</kbd><kbd>K</kbd></dt><dd>Buscar o ir a cualquier parte</dd></div>
   <div><dt><kbd data-mod>⌘</kbd><kbd>J</kbd></dt><dd>Abrir o cerrar el asistente</dd></div>
   <div><dt><kbd>/</kbd></dt><dd>Ir al buscador de la página</dd></div>
   <div><dt><kbd>G</kbd> <kbd>R</kbd> · <kbd>C</kbd> · <kbd>T</kbd> · <kbd>E</kbd></dt><dd>Ir a Resumen, Contratos, Trabajadores o Empresas</dd></div>
   <div><dt><kbd>N</kbd> <kbd>C</kbd> · <kbd>T</kbd>@if($isAdmin) · <kbd>E</kbd>@endif</dt><dd>{{ $isAdmin ? 'Nuevo contrato, trabajador o empresa' : 'Nuevo contrato o trabajador' }}</dd></div>
   @if($routeContract instanceof \App\Models\Contract)<div><dt><kbd>L</kbd> · <kbd>E</kbd></dt><dd>Liquidar o editar este contrato</dd></div>@endif
   <div><dt><kbd data-mod>⌘</kbd><kbd>↵</kbd></dt><dd>Guardar el formulario abierto</dd></div>
   <div><dt><kbd>Esc</kbd></dt><dd>Cerrar el panel o diálogo abierto</dd></div>
   <div><dt><kbd>?</kbd></dt><dd>Mostrar esta ayuda</dd></div>
  </dl>
 </div>
 <footer class="command-foot" aria-hidden="true">
  <span><kbd>↑</kbd><kbd>↓</kbd> navegar</span><span><kbd>↵</kbd> abrir</span><span><kbd data-mod>⌘</kbd><kbd>↵</kbd> preguntar a la IA</span><span class="command-foot-end"><kbd>?</kbd> atajos</span>
 </footer>
 <script type="application/json" data-commands>@json($commands)</script>
</dialog>
