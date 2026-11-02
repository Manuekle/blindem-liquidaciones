// Command palette (⌘K / Ctrl+K) and the app's keyboard shortcuts. The palette is an ARIA combobox over a listbox:
// focus stays in the input, ↑/↓ move the active option (aria-activedescendant), Enter runs it. The commands come
// from partials/command.blade.php; records come from GET /buscar. Uses icon(), normalize() and motionMs() from
// app.js and matrixLoader() and escapeHtml() from ai.js.
(() => {
  const dialog = document.querySelector('#command');
  if (!dialog) return;
  const input = dialog.querySelector('input');
  const list = dialog.querySelector('.command-list');
  const help = dialog.querySelector('.command-help');
  const commands = JSON.parse(dialog.querySelector('[data-commands]').textContent);
  const isMac = /mac|iphone|ipad/i.test(navigator.userAgentData?.platform || navigator.platform);
  const KIND = { contract: ['Contrato', 'file-text'], worker: ['Trabajador', 'users-round'], company: ['Empresa', 'building-2'] };
  const SEQUENCE_MS = 1200;

  document.querySelectorAll('kbd[data-mod]').forEach(key => { key.textContent = isMac ? '⌘' : 'Ctrl'; });
  const keyLabel = keys => keys.split(' ').map(key => `<kbd>${key === 'Mod' ? (isMac ? '⌘' : 'Ctrl') : escapeHtml(key)}</kbd>`).join('');

  // Nav links show their sequence on hover. aria-keyshortcuts cannot express sequences (G then C), so assistive
  // technology gets them from the palette's help instead.
  commands.filter(command => command.href && command.keys && !command.keys.includes('Mod')).forEach(command => {
    document.querySelectorAll(`.nav-link[href="${CSS.escape(command.href)}"]`).forEach(link => { link.title = `Atajo: ${command.keys}`; });
  });

  // Ranking: whole-title prefix, then a word prefix, then substring, then keywords; ties keep the declared order.
  const score = (command, query) => {
    const title = normalize(command.title);
    if (title.startsWith(query)) return 4;
    if (title.split(/\s+/).some(word => word.startsWith(query))) return 3;
    if (title.includes(query)) return 2;
    if (normalize(`${command.detail || ''} ${command.words || ''}`).includes(query)) return 1;
    return 0;
  };

  let items = [];
  let active = 0;
  let records = [];
  let searching = false;
  let lastFocus = null;
  let searchTimer;
  let controller;

  const optionHtml = (item, index) => {
    const meta = item.keys ? `<span class="command-keys" aria-hidden="true">${keyLabel(item.keys)}</span>` : item.kindLabel ? `<span class="command-kind">${item.kindLabel}</span>` : '';
    return `<div class="command-item${item.ask ? ' is-ask' : ''}" role="option" id="command-option-${index}" aria-selected="${index === active}" data-index="${index}">${icon(item.icon)}<span class="command-text"><b>${escapeHtml(item.title)}</b>${item.detail ? `<small>${escapeHtml(item.detail)}</small>` : ''}</span>${meta}</div>`;
  };

  const render = () => {
    const query = normalize(input.value);
    const raw = input.value.trim();
    help.hidden = true;
    list.hidden = false;
    const matched = query
      ? commands.map((command, order) => ({ command, rank: score(command, query), order })).filter(entry => entry.rank).sort((a, b) => b.rank - a.rank || a.order - b.order).map(entry => entry.command)
      : commands;
    const found = records.map(record => ({ ...record, group: 'Registros', icon: KIND[record.kind][1], kindLabel: KIND[record.kind][0] }));
    const ask = raw.length >= 2 ? [{ group: 'Asistente', title: `Preguntar: «${raw}»`, detail: 'El asistente responde con la norma y tus datos', icon: 'sparkles', action: 'ask', ask: true }] : [];
    // With a query, records lead; when nothing matches, the assistant is the first suggestion.
    items = [...found, ...matched, ...ask];
    if (active >= items.length) active = 0;
    const groups = [];
    items.forEach((item, index) => {
      let group = groups.find(entry => entry.name === item.group);
      if (!group) groups.push(group = { name: item.group, html: '' });
      group.html += optionHtml(item, index);
    });
    const loading = searching ? `<div class="command-loading" role="status"><span class="sr-only">Buscando registros…</span></div>` : '';
    list.innerHTML = groups.map((group, index) => `<div class="command-group" role="group" aria-labelledby="command-group-${index}"><div class="command-group-label" id="command-group-${index}" role="presentation">${escapeHtml(group.name)}</div>${group.html}</div>`).join('') + loading;
    if (searching) list.querySelector('.command-loading').prepend(matrixLoader('twinkle'));
    if (!items.length) list.innerHTML = '<p class="command-empty">Escribe al menos dos letras para buscar.</p>';
    setActive(active, false);
  };

  const setActive = (index, scroll = true) => {
    if (!items.length) {
      input.removeAttribute('aria-activedescendant');
      return;
    }
    active = (index + items.length) % items.length;
    list.querySelectorAll('[role=option]').forEach(option => option.setAttribute('aria-selected', String(Number(option.dataset.index) === active)));
    const option = list.querySelector(`#command-option-${active}`);
    input.setAttribute('aria-activedescendant', option.id);
    if (scroll) option.scrollIntoView({ block: 'nearest' });
  };

  const search = () => {
    clearTimeout(searchTimer);
    controller?.abort();
    const query = input.value.trim();
    if (query.length < 2) {
      records = [];
      searching = false;
      return;
    }
    searching = true;
    searchTimer = setTimeout(async () => {
      controller = new AbortController();
      try {
        const response = await fetch(`${dialog.dataset.searchUrl}?${new URLSearchParams({ q: query })}`, { headers: { Accept: 'application/json' }, signal: controller.signal, credentials: 'same-origin' });
        records = response.ok ? (await response.json()).data : [];
      } catch (error) {
        if (error.name === 'AbortError') return;
        records = [];
      }
      searching = false;
      active = 0;
      render();
    }, 160);
  };

  const showHelp = () => {
    list.hidden = true;
    help.hidden = false;
    input.removeAttribute('aria-activedescendant');
  };

  const run = (item, newTab = false) => {
    if (!item) return;
    if (item.href) {
      close(false);
      if (newTab) window.open(item.href, '_blank', 'noopener');
      else window.location.assign(item.href);
      return;
    }
    if (item.action === 'shortcuts') return showHelp();
    close(item.action !== 'agent' && item.action !== 'ask');
    if (item.action === 'agent') window.agent?.open();
    if (item.action === 'ask') window.agent?.ask(input.value.trim());
    if (item.action === 'theme') document.querySelector('#theme-toggle')?.click();
    if (item.action === 'sound') document.querySelector('#sound-toggle')?.click();
    if (item.action === 'logout') document.querySelector('form[action$="/logout"]')?.requestSubmit();
  };

  const open = (mode = 'search') => {
    if (dialog.open) {
      if (mode === 'help') showHelp();
      return;
    }
    lastFocus = document.activeElement;
    input.value = '';
    records = [];
    active = 0;
    render();
    if (mode === 'help') showHelp();
    dialog.classList.remove('is-closing');
    dialog.showModal();
    void dialog.offsetWidth;
    dialog.classList.add('is-open');
    window.sound?.play('open', { emphasis: 'subtle' });
    input.focus();
  };

  // Plays the close transition first; focus goes back where it was unless the command moves it elsewhere.
  const close = (restoreFocus = true) => {
    if (!dialog.open || dialog.classList.contains('is-closing')) return;
    dialog.classList.remove('is-open');
    dialog.classList.add('is-closing');
    controller?.abort();
    setTimeout(() => {
      dialog.close();
      dialog.classList.remove('is-closing');
      if (restoreFocus) lastFocus?.focus?.({ preventScroll: true });
    }, motionMs('--modal-close-dur'));
  };

  input.addEventListener('input', () => {
    active = 0;
    search();
    render();
  });
  input.addEventListener('keydown', event => {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      setActive(active + (event.key === 'ArrowDown' ? 1 : -1));
    } else if (event.key === 'Home' && event.ctrlKey) setActive(0);
    else if (event.key === 'End' && event.ctrlKey) setActive(items.length - 1);
    else if (event.key === 'Enter') {
      event.preventDefault();
      if (!help.hidden) return render();
      // ⌘↵ asks the assistant; on a page it opens a new tab.
      const item = items[active];
      if ((event.metaKey || event.ctrlKey) && !item?.href && input.value.trim().length >= 2) return run(items.find(entry => entry.ask));
      run(item, event.metaKey || event.ctrlKey);
    } else if (event.key === '?' && !input.value) {
      event.preventDefault();
      showHelp();
    }
  });
  list.addEventListener('pointermove', event => {
    const option = event.target.closest('[role=option]');
    if (option && Number(option.dataset.index) !== active) setActive(Number(option.dataset.index), false);
  });
  list.addEventListener('click', event => {
    const option = event.target.closest('[role=option]');
    if (option) run(items[Number(option.dataset.index)], event.metaKey || event.ctrlKey);
  });
  dialog.addEventListener('cancel', event => {
    event.preventDefault();
    close();
  });
  dialog.addEventListener('click', event => {
    if (event.target === dialog) close();
  });
  document.querySelectorAll('[data-command-open]').forEach(button => button.addEventListener('click', () => open()));

  // Global shortcuts. Single keys and sequences never fire while typing or with a modifier held.
  const typing = target => target.closest?.('input:not([type=checkbox]):not([type=radio]), textarea, select, [contenteditable]:not([contenteditable=false])');
  const sequences = new Map(commands.filter(command => command.keys && !command.keys.includes('Mod')).map(command => [command.keys.toLowerCase(), command]));
  let prefix = '';
  let prefixTimer;
  document.addEventListener('keydown', event => {
    const mod = isMac ? event.metaKey : event.ctrlKey;
    const key = event.key.toLowerCase();
    if (mod && key === 'k' && !event.shiftKey && !event.altKey) {
      event.preventDefault();
      if (dialog.open) close();
      else open();
      return;
    }
    // ⌘↵ saves the form being edited (guided forms handle their own composer).
    if (mod && event.key === 'Enter' && !dialog.open) {
      const form = event.target.closest?.('form');
      if (form && form.method === 'post' && !form.closest('.agent, .guided')) {
        event.preventDefault();
        form.requestSubmit();
      }
      return;
    }
    if (dialog.open || event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey || typing(event.target)) return;
    if (document.querySelector('dialog[open]')) return;
    if (event.key === '?') {
      event.preventDefault();
      open('help');
      return;
    }
    if (event.key === '/') {
      event.preventDefault();
      const field = document.querySelector('[data-shortcut-search]');
      if (field) field.focus();
      else open();
      return;
    }
    const combo = prefix ? `${prefix} ${key}` : key;
    clearTimeout(prefixTimer);
    prefix = '';
    if (sequences.has(combo)) {
      event.preventDefault();
      run(sequences.get(combo));
    } else if ([...sequences.keys()].some(keys => keys.startsWith(`${combo} `))) {
      prefix = combo;
      prefixTimer = setTimeout(() => { prefix = ''; }, SEQUENCE_MS);
    }
  });
})();
