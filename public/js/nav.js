// Navigation trail (dynamic back links), the account menu and the notification panel. All progressive: without JS the
// back links go to the parent page, the account toggles stay in the sidebar markup and the bell opens nothing.
(() => {
  const sprite = () => document.querySelector('use[href*="hugeicons.svg"]')?.getAttribute('href').split('#')[0] || '/icons/hugeicons.svg';
  const icon = name => `<svg class="icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24"><use href="${sprite()}#${name}"/></svg>`;
  const ms = name => parseFloat(getComputedStyle(document.documentElement).getPropertyValue(name)) || 0;
  const store = {
    get: (key, fallback) => { try { return JSON.parse(sessionStorage.getItem(key)) ?? fallback; } catch (error) { return fallback; } },
    set: (key, value) => { try { sessionStorage.setItem(key, JSON.stringify(value)); } catch (error) {} },
  };

  // Trail: the pages visited in this tab, one entry per path (filters only refresh the entry, so going back to a list
  // keeps them). Revisiting a page cuts the trail there, so it never loops; a page left by submitting a form (create,
  // edit, delete) is dropped, so "back" after saving skips the form.
  const TRAIL = 'nav-trail';
  const SUBMITTED = 'nav-submitted';
  const referrer = (() => {
    try {
      const url = new URL(document.referrer);
      return url.origin === location.origin ? url.pathname + url.search : null;
    } catch (error) { return null; }
  })();
  let previous;
  // Runs on load and again when the page comes back from the bfcache, where scripts do not run a second time.
  const syncTrail = () => {
    const here = { path: location.pathname, url: location.pathname + location.search, label: document.title.split(' · ')[0] };
    let trail = store.get(TRAIL, []);
    if (store.get(SUBMITTED, false) && trail.at(-1)?.path !== here.path) trail.pop();
    try { sessionStorage.removeItem(SUBMITTED); } catch (error) {}
    const at = trail.findIndex(entry => entry.path === here.path);
    trail = at >= 0 ? [...trail.slice(0, at), here] : [...trail, here].slice(-30);
    store.set(TRAIL, trail);
    previous = trail.at(-2);
    if (!previous) return;
    document.querySelectorAll('[data-back]').forEach(link => {
      link.href = previous.url;
      const name = link.querySelector('.back-text b');
      if (name) name.textContent = previous.label;
      link.dataset.backDynamic = '';
    });
  };
  syncTrail();
  addEventListener('pageshow', event => { if (event.persisted) syncTrail(); });

  document.addEventListener('submit', event => {
    const form = event.target;
    if (!event.defaultPrevented && form.method === 'post' && !form.closest('[data-ai], .agent')) store.set(SUBMITTED, true);
  });

  // The browser's own back keeps scroll position and the page as it was (bfcache), so use it whenever the previous
  // trail entry is literally the last document — not after a form redirect, where history holds the form.
  document.querySelectorAll('[data-back]').forEach(link => link.addEventListener('click', event => {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (!previous || referrer !== previous.url || history.length < 2) return;
    event.preventDefault();
    document.documentElement.dataset.navDirection = 'back';
    history.back();
  }));
  // Direction for the page transition: going back slides the other way (css: :active-view-transition-type(back)).
  addEventListener('pageswap', event => {
    const back = event.activation?.navigationType === 'traverse' || document.documentElement.dataset.navDirection === 'back';
    if (back) event.viewTransition?.types.add('back');
  });
  addEventListener('pagereveal', event => {
    const entry = performance.getEntriesByType('navigation')[0];
    if (window.navigation?.activation?.navigationType === 'traverse' || entry?.type === 'back_forward') event.viewTransition?.types.add('back');
  });
  addEventListener('pageshow', () => delete document.documentElement.dataset.navDirection);

  // Connection: a lasting toast while offline (saving would fail), a short one when the network comes back.
  let offlineToast;
  addEventListener('offline', () => {
    offlineToast = window.toast?.({ type: 'warning', title: 'Sin conexión', message: 'Los cambios no se guardarán hasta que vuelva la red.', duration: 864e5 });
  });
  addEventListener('online', () => {
    offlineToast?.();
    offlineToast = null;
    window.toast?.({ type: 'success', title: 'Conexión restablecida' });
  });

  // Popovers anchored to a trigger: the account menu and the notification panel share open/close and dismissal.
  const open = new Set();
  const popover = (trigger, panel, { onOpen, origin } = {}) => {
    const item = {
      trigger, panel,
      show(instant) {
        if (!panel.hidden && !panel.classList.contains('is-closing')) return;
        [...open].forEach(other => other.hide());
        clearTimeout(panel.hideTimer);
        panel.classList.remove('is-closing', 'is-open');
        panel.classList.toggle('is-instant', instant);
        panel.dataset.origin = origin;
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        open.add(item);
        onOpen?.();
        void panel.offsetWidth;
        panel.classList.add('is-open');
      },
      hide(restoreFocus) {
        if (!open.delete(item)) return;
        trigger.setAttribute('aria-expanded', 'false');
        panel.classList.remove('is-open', 'is-instant');
        panel.classList.add('is-closing');
        panel.hideTimer = setTimeout(() => {
          panel.hidden = true;
          panel.classList.remove('is-closing');
        }, ms('--dropdown-close-dur'));
        if (restoreFocus) trigger.focus();
      },
    };
    trigger.addEventListener('click', event => (open.has(item) ? item.hide() : item.show(event.detail === 0)));
    return item;
  };
  document.addEventListener('pointerdown', event => {
    open.forEach(item => { if (!item.trigger.contains(event.target) && !item.panel.contains(event.target)) item.hide(); });
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape' || !open.size) return;
    event.stopPropagation();
    open.forEach(item => item.hide(true));
  }, true);

  const accountTrigger = document.querySelector('#account-trigger');
  const accountPanel = document.querySelector('#account-panel');
  if (accountPanel) accountPanel.hidden = true;
  if (accountTrigger && accountPanel) popover(accountTrigger, accountPanel, { origin: 'bottom-left', onOpen: () => accountPanel.querySelector('button')?.focus({ preventScroll: true }) });

  // Notifications: the bell shows the unread count (server-rendered, refreshed every minute while the tab is visible),
  // the panel lists the latest 30 and anything new that arrives while the page is open also shows as a toast.
  const bell = document.querySelector('#notif-trigger');
  const panel = document.querySelector('#notif-panel');
  if (!bell || !panel) return;
  const list = panel.querySelector('.notif-list');
  const badge = bell.querySelector('.notif-badge');
  const readAll = panel.querySelector('[data-notif-read-all]');
  const tabs = panel.querySelectorAll('[data-notif-filter]');
  const csrf = document.querySelector('meta[name=csrf-token]')?.content;
  const SEEN = 'notif-seen';
  let items = null;
  let filter = 'all';

  const relative = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });
  const ago = iso => {
    const seconds = (new Date(iso) - Date.now()) / 1000;
    for (const [unit, size] of [['day', 86400], ['hour', 3600], ['minute', 60]]) {
      if (Math.abs(seconds) >= size) return relative.format(Math.round(seconds / size), unit);
    }
    return 'ahora';
  };
  const setCount = count => {
    const previousCount = Number(bell.dataset.count || 0);
    bell.dataset.count = count;
    badge.textContent = count > 9 ? '9+' : count;
    badge.hidden = !count;
    bell.setAttribute('aria-label', count ? `Notificaciones, ${count} sin leer` : 'Notificaciones');
    readAll.disabled = !count;
    if (count > previousCount) {
      badge.classList.remove('is-bumping');
      void badge.offsetWidth;
      badge.classList.add('is-bumping');
    }
  };
  const render = () => {
    const shown = (items || []).filter(item => filter === 'all' || (filter === 'unread' ? item.unread : item.kind === 'alert'));
    tabs.forEach(tab => tab.setAttribute('aria-selected', String(tab.dataset.notifFilter === filter)));
    if (!items) {
      list.innerHTML = Array.from({ length: 3 }, () => '<li class="notif-item is-skeleton" aria-hidden="true"><i class="sk sk-avatar"></i><div><i class="sk sk-line w-40"></i><i class="sk sk-line sm w-30"></i></div></li>').join('');
      return;
    }
    if (!shown.length) {
      list.innerHTML = `<li class="notif-empty"><span class="notif-empty-icon">${icon(filter === 'unread' ? 'check-check' : 'bell')}</span><b>${filter === 'unread' ? 'Estás al día' : filter === 'alert' ? 'Sin pendientes' : 'Sin notificaciones'}</b><small>${filter === 'all' ? 'Aquí verás la actividad del equipo y lo que requiere acción.' : 'No hay nada que revisar por ahora.'}</small></li>`;
      return;
    }
    list.replaceChildren(...shown.map(item => {
      const row = document.createElement('li');
      row.innerHTML = `<a class="notif-item ${item.unread ? 'is-unread' : ''}" href=""><span class="notif-icon ${item.tone}">${icon(item.icon)}</span><span class="notif-text"><b></b><small></small><time></time></span>${item.unread ? '<i class="notif-dot" aria-label="Sin leer"></i>' : ''}</a>`;
      const link = row.firstElementChild;
      link.href = item.url;
      link.querySelector('b').textContent = item.title;
      link.querySelector('small').textContent = item.body;
      const time = link.querySelector('time');
      time.dateTime = item.time;
      time.textContent = `${item.kind === 'alert' ? 'Pendiente · ' : ''}${ago(item.time)}`;
      return row;
    }));
  };

  let loading;
  const load = async ({ announce = false } = {}) => {
    if (loading) return loading;
    loading = fetch(panel.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(response => (response.ok ? response.json() : Promise.reject(response)))
      .then(data => {
        // New since this tab last looked: toast the newest few (only on background refreshes, not the first load).
        const seen = new Set(store.get(SEEN, []));
        const fresh = data.items.filter(item => item.unread && !seen.has(item.id));
        if (announce && items) {
          fresh.slice(0, 2).reverse().forEach(item => window.toast?.({ type: item.tone === 'danger' ? 'error' : item.tone === 'success' ? 'success' : 'info', title: item.title, message: item.body }));
        }
        store.set(SEEN, data.items.map(item => item.id));
        items = data.items;
        setCount(data.unread);
        if (!panel.hidden) render();
      })
      .catch(() => {})
      .finally(() => { loading = null; });
    return loading;
  };

  popover(bell, panel, { origin: 'top-right', onOpen: () => { render(); load(); } });
  tabs.forEach(tab => tab.addEventListener('click', () => { filter = tab.dataset.notifFilter; render(); }));
  readAll.addEventListener('click', async () => {
    readAll.disabled = true;
    items?.forEach(item => { item.unread = false; });
    setCount(0);
    render();
    await fetch(readAll.dataset.url, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, credentials: 'same-origin' }).catch(() => {});
  });
  setCount(Number(bell.dataset.count || 0));
  load();
  setInterval(() => { if (!document.hidden) load({ announce: true }); }, 60000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) load({ announce: true }); });
})();
