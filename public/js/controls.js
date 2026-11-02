// Small enhancements shared by every page (app and login): tooltips on icon-only controls, show/hide on password
// fields, custom recent-search suggestions instead of the browser's history, clickable table rows and per-page
// selectors that apply on change. All progressive: without JS the controls keep their native behaviour.
(() => {
  const sprite = () => document.querySelector('use[href*="hugeicons.svg"]')?.getAttribute('href').split('#')[0] || '/icons/hugeicons.svg';
  const icon = name => `<svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="${sprite()}#${name}"/></svg>`;
  const INTERACTIVE = 'a, button, input, select, textarea, label, summary, [role=button], [contenteditable]';

  // Tooltips: any control with data-tooltip or a title, and icon-only buttons/links named by aria-label. The native
  // title is moved aside so the browser's own tooltip never doubles up; the text stays the accessible name.
  const tooltip = document.createElement('div');
  tooltip.className = 'tooltip';
  tooltip.setAttribute('aria-hidden', 'true');
  let tipTarget = null;
  let tipTimer;
  const tipText = element => element.dataset.tooltip || element.getAttribute('aria-label') || '';
  const tipCandidate = node => {
    const element = node?.closest?.('[data-tooltip], [title], button[aria-label], a[aria-label], [role=button][aria-label]');
    // Form fields keep their title: validation.js reads it as the pattern message.
    if (!element || element.closest('.tooltip') || element.matches('input, select, textarea')) return null;
    if (element.hasAttribute('title')) {
      element.dataset.tooltip = element.title;
      if (!element.hasAttribute('aria-label') && !element.textContent.trim()) element.setAttribute('aria-label', element.title);
      element.removeAttribute('title');
    }
    // A labelled control that already shows its text needs no tooltip (icons, badges and sr-only text don't count).
    const visibleText = [...element.childNodes].filter(node => node.nodeType === Node.TEXT_NODE || !node.matches('svg, .sr-only, [class*=badge], [aria-hidden=true]')).map(node => node.textContent).join('').trim();
    if (!element.dataset.tooltip && visibleText) return null;
    return tipText(element) ? element : null;
  };
  const place = () => {
    if (!tipTarget?.isConnected) return hideTip();
    const box = tipTarget.getBoundingClientRect();
    const tip = tooltip.getBoundingClientRect();
    const above = box.top - tip.height - 8;
    const top = above >= 8 ? above : box.bottom + 8;
    const left = Math.min(Math.max(8, box.left + box.width / 2 - tip.width / 2), innerWidth - tip.width - 8);
    tooltip.style.translate = `${Math.round(left)}px ${Math.round(top)}px`;
    tooltip.dataset.side = above >= 8 ? 'top' : 'bottom';
  };
  const showTip = (element, delay) => {
    clearTimeout(tipTimer);
    tipTimer = setTimeout(() => {
      tipTarget = element;
      tooltip.textContent = tipText(element);
      if (!tooltip.isConnected) document.body.append(tooltip);
      place();
      tooltip.classList.add('is-visible');
    }, delay);
  };
  function hideTip() {
    clearTimeout(tipTimer);
    tipTarget = null;
    tooltip.classList.remove('is-visible');
  }
  document.addEventListener('pointerover', event => {
    if (event.pointerType === 'touch') return;
    const element = tipCandidate(event.target);
    if (element && element !== tipTarget) showTip(element, tooltip.classList.contains('is-visible') ? 0 : 400);
    else if (!element) hideTip();
  });
  document.addEventListener('focusin', event => {
    const element = event.target.matches?.(':focus-visible') && tipCandidate(event.target);
    if (element) showTip(element, 150); else hideTip();
  });
  ['pointerdown', 'focusout', 'scroll'].forEach(type => document.addEventListener(type, hideTip, true));
  document.addEventListener('keydown', event => { if (event.key === 'Escape') hideTip(); });

  // Password fields get a show/hide toggle; the field returns to hidden before its form submits.
  const enhancePassword = input => {
    if (input.closest('.password-field')) return;
    const wrap = document.createElement('span');
    wrap.className = 'password-field';
    input.before(wrap);
    wrap.append(input);
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle';
    button.setAttribute('aria-controls', input.id || '');
    const render = shown => {
      button.setAttribute('aria-pressed', String(shown));
      button.setAttribute('aria-label', shown ? 'Ocultar contraseña' : 'Mostrar contraseña');
      button.innerHTML = icon(shown ? 'eye-off' : 'eye');
    };
    render(false);
    button.addEventListener('click', () => {
      const shown = input.type === 'password';
      input.type = shown ? 'text' : 'password';
      render(shown);
      if (tipTarget === button) tooltip.textContent = tipText(button);
      input.focus({ preventScroll: true });
    });
    input.form?.addEventListener('submit', () => { input.type = 'password'; render(false); });
    wrap.append(button);
  };

  // Recent searches: the browser's own history list is turned off and replaced with the last searches made from
  // this page (localStorage), shown as an accessible combobox under the field.
  const RECENT_MAX = 6;
  let comboCount = 0;
  const enhanceSearch = input => {
    input.setAttribute('autocomplete', 'off');
    const key = `recent:${location.pathname}:${input.name}`;
    const read = () => { try { return JSON.parse(localStorage.getItem(key) || '[]'); } catch { return []; } };
    const write = list => { try { localStorage.setItem(key, JSON.stringify(list.slice(0, RECENT_MAX))); } catch { /* optional */ } };
    const id = `recent-${++comboCount}`;
    const list = document.createElement('div');
    list.className = 'recent-list t-dropdown';
    list.id = id;
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', 'Búsquedas recientes');
    list.hidden = true;
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', id);
    input.setAttribute('aria-expanded', 'false');
    let active = -1;
    let options = [];

    const position = () => {
      const box = (input.closest('.inline-filter, .ai-search-bar') || input).getBoundingClientRect();
      list.style.translate = `${Math.round(box.left)}px ${Math.round(box.bottom + 6)}px`;
      list.style.minWidth = `${Math.round(box.width)}px`;
    };
    const close = () => {
      list.classList.remove('is-open');
      list.hidden = true;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      active = -1;
    };
    const setActive = index => {
      active = index;
      options.forEach((option, i) => option.setAttribute('aria-selected', String(i === index)));
      if (options[index]) input.setAttribute('aria-activedescendant', options[index].id);
      else input.removeAttribute('aria-activedescendant');
    };
    const pick = value => {
      input.value = value;
      close();
      input.form?.requestSubmit();
    };
    const open = () => {
      const typed = input.value.trim().toLowerCase();
      const items = read().filter(item => item.toLowerCase() !== typed && item.toLowerCase().includes(typed));
      if (!items.length) return close();
      list.innerHTML = `<p class="recent-head">${icon('clock')} Búsquedas recientes</p>`;
      options = items.map((item, index) => {
        const option = document.createElement('div');
        option.className = 'recent-option';
        option.id = `${id}-${index}`;
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', 'false');
        option.innerHTML = `${icon('search')}<span></span>`;
        option.querySelector('span').textContent = item;
        option.addEventListener('pointerdown', event => { event.preventDefault(); pick(item); });
        list.append(option);
        return option;
      });
      const clear = document.createElement('button');
      clear.type = 'button';
      clear.className = 'recent-clear';
      clear.textContent = 'Borrar recientes';
      clear.addEventListener('pointerdown', event => { event.preventDefault(); write([]); close(); });
      list.append(clear);
      if (!list.isConnected) document.body.append(list);
      position();
      list.hidden = false;
      requestAnimationFrame(() => list.classList.add('is-open'));
      input.setAttribute('aria-expanded', 'true');
      setActive(-1);
    };
    input.addEventListener('focus', open);
    input.addEventListener('input', open);
    input.addEventListener('blur', close);
    input.addEventListener('keydown', event => {
      if (list.hidden) {
        if (event.key === 'ArrowDown') { open(); event.preventDefault(); }
        return;
      }
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault();
        const step = event.key === 'ArrowDown' ? 1 : -1;
        // The field itself is one more stop in the cycle (index -1), so arrowing past the ends returns to typing.
        const total = options.length + 1;
        setActive((active + 1 + step + total) % total - 1);
      } else if (event.key === 'Enter' && active >= 0) {
        event.preventDefault();
        pick(options[active].querySelector('span').textContent);
      } else if (event.key === 'Escape') {
        event.preventDefault();
        event.stopPropagation();
        close();
      }
    });
    addEventListener('resize', () => { if (!list.hidden) position(); });
    document.addEventListener('scroll', () => { if (!list.hidden) position(); }, true);
    input.form?.addEventListener('submit', () => {
      const value = input.value.trim();
      if (value.length >= 2) write([value, ...read().filter(item => item.toLowerCase() !== value.toLowerCase())]);
    });
  };

  // Table rows with data-href open on a click anywhere in the row; Cmd/Ctrl-click and middle click open a new tab.
  // The row's own link stays the keyboard and screen-reader path, so it is reused for plain clicks.
  const rowTarget = event => {
    const row = event.target.closest('tr[data-href]');
    if (!row || event.target.closest(INTERACTIVE) || getSelection().toString()) return null;
    return row;
  };
  document.addEventListener('click', event => {
    const row = rowTarget(event);
    if (!row) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey) return void open(row.dataset.href, '_blank', 'noopener');
    const link = [...row.querySelectorAll('a[href]')].find(anchor => anchor.href === new URL(row.dataset.href, location.href).href);
    if (link) link.click(); else location.assign(row.dataset.href);
  });
  document.addEventListener('auxclick', event => {
    const row = event.button === 1 && rowTarget(event);
    if (row) open(row.dataset.href, '_blank', 'noopener');
  });

  const enhance = scope => {
    scope.querySelectorAll('input[type=password]').forEach(enhancePassword);
    scope.querySelectorAll('input[type=search][name], input[data-recent]').forEach(enhanceSearch);
    // Fields without a declared purpose never show the browser's history of past entries.
    scope.querySelectorAll('input:not([autocomplete]):not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea:not([autocomplete])').forEach(field => field.setAttribute('autocomplete', 'off'));
    scope.querySelectorAll('select[data-autosubmit]').forEach(select => select.addEventListener('change', () => select.form?.requestSubmit()));
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => enhance(document)); else enhance(document);
})();
