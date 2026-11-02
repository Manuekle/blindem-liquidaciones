// Custom selects, date pickers and confirmations. Native controls stay in the form (hidden), so submissions,
// `[name]` lookups and change listeners elsewhere keep working with real values.
const iconSprite = () => document.querySelector('use[href*="hugeicons.svg"]')?.getAttribute('href').split('#')[0] || '/icons/hugeicons.svg';
const icon = name => `<svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="${iconSprite()}#${name}"/></svg>`;
const normalize = text => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
const notify = control => {
  control.dispatchEvent(new Event('input', { bubbles: true }));
  control.dispatchEvent(new Event('change', { bubbles: true }));
};
const hideNative = (control, id) => {
  control.id = `${id}-native`;
  control.tabIndex = -1;
  control.setAttribute('aria-hidden', 'true');
  control.classList.add('enhanced-native');
};
let controlCount = 0;

// Durations live in CSS (`:root` motion tokens) so JS cleanup timers stay in sync with the transitions.
const motionMs = name => parseFloat(getComputedStyle(document.documentElement).getPropertyValue(name)) || 0;

const popups = new Set();
const placePopup = (panel, anchor, matchWidth) => {
  const rect = anchor.getBoundingClientRect();
  if (matchWidth) panel.style.width = `${rect.width}px`;
  const below = window.innerHeight - rect.bottom;
  const up = below < panel.offsetHeight + 12 && rect.top > below;
  panel.dataset.origin = up ? 'bottom-left' : 'top-left';
  panel.style.top = `${Math.max(8, up ? rect.top - panel.offsetHeight - 4 : rect.bottom + 4)}px`;
  panel.style.left = `${Math.max(8, Math.min(rect.left, window.innerWidth - panel.offsetWidth - 8))}px`;
};
// Panels grow from their trigger; keyboard opens skip the entrance (it is repeated often), closes always fade out.
const showPopup = (popup, instant) => {
  const { panel } = popup;
  clearTimeout(panel.hideTimer);
  panel.classList.remove('is-closing', 'is-open');
  panel.classList.toggle('is-instant', instant);
  panel.hidden = false;
  popups.add(popup);
  popup.place();
  void panel.offsetWidth;
  panel.classList.add('is-open');
};
const hidePopup = popup => {
  const { panel } = popup;
  popups.delete(popup);
  panel.classList.remove('is-open', 'is-instant');
  panel.classList.add('is-closing');
  panel.hideTimer = setTimeout(() => {
    panel.hidden = true;
    panel.classList.remove('is-closing');
  }, motionMs('--dropdown-close-dur'));
};
// `detail` is 0 for clicks synthesised from Enter/Space on a button.
const fromKeyboard = event => !event || event.type === 'keydown' || event.detail === 0;
const closePopups = () => [...popups].forEach(popup => popup.close(false));
document.addEventListener('pointerdown', event => {
  for (const popup of [...popups]) if (!popup.owner.contains(event.target) && !popup.panel.contains(event.target)) popup.close(false);
});
window.addEventListener('scroll', event => popups.forEach(popup => { if (!popup.panel.contains(event.target)) popup.place(); }), true);
window.addEventListener('resize', () => popups.forEach(popup => popup.place()));

const enhanceSelect = select => {
  const id = select.id || `select-${++controlCount}`;
  const wrap = document.createElement('div');
  wrap.className = 'custom-select';
  select.before(wrap);
  wrap.append(select);
  hideNative(select, id);
  const label = document.querySelector(`label[for="${id}"]`);
  const name = select.getAttribute('aria-label') || label?.firstChild?.textContent.replace('*', '').trim() || '';

  const trigger = document.createElement('button');
  trigger.type = 'button';
  trigger.id = id;
  trigger.className = 'cs-trigger';
  trigger.setAttribute('role', 'combobox');
  trigger.setAttribute('aria-haspopup', 'listbox');
  trigger.setAttribute('aria-expanded', 'false');
  trigger.setAttribute('aria-controls', `${id}-list`);
  if (select.hasAttribute('aria-label')) trigger.setAttribute('aria-label', select.getAttribute('aria-label'));
  trigger.innerHTML = `<span class="cs-value"></span>${icon('chevron-down')}`;
  wrap.append(trigger);
  select.visualControl = trigger;

  const panel = document.createElement('div');
  panel.className = 'cs-panel t-dropdown';
  panel.hidden = true;
  const list = document.createElement('ul');
  list.id = `${id}-list`;
  list.className = 'cs-list';
  list.setAttribute('role', 'listbox');
  list.setAttribute('aria-label', name);
  let search = null;
  if (select.options.length > 8) {
    search = document.createElement('input');
    search.type = 'search';
    search.className = 'cs-search';
    search.placeholder = 'Buscar…';
    search.autocomplete = 'off';
    search.setAttribute('aria-label', `Buscar ${name.toLowerCase()}`.trim());
    search.setAttribute('aria-controls', list.id);
    panel.append(search);
  }
  panel.append(list);
  document.body.append(panel);

  let items = [];
  let active = -1;
  const focusTarget = () => search || trigger;
  const sync = () => {
    const option = select.selectedOptions[0];
    trigger.querySelector('.cs-value').textContent = option?.textContent ?? '';
    trigger.classList.toggle('is-placeholder', !option || option.value === '');
    trigger.disabled = select.disabled;
  };
  const render = (query = '') => {
    const needle = normalize(query);
    list.replaceChildren();
    items = [];
    [...select.options].forEach((option, index) => {
      if (option.hidden || (needle && !normalize(option.textContent).includes(needle))) return;
      const item = document.createElement('li');
      item.id = `${id}-option-${index}`;
      item.dataset.index = index;
      item.setAttribute('role', 'option');
      item.setAttribute('aria-selected', String(option.selected));
      if (option.disabled) item.setAttribute('aria-disabled', 'true');
      if (option.value === '') item.classList.add('is-placeholder');
      item.textContent = option.textContent;
      list.append(item);
      items.push(item);
    });
    if (!items.length) list.innerHTML = '<li class="cs-empty" role="presentation">Sin resultados</li>';
  };
  const setActive = index => {
    items[active]?.classList.remove('is-active');
    active = Math.max(0, Math.min(items.length - 1, index));
    const item = items[active];
    if (!item) return focusTarget().removeAttribute('aria-activedescendant');
    item.classList.add('is-active');
    item.scrollIntoView({ block: 'nearest' });
    focusTarget().setAttribute('aria-activedescendant', item.id);
  };
  const popup = { owner: wrap, panel, place: () => placePopup(panel, trigger, true), close: restoreFocus => close(restoreFocus) };
  const open = event => {
    if (select.disabled || popups.has(popup)) return;
    closePopups();
    render();
    trigger.setAttribute('aria-expanded', 'true');
    showPopup(popup, fromKeyboard(event));
    setActive(items.findIndex(item => select.options[item.dataset.index].selected));
    if (search) {
      search.value = '';
      search.focus();
    }
  };
  const close = (restoreFocus = true) => {
    if (!popups.has(popup)) return;
    hidePopup(popup);
    trigger.setAttribute('aria-expanded', 'false');
    focusTarget().removeAttribute('aria-activedescendant');
    if (restoreFocus) trigger.focus();
  };
  const choose = item => {
    const option = select.options[item?.dataset.index];
    if (!option || option.disabled) return;
    const changed = !option.selected;
    option.selected = true;
    sync();
    close();
    if (changed) notify(select);
  };
  let typed = '';
  let typedAt = 0;
  const typeahead = key => {
    typed = Date.now() - typedAt > 700 ? key : typed + key;
    typedAt = Date.now();
    open({ type: 'keydown' });
    const match = items.findIndex(item => normalize(item.textContent).startsWith(normalize(typed)));
    if (match >= 0) setActive(match);
  };
  const onKey = event => {
    const isOpen = popups.has(popup);
    switch (event.key) {
      case 'ArrowDown':
      case 'ArrowUp':
        event.preventDefault();
        if (!isOpen) return open(event);
        return setActive(active + (event.key === 'ArrowDown' ? 1 : -1));
      case 'Home':
      case 'End':
        if (!isOpen || event.target === search) return;
        event.preventDefault();
        return setActive(event.key === 'Home' ? 0 : items.length - 1);
      case 'Enter':
      case ' ':
        if (event.key === ' ' && event.target === search) return;
        event.preventDefault();
        return isOpen ? choose(items[active]) : open(event);
      case 'Escape':
        if (!isOpen) return;
        event.preventDefault();
        event.stopPropagation();
        return close();
      case 'Tab':
        // Focus returns to the trigger first so Tab continues from the field, not from the panel at the end of <body>.
        return close();
      default:
        if (event.target === trigger && event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
          event.preventDefault();
          if (search) {
            open(event);
            search.value = event.key;
            render(search.value);
            setActive(0);
          } else typeahead(event.key);
        }
    }
  };
  trigger.addEventListener('click', event => (popups.has(popup) ? close() : open(event)));
  trigger.addEventListener('keydown', onKey);
  search?.addEventListener('keydown', onKey);
  search?.addEventListener('input', () => {
    render(search.value);
    setActive(0);
  });
  list.addEventListener('pointermove', event => {
    const index = items.indexOf(event.target.closest('[role=option]'));
    if (index >= 0 && index !== active) setActive(index);
  });
  list.addEventListener('click', event => choose(event.target.closest('[role=option]')));
  select.addEventListener('change', sync);
  select.form?.addEventListener('reset', () => setTimeout(sync));
  sync();
};

const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const WEEKDAYS = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
const pad = number => String(number).padStart(2, '0');
const toIso = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
const toDisplay = date => `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear()}`;
const buildDate = (year, month, day) => {
  const date = new Date(year, month - 1, day);
  return year >= 1900 && date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day ? date : null;
};
const fromIso = value => {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
  return match ? buildDate(+match[1], +match[2], +match[3]) : null;
};
const fromDisplay = value => {
  const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value || '');
  return match ? buildDate(+match[3], +match[2], +match[1]) : null;
};
const sameDay = (a, b) => a && b && toIso(a) === toIso(b);
const shiftMonths = (date, months) => {
  const target = new Date(date.getFullYear(), date.getMonth() + months, 1);
  target.setDate(Math.min(date.getDate(), new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate()));
  return target;
};

const enhanceDate = native => {
  const id = native.id || `date-${++controlCount}`;
  let wrap = native.closest('.date-input');
  if (!wrap) {
    wrap = document.createElement('span');
    wrap.className = 'date-input';
    native.before(wrap);
    wrap.append(native);
  }
  hideNative(native, id);
  wrap.querySelector(':scope > .icon')?.remove();

  const text = document.createElement('input');
  text.type = 'text';
  text.id = id;
  text.className = 'date-text';
  text.inputMode = 'numeric';
  text.autocomplete = 'off';
  text.placeholder = 'dd/mm/aaaa';
  text.maxLength = 10;
  text.setAttribute('aria-haspopup', 'dialog');
  native.before(text);
  native.visualControl = text;

  const toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'date-toggle';
  toggle.setAttribute('aria-label', 'Abrir calendario');
  toggle.setAttribute('aria-expanded', 'false');
  toggle.innerHTML = icon('calendar-days');
  wrap.append(toggle);

  const panel = document.createElement('div');
  panel.className = 'dp-panel t-dropdown';
  panel.hidden = true;
  panel.setAttribute('role', 'dialog');
  panel.setAttribute('aria-label', 'Elegir fecha');
  document.body.append(panel);

  let cursor = new Date();
  let view = 'days';
  const selected = () => fromIso(native.value);
  const sync = () => {
    const date = selected();
    text.value = date ? toDisplay(date) : '';
    text.setCustomValidity('');
    text.disabled = toggle.disabled = native.disabled;
  };
  const setValue = iso => {
    if (native.value === iso) return;
    native.value = iso;
    notify(native);
  };
  const render = () => {
    const today = new Date();
    const title = view === 'days' ? `${MONTHS[cursor.getMonth()]} ${cursor.getFullYear()}` : String(cursor.getFullYear());
    const step = view === 'days' ? 'mes' : 'año';
    let body = '';
    if (view === 'days') {
      const first = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
      const start = new Date(first);
      start.setDate(1 - ((first.getDay() + 6) % 7));
      body = WEEKDAYS.map(day => `<span class="dp-weekday" aria-hidden="true">${day[0].toUpperCase()}</span>`).join('');
      for (let offset = 0; offset < 42; offset++) {
        const day = new Date(start);
        day.setDate(start.getDate() + offset);
        const classes = ['dp-day', day.getMonth() !== cursor.getMonth() && 'is-outside'].filter(Boolean).join(' ');
        body += `<button type="button" class="${classes}" data-date="${toIso(day)}" tabindex="${sameDay(day, cursor) ? 0 : -1}" aria-pressed="${sameDay(day, selected())}"${sameDay(day, today) ? ' aria-current="date"' : ''} aria-label="${WEEKDAYS[(day.getDay() + 6) % 7]} ${day.getDate()} de ${MONTHS[day.getMonth()]} de ${day.getFullYear()}">${day.getDate()}</button>`;
      }
    } else {
      const current = selected();
      body = MONTHS.map((month, index) => {
        const isCurrent = current && current.getFullYear() === cursor.getFullYear() && current.getMonth() === index;
        return `<button type="button" class="dp-month" data-month="${index}" tabindex="${index === cursor.getMonth() ? 0 : -1}" aria-pressed="${Boolean(isCurrent)}" aria-label="${month} de ${cursor.getFullYear()}">${month.slice(0, 3)}</button>`;
      }).join('');
    }
    panel.innerHTML = `<div class="dp-head"><button type="button" class="dp-nav" data-step="-1" aria-label="${step === 'mes' ? 'Mes' : 'Año'} anterior">${icon('chevron-left')}</button><button type="button" class="dp-title" aria-live="polite"${view === 'days' ? ' aria-label="Elegir mes y año, ' + title + '"' : ''}>${title}</button><button type="button" class="dp-nav" data-step="1" aria-label="${step === 'mes' ? 'Mes' : 'Año'} siguiente">${icon('chevron-right')}</button></div><div class="dp-grid ${view === 'days' ? 'dp-days' : 'dp-months'}">${body}</div><div class="dp-foot"><button type="button" class="dp-link subtle" data-action="clear">Borrar</button><button type="button" class="dp-link" data-action="today">Hoy</button></div>`;
  };
  const focusCursor = () => panel.querySelector('.dp-grid [tabindex="0"]')?.focus();
  const popup = { owner: wrap, panel, place: () => placePopup(panel, wrap, false), close: restoreFocus => close(restoreFocus) };
  const open = (focusGrid = true, event) => {
    if (native.disabled) return;
    if (popups.has(popup)) return focusGrid && focusCursor();
    closePopups();
    cursor = selected() || fromDisplay(text.value) || new Date();
    view = 'days';
    render();
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', 'Cerrar calendario');
    showPopup(popup, fromKeyboard(event));
    if (focusGrid) focusCursor();
  };
  const close = (restoreFocus = true) => {
    if (!popups.has(popup)) return;
    hidePopup(popup);
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Abrir calendario');
    if (restoreFocus) text.focus();
  };
  const choose = date => {
    setValue(date ? toIso(date) : '');
    sync();
    close();
  };
  const moveCursor = (date, keepFocus = true) => {
    cursor = date;
    render();
    popup.place();
    if (keepFocus) focusCursor();
  };

  text.addEventListener('input', event => {
    // Separators are inserted while typing digits; deletions are left as typed.
    if (!event.inputType?.startsWith('delete')) {
      const digits = text.value.replace(/\D/g, '').slice(0, 8);
      text.value = [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4)].filter(Boolean).join('/');
    }
    const date = fromDisplay(text.value);
    text.setCustomValidity(text.value && !date ? 'Fecha inválida. Usa el formato dd/mm/aaaa.' : '');
    setValue(date ? toIso(date) : '');
    if (date && popups.has(popup)) moveCursor(date, false);
  });
  text.addEventListener('click', event => open(false, event));
  text.addEventListener('keydown', event => {
    if (event.key === 'ArrowDown' && (event.altKey || popups.has(popup))) {
      event.preventDefault();
      open(true, event);
    } else if (event.key === 'Escape' && popups.has(popup)) {
      event.preventDefault();
      event.stopPropagation();
      close();
    } else if (event.key === 'Tab') close(false);
  });
  toggle.addEventListener('click', event => (popups.has(popup) ? close() : open(true, event)));
  panel.addEventListener('click', event => {
    const button = event.target.closest('button');
    if (!button) return;
    if (button.dataset.date) return choose(fromIso(button.dataset.date));
    if (button.dataset.month) {
      view = 'days';
      return moveCursor(new Date(cursor.getFullYear(), +button.dataset.month, Math.min(cursor.getDate(), 28)));
    }
    if (button.dataset.step) {
      const step = +button.dataset.step;
      moveCursor(shiftMonths(cursor, view === 'days' ? step : step * 12), false);
      return panel.querySelector(`.dp-nav[data-step="${step}"]`)?.focus();
    }
    if (button.classList.contains('dp-title')) {
      view = view === 'days' ? 'months' : 'days';
      return moveCursor(cursor);
    }
    if (button.dataset.action === 'today') return choose(new Date());
    if (button.dataset.action === 'clear') choose(null);
  });
  panel.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopPropagation();
      return close();
    }
    if (event.key === 'Tab') {
      // Keep keyboard focus inside the open calendar.
      const focusable = [...panel.querySelectorAll('button:not([tabindex="-1"])')];
      const index = focusable.indexOf(document.activeElement);
      event.preventDefault();
      return focusable[(index + (event.shiftKey ? -1 : 1) + focusable.length) % focusable.length]?.focus();
    }
    if (!event.target.matches('.dp-day, .dp-month')) return;
    const days = view === 'days';
    const moves = {
      ArrowLeft: () => (days ? new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() - 1) : shiftMonths(cursor, -1)),
      ArrowRight: () => (days ? new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + 1) : shiftMonths(cursor, 1)),
      ArrowUp: () => (days ? new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() - 7) : shiftMonths(cursor, -3)),
      ArrowDown: () => (days ? new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + 7) : shiftMonths(cursor, 3)),
      PageUp: () => shiftMonths(cursor, days && !event.shiftKey ? -1 : -12),
      PageDown: () => shiftMonths(cursor, days && !event.shiftKey ? 1 : 12),
      Home: () => (days ? new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() - ((cursor.getDay() + 6) % 7)) : shiftMonths(cursor, -cursor.getMonth())),
      End: () => (days ? new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + 6 - ((cursor.getDay() + 6) % 7)) : shiftMonths(cursor, 11 - cursor.getMonth())),
    };
    if (!moves[event.key]) return;
    event.preventDefault();
    moveCursor(moves[event.key]());
  });
  native.addEventListener('change', () => {
    if (document.activeElement !== text) sync();
  });
  native.form?.addEventListener('reset', () => setTimeout(sync));
  sync();
};

const confirmAction = (message, action, title = 'Confirmar eliminación') => new Promise(resolve => {
  const dialog = document.createElement('dialog');
  const key = `confirm-${++controlCount}`;
  dialog.className = 'confirm-dialog t-modal';
  dialog.setAttribute('aria-labelledby', `${key}-title`);
  dialog.setAttribute('aria-describedby', `${key}-message`);
  dialog.innerHTML = `<form method="dialog"><span class="confirm-icon">${icon('trash-2')}</span><h2 id="${key}-title">Confirmar eliminación</h2><p id="${key}-message"></p><div class="confirm-actions"><button class="btn ghost" value="cancel" autofocus>Cancelar</button><button class="btn destructive" value="confirm"></button></div></form>`;
  dialog.querySelector('h2').textContent = title;
  dialog.querySelector('p').textContent = message;
  dialog.querySelector('[value=confirm]').textContent = action;
  // Every exit path (buttons, Escape, backdrop) plays the close transition before the dialog really closes.
  const dismiss = value => {
    if (dialog.classList.contains('is-closing')) return;
    dialog.classList.remove('is-open');
    dialog.classList.add('is-closing');
    setTimeout(() => dialog.close(value), motionMs('--modal-close-dur'));
  };
  dialog.querySelector('form').addEventListener('submit', event => {
    event.preventDefault();
    dismiss(event.submitter?.value || 'cancel');
  });
  dialog.addEventListener('cancel', event => {
    event.preventDefault();
    dismiss('cancel');
  });
  dialog.addEventListener('click', event => {
    if (event.target === dialog) dismiss('cancel');
  });
  dialog.addEventListener('close', () => {
    resolve(dialog.returnValue === 'confirm');
    dialog.remove();
  });
  document.body.append(dialog);
  dialog.showModal();
  void dialog.offsetWidth;
  dialog.classList.add('is-open');
});

// Runs before any form listener, so the loading state only starts once the action is confirmed.
document.addEventListener('submit', event => {
  const form = event.target;
  if (!form.dataset.confirm || form.dataset.confirmed) return;
  event.preventDefault();
  event.stopPropagation();
  confirmAction(form.dataset.confirm, form.dataset.confirmAction || 'Eliminar', form.dataset.confirmTitle).then(confirmed => {
    if (!confirmed) return;
    form.dataset.confirmed = '1';
    form.requestSubmit(event.submitter);
    delete form.dataset.confirmed;
  });
}, true);

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('select:not([multiple])').forEach(enhanceSelect);
  document.querySelectorAll('input[type="date"]').forEach(enhanceDate);
  const parameterData = document.querySelector('#labor-parameters-data');
  if (parameterData) {
    const parameters = JSON.parse(parameterData.textContent);
    const end = document.querySelector('[name="end_date"]');
    const salary = document.querySelector('[name="basic_salary"]');
    const transport = document.querySelector('[name="transport_allowance"]');
    const reason = document.querySelector('[name="termination_reason_id"]');
    const status = document.querySelector('[name="status"]');
    const type = document.querySelector('[name="contract_type_id"]');
    const manual = document.querySelector('[name="manual_indemnification"]');
    const agreedEnd = document.querySelector('[name="agreed_end_date"]');
    for (const field of [salary, transport]) {
      field.dataset.auto = parameterData.dataset.new;
      // Failed submissions and user-entered values must not be overwritten.
      if (field.value !== field.defaultValue) field.dataset.auto = '0';
      field.addEventListener('input', () => { field.dataset.auto = '0'; });
    }
    const updateYear = (fill = false) => {
      const year = end.value.slice(0, 4) || parameterData.dataset.year;
      const values = parameters[year];
      const reference = document.querySelector('#labor-reference');
      reference.textContent = values
        ? `Vigencia ${year}. Salario mínimo: $${Number(values.min_wage).toLocaleString('es-CO')}. Auxilio de referencia: $${Number(values.transport_allowance).toLocaleString('es-CO')}.`
        : `No hay parámetros para ${year}. Solicita al administrador registrar esa vigencia antes de liquidar.`;
      if (fill && values) {
        if (salary.dataset.auto === '1') salary.value = values.min_wage;
        if (transport.dataset.auto === '1') transport.value = values.transport_allowance;
      }
    };
    const updateIndemnification = () => {
      const applies = status.value === 'terminated' && reason.selectedOptions[0]?.dataset.indemnifies === '1';
      const method = type.selectedOptions[0]?.dataset.method;
      document.querySelector('#indemnification-rule').textContent = applies
        ? 'El motivo de retiro seleccionado requiere indemnización.'
        : 'El estado y motivo seleccionados no dan lugar a indemnización.';
      manual.required = applies && ['manual', 'obra'].includes(method);
      agreedEnd.required = method === 'fijo';
    };
    // Mirrors LaborSettlementCalculator::days360 so blank fields preview the value the server will use.
    const days360 = (from, to) => {
      const [y1, m1, d1] = from.split('-').map(Number);
      const [y2, m2, d2] = to.split('-').map(Number);
      const lastFeb = m2 === 2 && d2 === new Date(y2, 2, 0).getDate();
      return Math.max(1, (y2 - y1) * 360 + (m2 - m1) * 30 + (Math.min(30, lastFeb ? 30 : d2) - Math.min(30, d1)) + 1);
    };
    const start = document.querySelector('[name="start_date"]');
    const taken = document.querySelector('[name="vacation_days_taken"]');
    const autoFields = document.querySelectorAll('[data-auto-days]');
    const updateAutoDays = () => {
      const cut = end.value || new Date().toISOString().slice(0, 10);
      if (!start.value || cut < start.value) {
        autoFields.forEach(field => { field.placeholder = 'Auto'; });
        return;
      }
      const later = (a, b) => (a > b ? a : b);
      const month = Number(cut.slice(5, 7));
      const salaryDays = Math.min(30, days360(later(start.value, `${cut.slice(0, 8)}01`), cut));
      const values = {
        cesantias: Math.min(360, days360(later(start.value, `${cut.slice(0, 4)}-01-01`), cut)),
        prima: Math.min(180, days360(later(start.value, `${cut.slice(0, 4)}-${month <= 6 ? '01' : '07'}-01`), cut)),
        vacation: Math.max(0, Math.round((days360(start.value, cut) * 15 / 360 - Number(taken.value || 0)) * 100) / 100),
        salary: salaryDays,
        transport: salaryDays,
      };
      autoFields.forEach(field => { field.placeholder = `Auto · ${values[field.dataset.autoDays].toLocaleString('es-CO')}`; });
    };
    end.addEventListener('change', () => updateYear(true));
    for (const field of [start, end, taken]) field.addEventListener('input', updateAutoDays);
    for (const field of [reason, status, type]) field.addEventListener('change', updateIndemnification);
    updateYear();
    updateIndemnification();
    updateAutoDays();
  }
  // Theme: a saved choice wins; without one the page follows the system preference live.
  const themeToggle = document.querySelector('#theme-toggle');
  const systemDark = window.matchMedia('(prefers-color-scheme: dark)');
  const savedTheme = () => {
    try { return localStorage.getItem('theme'); } catch (error) { return null; }
  };
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const applyTheme = theme => {
    const root = document.documentElement;
    const dark = theme === 'dark';
    themeToggle?.setAttribute('aria-pressed', String(dark));
    themeToggle?.querySelector('.t-icon-swap')?.setAttribute('data-state', dark ? 'b' : 'a');
    if (root.dataset.theme === theme) return;
    const swap = () => {
      root.classList.add('theme-switching');
      root.dataset.theme = theme;
      void root.offsetWidth;
      setTimeout(() => root.classList.remove('theme-switching'));
    };
    // The old and new page cross-fade; browsers without View Transitions switch instantly.
    if (document.startViewTransition && !reducedMotion.matches) document.startViewTransition(swap);
    else swap();
  };
  applyTheme(document.documentElement.dataset.theme || 'light');
  themeToggle?.addEventListener('click', () => {
    const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('theme', theme); } catch (error) {}
    applyTheme(theme);
  });
  systemDark.addEventListener('change', event => {
    if (!savedTheme()) applyTheme(event.matches ? 'dark' : 'light');
  });

  // Page navigation: with cross-document View Transitions the browser cross-fades both documents by itself (CSS), so
  // this only covers the rest — fade the body out before following a link, then fade it back in on arrival.
  const pageBody = document.querySelector('#main-content, .login-shell');
  const pageTransitions = window.pageTransitions || {};
  if (pageBody && !pageTransitions.crossDoc && !reducedMotion.matches) {
    document.addEventListener('click', event => {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      const link = event.target.closest('a[href]');
      if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
      const url = new URL(link.href, location.href);
      if (url.origin !== location.origin) return;
      // Same-document anchors only scroll, and links to this same page (sorting, pagination, tabs) update it in place:
      // neither fades the page out.
      if (url.pathname === location.pathname) return;
      event.preventDefault();
      pageBody.classList.add('is-leaving');
      // If the navigation never commits (a download, a blocked request) the page must not stay invisible.
      setTimeout(() => pageBody.classList.remove('is-leaving'), 1000);
      setTimeout(() => { location.href = link.href; }, motionMs('--page-exit-dur') + 10);
    });
  }

  const menuButton = document.querySelector('#mobile-menu');
  const sidebar = document.querySelector('#sidebar');
  const backdrop = document.querySelector('#sidebar-backdrop');
  const mainArea = document.querySelector('.main-area');
  const mobile = window.matchMedia('(max-width: 1023px)');

  const setMenu = (open, restoreFocus = false) => {
    if (!sidebar || !menuButton) return;
    const isOpen = mobile.matches && open;
    sidebar.classList.toggle('open', isOpen);
    sidebar.inert = mobile.matches && !isOpen;
    menuButton.setAttribute('aria-expanded', String(isOpen));
    menuButton.setAttribute('aria-label', isOpen ? 'Cerrar menú' : 'Abrir menú');
    if (backdrop) backdrop.hidden = !isOpen;
    if (mainArea) mainArea.inert = isOpen;
    document.body.classList.toggle('sidebar-visible', isOpen);
    if (isOpen) {
      sidebar.setAttribute('role', 'dialog');
      sidebar.setAttribute('aria-modal', 'true');
      sidebar.querySelector('a')?.focus();
    } else {
      sidebar.removeAttribute('role');
      sidebar.removeAttribute('aria-modal');
      if (restoreFocus) menuButton.focus();
    }
  };
  menuButton?.addEventListener('click', () => setMenu(!sidebar.classList.contains('open')));
  backdrop?.addEventListener('click', () => setMenu(false, true));
  sidebar?.addEventListener('click', event => {
    if (event.target.closest('a')) setMenu(false);
  });
  mobile.addEventListener('change', () => setMenu(false));
  setMenu(false);
  document.addEventListener('keydown', event => {
    if (!sidebar?.classList.contains('open')) return;
    if (event.key === 'Escape') setMenu(false, true);
    if (event.key === 'Tab') {
      const links = sidebar.querySelectorAll('a[href], button:not([disabled])');
      const first = links[0], last = links[links.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last?.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first?.focus();
      }
    }
  });

  // Desktop sidebar: drag its edge (or use the arrow keys on it) to resize, the panel button or ⌘B / Ctrl+B collapses
  // it to an icon rail. Both choices persist in localStorage; the layout applies them before first paint.
  const root = document.documentElement;
  const collapseButton = document.querySelector('#sidebar-collapse');
  const resizer = document.querySelector('#sidebar-resizer');
  const SIDEBAR_MIN = 220, SIDEBAR_MAX = 400, SIDEBAR_DEFAULT = 264, SIDEBAR_SNAP = 140;
  const remember = (key, value) => { try { localStorage.setItem(key, value); } catch (error) {} };
  const isCollapsed = () => root.classList.contains('sidebar-collapsed');
  let animating;
  const setCollapsed = (collapsed, animate = true) => {
    if (animate && !reducedMotion.matches) {
      root.classList.add('sidebar-animating');
      clearTimeout(animating);
      animating = setTimeout(() => root.classList.remove('sidebar-animating'), motionMs('--duration-fast') + 50);
    }
    root.classList.toggle('sidebar-collapsed', collapsed);
    const label = collapsed ? 'Expandir menú' : 'Contraer menú';
    collapseButton?.setAttribute('aria-expanded', String(!collapsed));
    collapseButton?.setAttribute('aria-label', label);
    collapseButton?.setAttribute('title', label);
    sidebar?.querySelectorAll('.nav-links .nav-link').forEach(link => {
      const text = link.querySelector('.nav-label')?.textContent.trim();
      if (collapsed && text) link.title = text;
      else link.removeAttribute('title');
    });
    // The account button shows only the avatar in the rail, so its hidden name moves to a label.
    const account = document.querySelector('#account-trigger');
    const accountName = account?.querySelector('.account-text b')?.textContent.trim();
    ['aria-label', 'title'].forEach(attribute => {
      if (collapsed && accountName) account.setAttribute(attribute, accountName);
      else account?.removeAttribute(attribute);
    });
    remember('sidebar-collapsed', collapsed ? '1' : '0');
  };
  const setWidth = width => {
    const value = Math.round(Math.min(SIDEBAR_MAX, Math.max(SIDEBAR_MIN, width)));
    root.style.setProperty('--sidebar-w', `${value}px`);
    resizer?.setAttribute('aria-valuenow', String(value));
    return value;
  };
  const currentWidth = () => parseInt(getComputedStyle(root).getPropertyValue('--sidebar-w'), 10) || SIDEBAR_DEFAULT;
  if (sidebar && collapseButton) {
    setCollapsed(isCollapsed(), false);
    resizer?.setAttribute('aria-valuenow', String(currentWidth()));
    collapseButton.addEventListener('click', () => setCollapsed(!isCollapsed()));
    // In the rail, "Catálogos" has no room for its list: expand the sidebar and open it instead.
    sidebar.querySelector('.nav-group > summary')?.addEventListener('click', event => {
      if (!isCollapsed() || mobile.matches) return;
      event.preventDefault();
      setCollapsed(false);
      event.currentTarget.parentElement.open = true;
    });
    document.addEventListener('keydown', event => {
      if (event.key.toLowerCase() !== 'b' || !(event.metaKey || event.ctrlKey) || event.altKey || event.shiftKey || mobile.matches) return;
      if (event.target.closest?.('input, textarea, select, [contenteditable]')) return;
      event.preventDefault();
      setCollapsed(!isCollapsed());
    });
  }
  resizer?.addEventListener('pointerdown', event => {
    if (event.button !== 0 || !sidebar) return;
    event.preventDefault();
    resizer.setPointerCapture(event.pointerId);
    resizer.classList.add('is-dragging');
    root.classList.add('sidebar-resizing');
    const left = sidebar.getBoundingClientRect().left;
    const startWidth = currentWidth();
    let width = startWidth;
    const move = moveEvent => {
      const next = moveEvent.clientX - left;
      // Dragging past the snap point folds it into the rail; dragging back out unfolds it at the pointer.
      if (next < SIDEBAR_SNAP) {
        if (!isCollapsed()) setCollapsed(true);
        width = setWidth(startWidth);
        return;
      }
      if (isCollapsed()) setCollapsed(false, false);
      width = setWidth(next);
    };
    const end = () => {
      resizer.removeEventListener('pointermove', move);
      resizer.classList.remove('is-dragging');
      root.classList.remove('sidebar-resizing');
      remember('sidebar-width', String(width));
    };
    resizer.addEventListener('pointermove', move);
    resizer.addEventListener('pointerup', end, { once: true });
    resizer.addEventListener('pointercancel', end, { once: true });
  });
  resizer?.addEventListener('dblclick', () => {
    remember('sidebar-width', String(setWidth(SIDEBAR_DEFAULT)));
    if (isCollapsed()) setCollapsed(false);
  });
  resizer?.addEventListener('keydown', event => {
    const steps = { ArrowLeft: -16, ArrowRight: 16, Home: -SIDEBAR_MAX, End: SIDEBAR_MAX };
    if (event.key === 'Enter') {
      event.preventDefault();
      setCollapsed(!isCollapsed());
      return;
    }
    if (!(event.key in steps)) return;
    event.preventDefault();
    if (isCollapsed()) setCollapsed(false);
    remember('sidebar-width', String(setWidth(currentWidth() + steps[event.key])));
  });

  const filter = document.querySelector('#contracts-filters');
  if (filter) {
    filter.querySelectorAll('select').forEach(field => field.addEventListener('change', () => filter.requestSubmit()));
    let debounce;
    filter.querySelectorAll('input[name]').forEach(field => field.addEventListener('input', () => {
      clearTimeout(debounce);
      debounce = setTimeout(() => filter.requestSubmit(), 650);
    }));
  }

  // Errors are announced on the visible control; enhanced natives forward to their custom counterpart.
  const showFieldError = (control, text) => {
    const target = control.visualControl || control;
    const id = `${target.id || control.name}-error`;
    document.getElementById(id)?.clearError?.();
    const message = document.createElement('span');
    message.className = 'field-error';
    message.id = id;
    message.textContent = text;
    target.setAttribute('aria-invalid', 'true');
    target.setAttribute('aria-describedby', [target.getAttribute('aria-describedby'), message.id].filter(Boolean).join(' '));
    const field = control.closest('.field');
    if (field) field.appendChild(message);
    else (control.closest('.custom-select, .date-input') || control).after(message);

    // A message describes the submitted value; discard it on edit.
    const watched = [...new Set([control, target])];
    const clearError = () => {
      message.remove();
      target.removeAttribute('aria-invalid');
      const descriptions = (target.getAttribute('aria-describedby') || '').split(/\s+/).filter(item => item && item !== message.id);
      if (descriptions.length) target.setAttribute('aria-describedby', descriptions.join(' '));
      else target.removeAttribute('aria-describedby');
      watched.forEach(item => {
        item.removeEventListener('input', clearError);
        item.removeEventListener('change', clearError);
      });
      control.form?.removeEventListener('reset', clearError);
    };
    message.clearError = clearError;
    watched.forEach(item => {
      item.addEventListener('input', clearError);
      item.addEventListener('change', clearError);
    });
    control.form?.addEventListener('reset', clearError);
  };

  const errorData = document.querySelector('#validation-errors');
  if (errorData) {
    const errors = JSON.parse(errorData.textContent);
    document.querySelectorAll('.field').forEach(field => {
      const control = field.querySelector('input[name]:not([type="hidden"]), select[name], textarea[name]');
      const messages = control && errors[control.name];
      if (messages?.length) showFieldError(control, messages[0]);
    });
  }

  // A rejected submit nudges each invalid field; the shake replays on every attempt.
  const shake = control => {
    const box = control.closest('.custom-select, .date-input, .input-prefix, .password-field') || control;
    box.classList.add('t-input');
    box.classList.remove('is-shaking');
    void box.offsetWidth;
    box.classList.add('is-shaking');
    clearTimeout(box.shakeTimer);
    box.shakeTimer = setTimeout(() => box.classList.remove('is-shaking'), motionMs('--shake-dur-a') * 2 + motionMs('--shake-dur-b') * 2 + 20);
  };
  // validation.js (zod) renders its messages through the same inline treatment as server errors.
  window.formErrors = {
    show: showFieldError,
    clear: control => document.getElementById(`${(control.visualControl || control).id || control.name}-error`)?.clearError?.(),
    shake,
  };

  // Hidden natives cannot show the browser's validation bubble, so their errors render inline.
  let invalidFocused = false;
  document.addEventListener('invalid', event => {
    const control = event.target;
    shake(control);
    const target = control.visualControl || (control.classList.contains('date-text') ? control : null);
    if (!target) return;
    event.preventDefault();
    const source = target.validity.customError ? target : control;
    const message = source.validity.customError
      ? source.validationMessage
      : control.tagName === 'SELECT' ? 'Selecciona una opción.' : 'Ingresa una fecha.';
    showFieldError(control, message);
    if (!invalidFocused) {
      invalidFocused = true;
      target.focus({ preventScroll: true });
      target.scrollIntoView({ block: 'center', behavior: 'smooth' });
      setTimeout(() => { invalidFocused = false; });
    }
  }, true);

  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type="submit"]:is(.primary, .solid), button:not([type]):is(.primary, .solid)');
      if (button) {
        button.dataset.original = button.innerHTML;
        button.disabled = true;
        const label = document.createElement('span');
        label.className = 't-shimmer';
        label.textContent = label.dataset.text = form.dataset.loadingLabel || 'Guardando…';
        button.replaceChildren(label);
      }
    });
  });
  // Restore action buttons when returning through the browser's page cache.
  window.addEventListener('pageshow', () => {
    document.querySelectorAll('button[data-original]').forEach(button => {
      button.innerHTML = button.dataset.original;
      button.disabled = false;
      delete button.dataset.original;
    });
    pageBody?.classList.remove('is-leaving');
    closePopups();
    setMenu(false);
  });
});
