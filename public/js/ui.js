// Toasts and navigation skeletons. Both are progressive: without JS, flash messages render as <noscript> notices and
// pages simply load.
(() => {
  const sprite = () => document.querySelector('use[href*="hugeicons.svg"]')?.getAttribute('href').split('#')[0] || '/icons/hugeicons.svg';
  const icon = name => `<svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="${sprite()}#${name}"/></svg>`;
  const ms = name => parseFloat(getComputedStyle(document.documentElement).getPropertyValue(name)) || 0;

  // Toasts: newest at the bottom, at most three; timers pause while hovered, focused or while the tab is hidden.
  let toaster;
  const TOAST_ICONS = { success: 'check', error: 'x', warning: 'info', info: 'info' };
  const toast = ({ type = 'success', title, message = '', duration = type === 'error' ? 7000 : 4500 }) => {
    // The cue for what the toast reports, kept subtle (sounds.js; silent until the first interaction).
    window.sound?.play({ error: 'error', warning: 'warning', info: 'ready' }[type] || 'success', { emphasis: 'subtle' });
    if (!toaster) {
      toaster = document.createElement('section');
      toaster.className = 'toaster';
      toaster.setAttribute('aria-label', 'Notificaciones');
      document.body.append(toaster);
    }
    const slot = document.createElement('div');
    slot.className = 'toast-slot';
    const item = document.createElement('div');
    item.className = `toast t-toast ${type}`;
    item.setAttribute('role', type === 'error' ? 'alert' : 'status');
    item.innerHTML = `<span class="toast-icon">${icon(TOAST_ICONS[type] || 'info')}</span><div class="toast-text"><b></b><p></p></div><button type="button" class="toast-close" aria-label="Cerrar notificación">${icon('x')}</button>`;
    item.querySelector('b').textContent = title;
    const body = item.querySelector('p');
    if (message) body.textContent = message;
    else body.remove();
    slot.append(item);
    toaster.append(slot);
    [...toaster.children].slice(0, -3).forEach(old => old.dismiss?.());

    let remaining = duration;
    let started = Date.now();
    let timer;
    const pause = () => {
      clearTimeout(timer);
      remaining -= Date.now() - started;
    };
    const resume = () => {
      if (document.hidden || item.matches(':hover, :focus-within')) return;
      started = Date.now();
      clearTimeout(timer);
      timer = setTimeout(dismiss, Math.max(remaining, 1200));
    };
    const dismiss = slot.dismiss = () => {
      if (slot.classList.contains('is-leaving')) return;
      clearTimeout(timer);
      item.classList.remove('is-open');
      slot.classList.add('is-leaving');
      document.removeEventListener('visibilitychange', onVisibility);
      setTimeout(() => slot.remove(), ms('--toast-close') + 120);
    };
    const onVisibility = () => (document.hidden ? pause() : resume());
    item.addEventListener('pointerenter', pause);
    item.addEventListener('pointerleave', resume);
    item.addEventListener('focusin', pause);
    item.addEventListener('focusout', resume);
    item.querySelector('.toast-close').addEventListener('click', dismiss);
    document.addEventListener('visibilitychange', onVisibility);
    void item.offsetWidth;
    item.classList.add('is-open');
    resume();
    return dismiss;
  };
  window.toast = toast;

  // Navigation skeletons: shown only when the next page takes longer than a blink, shaped like the destination.
  const bars = (count, className = '') => Array.from({ length: count }, () => `<i class="sk ${className}"></i>`).join('');
  const head = '<div class="sk-head"><div>' + bars(1, 'sk-title') + bars(1, 'sk-line w-40') + '</div>' + bars(1, 'sk-button') + '</div>';
  const rows = count => Array.from({ length: count }, () => `<div class="sk-row">${bars(1, 'sk-avatar')}<div>${bars(1, 'sk-line w-30')}${bars(1, 'sk-line sm w-20')}</div>${bars(1, 'sk-line w-20')}${bars(1, 'sk-line w-10')}</div>`).join('');
  const table = count => `<div class="sk-card"><div class="sk-card-head">${bars(1, 'sk-line w-30')}${bars(1, 'sk-field')}</div>${rows(count)}</div>`;
  const fields = count => `<div class="sk-card sk-pad"><div class="sk-fields">${Array.from({ length: count }, () => `<div>${bars(1, 'sk-line sm w-30')}${bars(1, 'sk-field')}</div>`).join('')}</div></div>`;
  const list = count => `<div class="sk-card sk-pad">${bars(1, 'sk-line w-40')}${Array.from({ length: count }, () => `<div class="sk-pair">${bars(1, 'sk-line w-30')}${bars(1, 'sk-line w-20')}</div>`).join('')}</div>`;
  const SKELETONS = {
    dashboard: `${head}<div class="sk-split"><div class="sk-card sk-hero">${bars(1, 'sk-line w-30')}${bars(1, 'sk-figure')}${bars(1, 'sk-line w-40')}</div><div class="sk-card sk-pad">${bars(1, 'sk-line w-40')}${rows(3)}</div></div>${table(4)}`,
    index: `${head}${table(6)}`,
    form: `${head}${fields(8)}`,
    detail: `${head}<div class="sk-split even">${list(5)}${list(5)}</div>`,
  };
  const kindOf = url => {
    const path = url.pathname.replace(/\/+$/, '');
    if (!path) return 'dashboard';
    if (/\/(nuevo|editar|crear)(\/|$)/.test(path)) return 'form';
    if (/^\/(contratos|trabajadores)\/[^/]+/.test(path)) return 'detail';
    return 'index';
  };

  let pending;
  const showSkeleton = url => {
    const main = document.querySelector('#main-content');
    if (!main) return;
    clearTimeout(pending);
    pending = setTimeout(() => {
      main.querySelector(':scope > .page-skeleton')?.remove();
      const skeleton = document.createElement('div');
      skeleton.className = 'page-skeleton';
      skeleton.setAttribute('aria-hidden', 'true');
      skeleton.innerHTML = SKELETONS[kindOf(url)];
      main.prepend(skeleton);
      main.classList.add('is-loading');
      main.setAttribute('aria-busy', 'true');
      (document.querySelector('#main-scroll') || window).scrollTo({ top: 0 });
    }, 140);
  };
  const resetSkeleton = () => {
    clearTimeout(pending);
    const main = document.querySelector('#main-content');
    main?.classList.remove('is-loading');
    main?.removeAttribute('aria-busy');
    main?.querySelector(':scope > .page-skeleton')?.remove();
  };

  // In-place updates (filters, search, sorting, pagination, tabs, actions that return to the same list) reload this same
  // page: instead of a skeleton and a jump to the top, only the cursor turns busy, and the new page opens where the old
  // one was — same scroll position, same field focused — so it reads as the content updating, not the page reloading.
  // Pagination is the exception for scroll: the next page starts at the top.
  const PLACE = 'in-place';
  const markBusy = () => {
    clearTimeout(pending);
    pending = setTimeout(() => {
      document.documentElement.classList.add('is-refreshing');
      document.querySelector('#main-content')?.setAttribute('aria-busy', 'true');
    }, 140);
  };
  const keepPlace = ({ scroll = true, field: keepField = true } = {}) => {
    const active = document.activeElement;
    const field = keepField && active?.id && active.matches('input[name]:not([type=hidden]), textarea[name]') ? active : null;
    try {
      sessionStorage.setItem(PLACE, JSON.stringify({ path: location.pathname, top: scroll ? document.querySelector('#main-scroll')?.scrollTop || 0 : 0, field: field?.id, at: Date.now() }));
    } catch (error) {}
    markBusy();
  };
  const restorePlace = () => {
    let place = null;
    try {
      place = JSON.parse(sessionStorage.getItem(PLACE));
      sessionStorage.removeItem(PLACE);
    } catch (error) {}
    if (!place || place.path !== location.pathname || Date.now() - place.at > 30000) return;
    // A save that came back with errors scrolls to the first invalid field itself (validation.js).
    if (document.querySelector('.field-error, [aria-invalid=true]')) return;
    const scroller = document.querySelector('#main-scroll');
    if (scroller && place.top) scroller.scrollTo({ top: place.top, behavior: 'instant' });
    const field = place.field && document.getElementById(place.field);
    if (field) {
      field.focus({ preventScroll: true });
      try { field.setSelectionRange(field.value.length, field.value.length); } catch (error) {}
    }
  };
  const resetBusy = () => {
    document.documentElement.classList.remove('is-refreshing');
    resetSkeleton();
  };

  document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if ((link.target && link.target !== '_self') || link.hasAttribute('download')) return;
    const url = new URL(link.href, location.href);
    // Other origins, in-page anchors and spreadsheet exports never navigate away.
    if (url.origin !== location.origin || /\/excel$/.test(url.pathname)) return;
    if (url.pathname === location.pathname) {
      if (url.search === location.search && (url.hash || link.getAttribute('href') === '#')) return;
      keepPlace({ scroll: !link.closest('.pager') });
      return;
    }
    if (link.classList.contains('nav-link')) {
      document.querySelectorAll('.nav-link.active').forEach(item => item.classList.remove('active'));
      link.classList.add('active');
    }
    showSkeleton(url);
  });
  // Filter forms (GET) reload the listing in place; saving forms keep their own loading button and, when they come back
  // to this page, their place on it.
  document.addEventListener('submit', event => {
    const form = event.target;
    if (event.defaultPrevented || form.method === 'dialog' || (form.target && form.target !== '_self')) return;
    const url = new URL(form.action, location.href);
    if (/\/excel$/.test(url.pathname)) return;
    if (form.method === 'post') keepPlace({ field: false });
    else if (url.pathname === location.pathname) keepPlace();
    else showSkeleton(url);
  });
  window.addEventListener('pageshow', resetBusy);
  document.addEventListener('DOMContentLoaded', restorePlace);

  document.addEventListener('DOMContentLoaded', () => {
    const flash = document.querySelector('#flash-toasts');
    if (flash) JSON.parse(flash.textContent).forEach(item => toast(item));
    // Server validation: fields already carry their message (app.js); the toast summarises and keeps any error that
    // has no field on the page.
    const errorData = document.querySelector('#validation-errors');
    const errors = errorData ? Object.entries(JSON.parse(errorData.textContent)) : [];
    if (errors.length) {
      const orphan = errors.filter(([name]) => !document.querySelector(`.field [name="${CSS.escape(name)}"]`)).map(([, messages]) => messages[0]);
      const marked = errors.length - orphan.length;
      toast({
        type: 'error',
        title: marked ? (marked === 1 ? 'Revisa el campo marcado' : `Revisa ${marked} campos marcados`) : 'No se pudo completar la acción',
        message: orphan.join(' ') || (errors.length === 1 ? errors[0][1][0] : 'Corrige los datos resaltados e inténtalo de nuevo.'),
      });
    }
  });
})();
