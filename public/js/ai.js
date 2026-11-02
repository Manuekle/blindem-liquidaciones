// Assistant features. Every request goes to /ia/* and renders the structured answer; the provider
// behind it (simulated today) is a server concern. Depends on `icon()` from app.js; `window.sound` (sounds.js)
// is optional and plays the AI cues: loading when asked, ready when the answer is in, error when it fails.
const AI_MIN_THINKING_MS = 650;
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
const escapeHtml = text => String(text).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));

// `quiet` skips the sounds: for requests the page makes by itself on load, and for callers that pick their own cue.
const aiRequest = async (url, { method = 'GET', body, quiet = false } = {}) => {
  const cue = (name, options) => { if (!quiet) window.sound?.play(name, options); };
  cue('loading', { emphasis: 'subtle' });
  const started = performance.now();
  const response = await fetch(url, {
    method,
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), ...(body ? { 'Content-Type': 'application/json' } : {}) },
    body: body ? JSON.stringify(body) : undefined,
    credentials: 'same-origin',
  });
  const payload = await response.json().catch(() => ({}));
  // Keep the thinking state on screen long enough to read, like a real model call.
  await wait(Math.max(0, AI_MIN_THINKING_MS - (performance.now() - started)));
  if (!response.ok) {
    cue('error');
    const firstError = payload.errors && Object.values(payload.errors)[0]?.[0];
    throw new Error(firstError || payload.message || 'El asistente no respondió. Inténtalo de nuevo.');
  }
  document.querySelectorAll('[data-ai-provider]').forEach(badge => { badge.textContent = `IA · ${payload.meta.provider}`; });
  cue('ready', { emphasis: 'subtle' });
  return payload.data;
};

// Motion recipes from transitions.dev: Matrix dot loader, Thinking states, Reasoning stream and Streaming text.
// Timings live in CSS (`--matrix-*`, `--think-*`, `--reason-*`, `--stream-*`) so they can be tuned in one place.
const motionToken = (name, fallback) => {
  const value = parseFloat(getComputedStyle(document.documentElement).getPropertyValue(name));
  return Number.isFinite(value) ? value : fallback;
};

// What each assistant task narrates while it works: a status line that swaps every --think-hold and, for the longer
// tasks, the steps of the pipeline scrolling by (Grounding, RAG and the rules described in docs/IA.md).
const THINKING = {
  agent: {
    states: ['Leyendo tu pregunta', 'Ubicando el contrato', 'Consultando las fuentes', 'Verificando las cifras', 'Redactando la respuesta'],
    reasoning: ['Identifico qué me pides y en qué pantalla estás', 'Si hablas de un contrato, busco cuál es', 'Elijo la tarea: revisar, explicar, buscar o responder', 'Recupero los artículos del Código Sustantivo que aplican', 'Calculo con las reglas del motor de liquidación', 'Comparo cada cifra del texto con el cálculo', 'Cito la fuente de cada afirmación legal', 'Si falta un dato, te lo pregunto en vez de suponer'],
  },
  extract: {
    states: ['Leyendo el texto', 'Buscando nombres y documentos', 'Reconociendo fechas', 'Leyendo el salario', 'Llenando los campos'],
    reasoning: ['Separo el texto en frases', 'Busco el nombre y el documento del trabajador', 'Reconozco fechas de ingreso, retiro y fin pactado', 'Identifico empresa, cargo y tipo de contrato', 'Convierto el salario a pesos', 'Cruzo los nombres con los registros existentes', 'Marco lo que no encontré para preguntártelo'],
  },
  review: { states: ['Revisando el contrato', 'Comparando fechas', 'Validando el salario', 'Clasificando hallazgos'] },
  explain: { states: ['Leyendo la liquidación', 'Agrupando conceptos', 'Buscando la norma', 'Redactando'] },
  ask: { states: ['Pensando', 'Buscando en las fuentes', 'Redactando'] },
  letter: { states: ['Redactando', 'Revisando el tono', 'Ajustando cifras'] },
  search: { states: ['Interpretando', 'Armando los filtros'] },
};

// Sixteen dots, each handed a delay into one shared pulse; the variant is just the delay table.
const MATRIX_CORNERS = [0, 3, 12, 15];
const MATRIX_RING = [1, 2, 7, 11, 14, 13, 8, 4];
const MATRIX_INNER = [5, 6, 9, 10];
const MATRIX_TWINKLE = [7, 2, 11, 5, 14, 9, 0, 12, 3, 15, 6, 10, 13, 1, 8, 4];
const matrixLoader = (variant = 'scan', rounded = false) => {
  const loader = document.createElement('span');
  loader.className = 't-matrix';
  loader.dataset.variant = variant;
  loader.setAttribute('aria-hidden', 'true');
  const cycle = motionToken('--matrix-cycle', 1200);
  for (let index = 0; index < 16; index++) {
    const dot = document.createElement('i');
    const column = index % 4;
    if (rounded && MATRIX_CORNERS.includes(index)) dot.className = 'is-gap';
    else if (variant === 'scan') dot.style.setProperty('--d', String(Math.round(column * (cycle / 10))));
    else if (variant === 'twinkle') dot.style.setProperty('--d', String(Math.round(MATRIX_TWINKLE[index] * (cycle / 16))));
    else if (variant === 'orbit') {
      const step = MATRIX_RING.indexOf(index);
      if (step === -1) dot.style.animation = 'none';
      else dot.style.setProperty('--d', String(Math.round(step * (cycle / 8))));
    } else if (variant === 'pulse') dot.style.setProperty('--d', String(Math.round((MATRIX_INNER.includes(index) ? 0 : 1) * cycle * 0.16)));
    loader.append(dot);
  }
  return loader;
};

// A shimmering status line that swaps to the next state every --think-hold and stays on the last one.
// Decorative: callers keep a static label for assistive technology.
const thinkLine = states => {
  states = [].concat(states);
  const box = document.createElement('span');
  box.className = 't-think';
  box.setAttribute('aria-hidden', 'true');
  const longest = states.reduce((a, b) => (b.length > a.length ? b : a), '');
  const line = text => {
    const element = document.createElement('span');
    element.className = 't-think-text';
    element.textContent = text;
    element.dataset.text = text;
    return element;
  };
  box.innerHTML = `<span class="t-think-sizer">${escapeHtml(longest)}</span>`;
  let live = line(states[0]);
  box.append(live);
  let index = 0;
  let timer;
  const cycle = () => {
    timer = setTimeout(() => {
      if (!box.isConnected || index >= states.length - 1) return;
      const swap = motionToken('--think-swap', 150);
      const gap = motionToken('--think-gap', 50);
      const leaving = live;
      index += 1;
      leaving.classList.add('is-exit');
      const next = line(states[index]);
      next.classList.add('is-enter-start');
      box.append(next);
      live = next;
      setTimeout(() => {
        void next.offsetWidth;
        next.classList.remove('is-enter-start');
      }, gap);
      timer = setTimeout(() => {
        leaving.remove();
        cycle();
      }, swap + gap);
    }, motionToken('--think-hold', 2000));
  };
  if (states.length > 1 && !reduceMotion.matches) cycle();
  return { element: box, stop: () => clearTimeout(timer) };
};

// The steps a task goes through, scrolling up two lines at a time inside a faded window. The transcript is cloned
// once so the loop wraps without a jump.
const reasoningStream = lines => {
  const card = document.createElement('div');
  card.className = 't-reason ai-reason';
  card.setAttribute('aria-hidden', 'true');
  card.innerHTML = `<div class="t-reason-viewport"><div class="t-reason-scroll"><div class="t-reason-text">${lines.map(text => `<p>${escapeHtml(text)}</p>`).join('')}</div></div></div>`;
  const scroll = card.querySelector('.t-reason-scroll');
  const text = scroll.querySelector('.t-reason-text');
  scroll.append(text.cloneNode(true));
  let offset = 0;
  let timer;
  const step = () => {
    timer = setTimeout(() => {
      if (!card.isConnected) return;
      const lineHeight = parseFloat(getComputedStyle(text).lineHeight) || 18;
      const duration = motionToken('--reason-step', 500);
      const ease = getComputedStyle(document.documentElement).getPropertyValue('--reason-ease').trim() || 'ease-out';
      offset += lineHeight * motionToken('--reason-lines', 2);
      scroll.style.transition = `transform ${duration}ms ${ease}`;
      scroll.style.transform = `translateY(${-offset}px)`;
      timer = setTimeout(() => {
        const copyHeight = text.offsetHeight;
        if (copyHeight && offset >= copyHeight) {
          offset -= copyHeight;
          scroll.style.transition = 'none';
          scroll.style.transform = `translateY(${-offset}px)`;
          void scroll.offsetWidth;
        }
        step();
      }, duration + 30);
    }, motionToken('--reason-hold', 840));
  };
  if (!reduceMotion.matches) step();
  return { element: card, stop: () => clearTimeout(timer) };
};

// Loader, status line and elapsed time; `task` picks the narration from THINKING (or pass a label).
const thinking = (container, task = 'Analizando') => {
  const { states, reasoning } = THINKING[task] || { states: [task] };
  container.innerHTML = `<div class="ai-thinking-block"><div class="ai-thinking" role="status"><span class="sr-only">${escapeHtml(states[0])}…</span><span class="ai-elapsed" aria-hidden="true">0.0s</span></div></div>`;
  const row = container.querySelector('.ai-thinking');
  const line = thinkLine(states);
  row.prepend(matrixLoader('scan'), line.element);
  const reason = reasoning ? reasoningStream(reasoning) : null;
  if (reason) container.firstChild.append(reason.element);
  const elapsed = row.querySelector('.ai-elapsed');
  const started = performance.now();
  const timer = setInterval(() => { elapsed.textContent = `${((performance.now() - started) / 1000).toFixed(1)}s`; }, 100);
  return () => {
    clearInterval(timer);
    line.stop();
    reason?.stop();
  };
};

const showError = (container, message, retry) => {
  container.innerHTML = `<div class="ai-error" role="alert"><span>${escapeHtml(message)}</span>${retry ? '<button type="button" class="btn ghost">Reintentar</button>' : ''}</div>`;
  container.querySelector('button')?.addEventListener('click', retry);
};

// Streaming text: every word is in the DOM at once (screen readers get the whole answer in one go) and resolves into
// view through a soft blur, one every --stream-gap. Long paragraphs tighten the gap so they finish within ~9s; a
// click on the text shows the rest at once. Instant with reduced motion.
const stream = (element, text) => new Promise(resolve => {
  element.textContent = '';
  if (reduceMotion.matches) {
    element.textContent = text;
    resolve();
    return;
  }
  const words = [];
  for (const part of text.split(/(\s+)/)) {
    if (!part) continue;
    if (/^\s+$/.test(part)) {
      element.append(part);
      continue;
    }
    const word = document.createElement('span');
    word.className = 't-stream-w';
    word.textContent = part;
    element.append(word);
    words.push(word);
  }
  const gap = Math.max(28, Math.min(motionToken('--stream-gap', 60), 9000 / Math.max(1, words.length)));
  let index = 0;
  let timer;
  const finish = () => {
    clearTimeout(timer);
    words.forEach(word => word.classList.add('is-in'));
    element.classList.remove('is-streaming');
    resolve();
  };
  const next = () => {
    if (index >= words.length || !element.isConnected) return finish();
    words[index++].classList.add('is-in');
    timer = setTimeout(next, gap);
  };
  element.classList.add('is-streaming');
  element.addEventListener('click', finish, { once: true });
  void element.offsetWidth;
  next();
});

// Says where an answer came from when a language model was involved (see AiAssistant).
const provenance = data => {
  if (data.fallback) return data.sources?.length
    ? '<p class="ai-provenance warn">El modelo dio datos que no aparecen en las fuentes; se muestra el fragmento de la norma.</p>'
    : '<p class="ai-provenance warn">El modelo dio datos que no coinciden con el cálculo; se muestra la versión verificada por reglas.</p>';
  if (data.status === 'insufficient') return '<p class="ai-provenance">Sin fuentes suficientes: no respondo con suposiciones.</p>';
  if (data.source === 'ai' && data.sources?.length) return '<p class="ai-provenance">Redactado por el modelo a partir de las fuentes citadas.</p>';
  if (data.source === 'ai') return '<p class="ai-provenance">Redactado por el modelo y verificado contra las cifras del cálculo.</p>';
  if (data.source === 'mixed') return '<p class="ai-provenance">Combina reglas verificadas con aportes del modelo, marcados «IA».</p>';
  return '';
};
// The notes a question was answered from (RAG); [S1] in the answer points to the source with that id.
const sourceList = sources => (sources?.length
  ? `<details class="ai-sources"><summary>Fuentes (${sources.length})</summary><ol>${sources.map(source => `<li><span class="ai-source-id">${escapeHtml(source.id)}</span><div><b>${escapeHtml(source.title)}</b><small>${escapeHtml(source.norm)}</small><p>${escapeHtml(source.excerpt)}</p></div></li>`).join('')}</ol></details>`
  : '');
const aiTag = item => (item.source === 'ia' ? ' <span class="ai-tag">IA</span>' : '');

const followUps = (questions, onPick) => {
  const list = document.createElement('div');
  list.className = 'ai-followups';
  list.setAttribute('aria-label', 'Preguntas sugeridas');
  questions.forEach(question => {
    const chip = document.createElement('button');
    chip.type = 'button';
    chip.className = 'ai-chip';
    chip.textContent = question;
    chip.addEventListener('click', () => onPick(question));
    list.append(chip);
  });
  return list;
};

// Contract form: paste text, fill fields.
const initExtract = card => {
  const form = document.querySelector('.contract-form');
  const textarea = card.querySelector('textarea');
  const button = card.querySelector('[data-ai-run]');
  const output = card.querySelector('.ai-output');
  const run = async () => {
    if (textarea.value.trim().length < 15) {
      showError(output, 'Pega un texto un poco más largo: un correo, una carta de renuncia o las notas del contrato.');
      textarea.focus();
      return;
    }
    button.disabled = true;
    const stop = thinking(output, 'extract');
    try {
      const data = await aiRequest(card.dataset.url, { method: 'POST', body: { text: textarea.value } });
      stop();
      form.querySelectorAll('.ai-filled').forEach(field => field.classList.remove('ai-filled'));
      Object.entries(data.fields).forEach(([name, value]) => {
        const control = form.querySelector(`[name="${name}"]`);
        if (!control) return;
        control.value = value;
        control.dispatchEvent(new Event('input', { bubbles: true }));
        control.dispatchEvent(new Event('change', { bubbles: true }));
        control.closest('.field')?.classList.add('ai-filled');
      });
      const rows = data.found.map(item => `<li><span class="ai-dot ok" aria-hidden="true"></span><div><b>${escapeHtml(item.label)}</b><small>${escapeHtml(item.evidence)}</small></div><span>${escapeHtml(item.value)}</span></li>`).join('');
      const missing = data.missing.length ? `<p class="ai-note"><b>Completa a mano:</b> ${data.missing.map(escapeHtml).join(', ')}.</p>` : '';
      const notes = data.notes.map(note => `<p class="ai-note warn">${escapeHtml(note)}</p>`).join('');
      output.innerHTML = data.found.length
        ? `<p class="ai-lead">Rellené ${data.found.length} ${data.found.length === 1 ? 'campo' : 'campos'}. Revísalos antes de guardar.</p><ul class="ai-rows">${rows}</ul>${missing}${notes}${provenance(data)}`
        : `<p class="ai-lead">No reconocí datos del contrato en el texto.</p>${notes}`;
      form.querySelector('.ai-filled')?.scrollIntoView({ block: 'center', behavior: reduceMotion.matches ? 'auto' : 'smooth' });
    } catch (error) {
      stop();
      showError(output, error.message, run);
    } finally {
      button.disabled = false;
    }
  };
  button.addEventListener('click', run);
};

// Contract detail: review before settling.
const initReview = card => {
  const output = card.querySelector('.ai-output');
  const run = async (quiet = false) => {
    const stop = thinking(output, 'review');
    try {
      const data = await aiRequest(card.dataset.url, { quiet });
      stop();
      const severity = { alta: 'Alta', media: 'Media', baja: 'Baja' };
      output.innerHTML = `<p class="ai-lead">${escapeHtml(data.summary)}</p>${data.findings.length ? `<ul class="ai-rows">${data.findings.map(finding => `<li><span class="ai-dot ${finding.severity}" aria-hidden="true"></span><div><b>${escapeHtml(finding.title)}${aiTag(finding)}</b><small>${escapeHtml(finding.detail)}</small></div><span class="ai-severity ${finding.severity}">${severity[finding.severity]}</span></li>`).join('')}</ul>` : ''}`;
      output.insertAdjacentHTML('beforeend', provenance(data));
      card.querySelector('.ai-card-actions')?.toggleAttribute('hidden', !data.findings.length);
    } catch (error) {
      stop();
      showError(output, error.message, () => run());
    }
  };
  card.querySelector('[data-ai-run]')?.addEventListener('click', () => run());
  run(true);
};

// Settlement: explanation, follow-up questions and the letter.
const initExplain = card => {
  const output = card.querySelector('.ai-output');
  const conversation = card.querySelector('.ai-conversation');
  const askForm = card.querySelector('.ai-ask');
  const query = card.dataset.settlement ? `?settlement=${card.dataset.settlement}` : '';
  const ask = async question => {
    const turn = document.createElement('div');
    turn.className = 'ai-turn';
    turn.innerHTML = `<p class="ai-question">${escapeHtml(question)}</p><div class="ai-answer"></div>`;
    conversation.append(turn);
    const answer = turn.querySelector('.ai-answer');
    const stop = thinking(answer, 'ask');
    turn.scrollIntoView({ block: 'nearest', behavior: reduceMotion.matches ? 'auto' : 'smooth' });
    try {
      const data = await aiRequest(card.dataset.askUrl + query, { method: 'POST', body: { question } });
      stop();
      answer.innerHTML = '<p></p>';
      await stream(answer.querySelector('p'), data.answer);
      answer.insertAdjacentHTML('beforeend', sourceList(data.sources) + provenance(data));
    } catch (error) {
      stop();
      showError(answer, error.message);
    }
  };
  const run = async () => {
    card.querySelector('[data-ai-run]').hidden = true;
    const stop = thinking(output, 'explain');
    try {
      const data = await aiRequest(card.dataset.url + query);
      stop();
      output.innerHTML = `<p class="ai-headline"></p><div class="ai-shares">${data.highlights.map(item => `<div class="ai-share"><span>${escapeHtml(item.label)}</span><i aria-hidden="true"><b style="width:${Math.max(2, item.share)}%"></b></i><span>${item.share}%</span></div>`).join('')}</div><div class="ai-paragraphs"></div>`;
      await stream(output.querySelector('.ai-headline'), data.headline);
      const paragraphs = output.querySelector('.ai-paragraphs');
      for (const text of data.paragraphs) {
        const paragraph = document.createElement('p');
        paragraphs.append(paragraph);
        await stream(paragraph, text);
      }
      output.insertAdjacentHTML('beforeend', provenance(data));
      output.append(followUps(data.followups, ask));
      askForm.hidden = false;
    } catch (error) {
      stop();
      card.querySelector('[data-ai-run]').hidden = false;
      showError(output, error.message, run);
    }
  };
  card.querySelector('[data-ai-run]').addEventListener('click', run);
  askForm.addEventListener('submit', event => {
    event.preventDefault();
    const input = askForm.querySelector('input');
    const question = input.value.trim();
    if (question.length < 3) return;
    input.value = '';
    ask(question);
  });
};

const initLetter = card => {
  const output = card.querySelector('.ai-output');
  const button = card.querySelector('[data-ai-run]');
  const query = card.dataset.settlement ? `?settlement=${card.dataset.settlement}` : '';
  const run = async () => {
    const kind = card.querySelector('input[name="letter-kind"]:checked').value;
    button.disabled = true;
    const stop = thinking(output, 'letter');
    try {
      const data = await aiRequest(card.dataset.url + query, { method: 'POST', body: { kind } });
      stop();
      output.innerHTML = `<div class="ai-letter"><div class="ai-letter-head"><b>${escapeHtml(data.subject)}</b><button type="button" class="ai-copy">${icon('copy')}<span>Copiar</span></button></div><pre></pre></div>`;
      const body = output.querySelector('pre');
      await stream(body, data.body);
      output.insertAdjacentHTML('beforeend', provenance(data));
      const copy = output.querySelector('.ai-copy');
      copy.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(`${data.subject}\n\n${data.body}`);
          window.sound?.play('success', { emphasis: 'subtle' });
          copy.innerHTML = `${icon('check')}<span>Copiado</span>`;
          setTimeout(() => { copy.innerHTML = `${icon('copy')}<span>Copiar</span>`; }, 1600);
        } catch {
          copy.querySelector('span').textContent = 'Selecciona y copia';
        }
      });
    } catch (error) {
      stop();
      showError(output, error.message, run);
    } finally {
      button.disabled = false;
    }
  };
  button.addEventListener('click', run);
};

// Dashboard: insight cards (Beautiful UI "Insight Cards").
const initInsights = section => {
  const output = section.querySelector('.ai-output');
  const run = async () => {
    output.innerHTML = '<div class="ai-insight-grid">' + '<div class="ai-insight is-loading"><i></i><i></i><i></i></div>'.repeat(3) + '</div>';
    try {
      const data = await aiRequest(section.dataset.url, { quiet: true });
      const linkFor = insight => {
        if (!insight.contract_id || !insight.action) return '';
        const path = { settlement: '/liquidacion', edit: '/editar' }[insight.route] || '';
        return `<a class="ai-insight-link" href="${section.dataset.contractsUrl}/${insight.contract_id}${path}">${escapeHtml(insight.action)} ${icon('arrow-right')}</a>`;
      };
      output.innerHTML = `<div class="ai-insight-grid">${data.insights.map(insight => `<article class="ai-insight ${insight.tone}"><span class="ai-insight-tone" aria-hidden="true"></span><h3>${escapeHtml(insight.title)}${aiTag(insight)}</h3><p>${escapeHtml(insight.detail)}</p>${linkFor(insight)}</article>`).join('')}</div>`;
    } catch (error) {
      showError(output, error.message, run);
    }
  };
  run();
};

// Contracts: natural-language search into filters.
const initSearch = form => {
  const input = form.querySelector('input');
  const output = form.querySelector('.ai-output');
  const key = 'ai-search-understood';
  try {
    const remembered = JSON.parse(sessionStorage.getItem(key) || 'null');
    sessionStorage.removeItem(key);
    if (remembered) {
      input.value = remembered.query;
      output.innerHTML = `<div class="ai-understood"><span>${escapeHtml(remembered.message)}</span>${remembered.understood.map(label => `<span class="ai-chip static">${escapeHtml(label)}</span>`).join('')}</div>`;
    }
  } catch { /* storage unavailable: the filters still apply */ }
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const queryText = input.value.trim();
    if (queryText.length < 2) return;
    const stop = thinking(output, 'search');
    try {
      const data = await aiRequest(form.dataset.url, { method: 'POST', body: { query: queryText } });
      stop();
      if (!Object.keys(data.filters).length) {
        output.innerHTML = `<div class="ai-understood"><span>${escapeHtml(data.message)}</span></div>`;
        return;
      }
      try { sessionStorage.setItem(key, JSON.stringify({ query: queryText, message: data.message, understood: data.understood })); } catch { /* optional */ }
      window.location.assign(`${form.action}?${new URLSearchParams(data.filters)}`);
    } catch (error) {
      stop();
      showError(output, error.message);
    }
  });
};

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-ai="extract"]').forEach(initExtract);
  document.querySelectorAll('[data-ai="review"]').forEach(initReview);
  document.querySelectorAll('[data-ai="explain"]').forEach(initExplain);
  document.querySelectorAll('[data-ai="letter"]').forEach(initLetter);
  document.querySelectorAll('[data-ai="insights"]').forEach(initInsights);
  document.querySelectorAll('[data-ai="search"]').forEach(initSearch);
});
