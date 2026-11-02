// Floating assistant. One conversation that follows the user across pages: the server (POST /ia/agente)
// works out what is asked and about which contract, runs the matching assistant task and returns the
// steps it took plus a mood for the avatar. Uses aiRequest, escapeHtml, stream, sourceList, provenance,
// wait and reduceMotion from ai.js, and icon() from app.js. Sounds go through window.sound (sounds.js, cuelume);
// importing sounds.js here would load it a second time under another URL.
import { _parts } from './vendor/blobatar/internal.js';
import { idle, thinking, happy, surprised, unsure, sad } from './vendor/blobatar/expression.js';

const MOODS = { idle, thinking, happy, surprised, unsure, sad };
const play = (name, options) => window.sound?.play(name, options);
const MOOD_HOLD_MS = 3200;
const STEP_REVEAL_MS = 260;
const MAX_TURNS = 20;
// Shortcut labels follow the platform: ⌘ on Apple devices, Ctrl elsewhere.
const MOD = /mac|iphone|ipad/i.test(navigator.userAgentData?.platform || navigator.platform) ? '⌘' : 'Ctrl+';
// The avatar is the brand: lime body, ink eyes, whatever the seed would have picked.
const BLOB = { animate: 'always', palette: { head: '#e4f222', eye: '#0f0f0d' } };
// What each mood sounds like when an answer arrives (cuelume's "which cue?" table).
const MOOD_CUES = { happy: ['ready', 'subtle'], surprised: ['warning', 'normal'], unsure: ['attention', 'subtle'], sad: ['error', 'normal'] };

const SUGGESTIONS = {
  contract: ['Revisa este contrato', 'Explícame la liquidación', '¿Tiene derecho a indemnización?'],
  settlement: ['Explícame esta liquidación', '¿Cuándo se debe pagar?', '¿Cómo se calculan las cesantías?'],
  worker: ['Revisa su contrato', 'Explícame su liquidación'],
  general: ['¿Qué hay pendiente hoy?', 'Busca los contratos retirados', '¿Cuánto dura el periodo de prueba?'],
};

// Renders the blob as inline SVG (the animated mode); a pose change only rewrites the CSS variables,
// so blobatar's motion.css morphs between expressions.
const mountBlob = slot => {
  const size = Number(slot.dataset.size);
  const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
  svg.setAttribute('viewBox', '0 0 100 100');
  svg.setAttribute('width', size);
  svg.setAttribute('height', size);
  const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
  svg.append(group);
  slot.append(svg);
  let posed = [];
  const pose = name => {
    const parts = _parts('blindem', { ...BLOB, expression: MOODS[name] || idle });
    const keys = Object.keys(parts.vars);
    posed.filter(key => !keys.includes(key)).forEach(key => svg.style.removeProperty(key));
    Object.entries(parts.vars).forEach(([key, value]) => svg.style.setProperty(key, value));
    posed = keys;
    group.setAttribute('class', parts.cls);
    if (!group.innerHTML) group.innerHTML = parts.inner;
  };
  pose('idle');
  return { pose };
};

const initAgent = root => {
  const panel = root.querySelector('.agent-panel');
  const toggle = root.querySelector('[data-agent-toggle]');
  const log = root.querySelector('[data-agent-log]');
  const form = root.querySelector('[data-agent-form]');
  const input = form.querySelector('input');
  const status = root.querySelector('[data-agent-status]');
  const page = JSON.parse(root.querySelector('[data-agent-context]').textContent);
  const blobs = [...root.querySelectorAll('[data-agent-blob]')].map(mountBlob);
  const storageKey = `agent:${root.dataset.user}`;
  status.textContent = status.textContent.replace('⌘', MOD);
  toggle.title = toggle.title.replace('⌘', MOD);
  const idleStatus = status.textContent;
  let moodTimer;
  let busy = false;

  // The conversation survives closing the panel, page loads and new tabs (localStorage, per user); whether the
  // panel is open is per tab (sessionStorage). The page's own contract takes over on a new page.
  const read = (store, key) => { try { return JSON.parse(store.getItem(key) || 'null'); } catch { return null; } };
  const saved = read(localStorage, storageKey) || read(sessionStorage, storageKey);
  const state = { open: read(sessionStorage, `${storageKey}:open`) || false, turns: saved?.turns || [], focus: page.page_contract ? { contract: page.page_contract } : saved?.focus || null };
  const save = () => {
    try {
      localStorage.setItem(storageKey, JSON.stringify({ turns: state.turns.slice(-MAX_TURNS), focus: state.focus }));
      sessionStorage.setItem(`${storageKey}:open`, JSON.stringify(state.open));
    } catch { /* optional */ }
    // A dot on the launcher says there is a conversation to come back to.
    root.classList.toggle('has-conversation', state.turns.length > 0);
    toggle.setAttribute('aria-label', `${state.open ? 'Cerrar' : 'Abrir'} asistente${state.turns.length ? ' (conversación activa)' : ''}`);
  };

  const setMood = (name, hold = true) => {
    clearTimeout(moodTimer);
    blobs.forEach(blob => blob.pose(name));
    root.dataset.mood = name;
    if (hold && name !== 'idle' && name !== 'thinking') moodTimer = setTimeout(() => setMood('idle'), MOOD_HOLD_MS);
  };

  const suggestions = () => {
    const kind = page.settlement || page.route?.startsWith('settlements.') ? 'settlement' : page.page_contract ? 'contract' : page.worker ? 'worker' : 'general';
    return SUGGESTIONS[kind];
  };

  const chips = (labels, onPick, className = 'ai-followups') => {
    const list = document.createElement('div');
    list.className = className;
    labels.forEach(({ label, value }) => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'ai-chip';
      chip.textContent = label;
      chip.addEventListener('click', () => onPick(value));
      list.append(chip);
    });
    return list;
  };

  const renderWelcome = () => {
    if (state.turns.length) return;
    const welcome = document.createElement('div');
    welcome.className = 'agent-welcome';
    welcome.innerHTML = '<p><b>Hola.</b> Puedo revisar contratos, explicar liquidaciones, buscar en la cartera y responder preguntas laborales citando la norma.</p>';
    welcome.append(chips(suggestions().map(text => ({ label: text, value: text })), text => send(text)));
    log.append(welcome);
  };

  const stepList = () => {
    const list = document.createElement('ol');
    list.className = 'agent-steps';
    return list;
  };
  const addStep = (list, { label, detail }, done = true) => {
    const item = document.createElement('li');
    item.className = done ? 'is-done' : 'is-active';
    item.innerHTML = `<span class="agent-step-mark" aria-hidden="true">${done ? icon('check') : ''}</span><span><b>${escapeHtml(label)}</b>${detail ? ` <small>${escapeHtml(detail)}</small>` : ''}</span>`;
    list.append(item);
    return item;
  };

  const itemRow = item => {
    const tag = item.href ? 'a' : 'div';
    return `<li><${tag} class="agent-item ${escapeHtml(item.tone || 'info')}"${item.href ? ` href="${escapeHtml(item.href)}"` : ''}><span class="agent-item-tone" aria-hidden="true"></span><span><b>${escapeHtml(item.title)}</b>${item.detail ? `<small>${escapeHtml(item.detail)}</small>` : ''}</span>${item.href ? icon('arrow-right') : ''}</${tag}></li>`;
  };

  // Draws an answer. `animate` is false when restoring a saved conversation.
  const renderAnswer = async (container, data, question, animate) => {
    const steps = container.querySelector('.agent-steps') || container.appendChild(stepList());
    steps.replaceChildren();
    for (const step of data.steps) {
      addStep(steps, step);
      if (animate && !reduceMotion.matches) await wait(STEP_REVEAL_MS);
    }
    const body = document.createElement('div');
    body.className = 'agent-reply';
    container.append(body);
    for (const text of data.reply.split(/\n{2,}/)) {
      const paragraph = document.createElement('p');
      body.append(paragraph);
      if (animate) await stream(paragraph, text); else paragraph.textContent = text;
    }
    if (data.items.length) body.insertAdjacentHTML('beforeend', `<ul class="agent-items">${data.items.map(itemRow).join('')}</ul>`);
    body.insertAdjacentHTML('beforeend', sourceList(data.sources) + provenance(data));
    if (data.choices.length) {
      body.append(chips(data.choices.map(choice => ({ label: choice.label, value: choice })), choice => send(question, { label: choice.label, chosen_contract: choice.contract || null, chosen_worker: choice.worker || null }), 'ai-followups agent-choices'));
    }
    // No link to the page already on screen.
    const actions = data.actions.filter(action => new URL(action.href, location.href).pathname !== location.pathname);
    if (actions.length) {
      body.insertAdjacentHTML('beforeend', `<div class="agent-actions">${actions.map(action => `<a class="btn ${action.go ? 'solid' : 'ghost'}" href="${escapeHtml(action.href)}">${escapeHtml(action.label)} ${icon('arrow-right')}</a>`).join('')}</div>`);
    }
    if (data.followups.length) body.append(chips(data.followups.map(text => ({ label: text, value: text })), text => send(text)));
  };

  const addTurn = (question, shown) => {
    log.querySelector('.agent-welcome')?.remove();
    const turn = document.createElement('div');
    turn.className = 'agent-turn';
    turn.innerHTML = `<p class="ai-question">${escapeHtml(shown || question)}</p><div class="agent-answer"></div>`;
    log.append(turn);
    return turn.querySelector('.agent-answer');
  };

  const scrollDown = () => { log.scrollTop = log.scrollHeight; };
  // A long answer is read from its question down, not from its last line.
  const scrollToTurn = answer => {
    const turn = answer.closest('.agent-turn');
    if (turn.offsetHeight > log.clientHeight) log.scrollTop += turn.getBoundingClientRect().top - log.getBoundingClientRect().top - 12;
    else scrollDown();
  };

  const send = async (message, choice = null) => {
    message = message.trim();
    if (busy || message.length < 2) return;
    busy = true;
    form.querySelector('button').disabled = true;
    log.querySelectorAll('.agent-choices button').forEach(button => { button.disabled = true; });
    const answer = addTurn(message, choice?.label);
    const steps = answer.appendChild(stepList());
    // Thinking: a dot matrix in the step mark, a status line that narrates the pipeline and its steps scrolling by.
    const pending = addStep(steps, { label: '', detail: '0.0s' }, false);
    const narration = thinkLine(THINKING.agent.states);
    const reasoning = reasoningStream(THINKING.agent.reasoning);
    pending.querySelector('.agent-step-mark').append(matrixLoader('orbit', true));
    pending.querySelector('b').replaceWith(narration.element);
    pending.insertAdjacentHTML('afterbegin', '<span class="sr-only">Pensando…</span>');
    answer.append(reasoning.element);
    const elapsed = pending.querySelector('small');
    const stopThinking = () => {
      clearInterval(timer);
      narration.stop();
      reasoning.stop();
      reasoning.element.remove();
    };
    const started = performance.now();
    const timer = setInterval(() => { elapsed.textContent = `${((performance.now() - started) / 1000).toFixed(1)}s`; }, 100);
    status.textContent = 'Pensando…';
    setMood('thinking');
    play('loading', { emphasis: 'subtle' });
    scrollDown();
    try {
      const context = { route: page.route, page_contract: page.page_contract, worker: page.worker, settlement: page.settlement, contract: state.focus?.contract ?? page.page_contract, chosen_contract: choice?.chosen_contract, chosen_worker: choice?.chosen_worker };
      const data = await aiRequest(root.dataset.url, { quiet: true, method: 'POST', body: { message, context: Object.fromEntries(Object.entries(context).filter(([, value]) => value != null)) } });
      stopThinking();
      if (data.focus) state.focus = data.focus;
      state.turns.push({ question: message, shown: choice?.label || null, data });
      save();
      setMood(data.mood);
      const [cue, emphasis] = MOOD_CUES[data.mood] || MOOD_CUES.happy;
      play(cue, { emphasis });
      status.textContent = data.mood === 'unsure' ? 'Necesito un dato más' : idleStatus;
      await renderAnswer(answer, data, message, true);
      scrollToTurn(answer);
    } catch (error) {
      stopThinking();
      steps.remove();
      setMood('sad');
      play('error');
      status.textContent = idleStatus;
      showError(answer, error.message, () => { answer.closest('.agent-turn').remove(); send(message, choice); });
      scrollDown();
    } finally {
      busy = false;
      form.querySelector('button').disabled = false;
    }
  };

  const restore = () => {
    state.turns.forEach(({ question, shown, data }) => renderAnswer(addTurn(question, shown), data, question, false));
    log.querySelectorAll('.agent-turn:not(:last-child) .agent-choices button').forEach(button => { button.disabled = true; });
    renderWelcome();
    scrollDown();
  };

  // Opening and closing both animate (the panel grows out of and back into the blob); `instant` is for
  // restoring an open panel on page load, which should neither animate nor make a sound.
  let closeTimer;
  const setOpen = (open, instant = false) => {
    if (open === state.open && !instant) return;
    state.open = open;
    clearTimeout(closeTimer);
    root.classList.toggle('is-open', open);
    toggle.setAttribute('aria-expanded', String(open));
    save();
    if (!instant) play(open ? 'open' : 'close');
    if (open) {
      panel.classList.remove('is-closing');
      panel.classList.toggle('is-instant', instant);
      panel.hidden = false;
      scrollDown();
      input.focus({ preventScroll: true });
      return;
    }
    panel.classList.add('is-closing');
    closeTimer = setTimeout(() => {
      panel.hidden = true;
      panel.classList.remove('is-closing', 'is-instant');
    }, reduceMotion.matches ? 0 : 200);
  };

  // Full screen is a preference, so it is remembered across tabs (localStorage) and applies the next time the panel opens.
  const expand = root.querySelector('[data-agent-expand]');
  const setExpanded = expanded => {
    state.expanded = expanded;
    root.classList.toggle('is-expanded', expanded);
    expand.setAttribute('aria-pressed', String(expanded));
    expand.setAttribute('aria-label', expanded ? 'Salir de pantalla completa' : 'Pantalla completa');
    expand.title = expanded ? 'Salir de pantalla completa (Esc)' : 'Pantalla completa';
    try { localStorage.setItem(`${storageKey}:expanded`, JSON.stringify(expanded)); } catch { /* optional */ }
  };
  setExpanded(read(localStorage, `${storageKey}:expanded`) || false);
  expand.addEventListener('click', () => {
    setExpanded(!state.expanded);
    scrollDown();
    input.focus({ preventScroll: true });
  });

  toggle.addEventListener('click', () => setOpen(!state.open));
  root.querySelector('[data-agent-close]').addEventListener('click', () => { setOpen(false); toggle.focus(); });
  root.querySelector('[data-agent-reset]').addEventListener('click', () => {
    state.turns = [];
    state.focus = page.page_contract ? { contract: page.page_contract } : null;
    save();
    log.replaceChildren();
    renderWelcome();
    setMood('idle');
    input.focus();
  });
  form.addEventListener('submit', event => {
    event.preventDefault();
    const message = input.value;
    input.value = '';
    send(message);
  });
  // A click anywhere outside closes the panel, unless it lands in a modal on top (confirm, command palette).
  document.addEventListener('pointerdown', event => {
    if (!state.open || root.contains(event.target) || event.target.closest('dialog, .t-modal')) return;
    setOpen(false);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && state.open && !document.querySelector('dialog[open]')) {
      // The first Esc leaves full screen, the next one closes.
      if (state.expanded) {
        setExpanded(false);
        return;
      }
      const inside = panel.contains(document.activeElement);
      setOpen(false);
      if (inside) toggle.focus();
    }
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'j') {
      event.preventDefault();
      setOpen(!state.open);
    }
  });

  restore();
  save();
  if (state.open) setOpen(true, true);

  // Used by the command palette (command.js): open the panel, or open it and ask straight away.
  window.agent = {
    open: () => setOpen(true),
    toggle: () => setOpen(!state.open),
    ask: text => {
      setOpen(true);
      send(text);
    },
  };
};

document.querySelectorAll('[data-agent]').forEach(initAgent);
