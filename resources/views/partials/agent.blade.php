@php
 // What the user is looking at, so "revisa este contrato" knows which one. Route parameters are bound models.
 $agentRoute = request()->route();
 $agentContext = ['route' => $agentRoute?->getName(), 'page_contract' => $agentRoute?->parameter('contract')?->id, 'worker' => $agentRoute?->parameter('worker')?->id, 'settlement' => $agentRoute?->parameter('settlement')?->id];
@endphp
<div class="agent" data-agent data-url="{{ route('ai.agent') }}" data-user="{{ auth()->id() }}">
 <script type="application/json" data-agent-context>@json($agentContext)</script>
 <section class="agent-panel" id="agent-panel" role="dialog" aria-modal="false" aria-labelledby="agent-title" hidden>
  <header class="agent-head">
   <span class="agent-avatar" data-agent-blob data-size="36" aria-hidden="true"></span>
   <div class="agent-id"><h2 id="agent-title">Asistente</h2><p data-agent-status>Listo · ⌘J para abrir o cerrar</p></div>
   <span class="ai-badge" data-ai-provider>IA · {{ app(\App\Services\Ai\AiClient::class)->label() }}</span>
   <button type="button" class="icon-button agent-expand" data-agent-expand aria-pressed="false" aria-label="Pantalla completa" title="Pantalla completa"><svg class="icon icon-maximize" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#maximize"/></svg><svg class="icon icon-minimize" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#minimize"/></svg></button>
   <button type="button" class="icon-button" data-agent-reset aria-label="Nueva conversación" title="Nueva conversación"><svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#plus"/></svg></button>
   <button type="button" class="icon-button" data-agent-close data-sound="off" aria-label="Cerrar asistente" title="Cerrar (Esc)"><svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#x"/></svg></button>
  </header>
  <div class="agent-log" data-agent-log aria-live="polite"></div>
  <form class="agent-form ai-ask" data-agent-form>
   <label class="sr-only" for="agent-input">Mensaje para el asistente</label>
   <input id="agent-input" data-cuelume-type data-cuelume-emphasis="subtle" placeholder="Pregunta o pide algo…" autocomplete="off" maxlength="400">
   <button class="ai-send" type="submit" aria-label="Enviar"><svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="{{ $iconSprite }}#send"/></svg></button>
  </form>
 </section>
 <button type="button" class="agent-fab" data-agent-toggle data-sound="off" aria-controls="agent-panel" aria-expanded="false" aria-label="Abrir asistente" title="Asistente (⌘J)">
  <span class="agent-fab-blob" data-agent-blob data-size="56" aria-hidden="true"></span>
 </button>
</div>
