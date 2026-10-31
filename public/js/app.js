document.addEventListener('DOMContentLoaded', () => {
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

  const filter = document.querySelector('#contracts-filters');
  if (filter) {
    filter.querySelectorAll('select').forEach(field => field.addEventListener('change', () => filter.requestSubmit()));
    let debounce;
    filter.querySelectorAll('input').forEach(field => field.addEventListener('input', () => {
      clearTimeout(debounce);
      debounce = setTimeout(() => filter.requestSubmit(), 650);
    }));
  }

  const errorData = document.querySelector('#validation-errors');
  if (errorData) {
    const errors = JSON.parse(errorData.textContent);
    document.querySelectorAll('.field').forEach((field, index) => {
      const control = field.querySelector('input[name]:not([type="hidden"]), select[name], textarea[name]');
      const messages = control && errors[control.name];
      if (!messages?.length) return;
      const message = document.createElement('span');
      message.className = 'field-error';
      message.id = `${control.id || `field-${index}`}-error`;
      message.textContent = messages[0];
      control.setAttribute('aria-invalid', 'true');
      control.setAttribute('aria-describedby', [control.getAttribute('aria-describedby'), message.id].filter(Boolean).join(' '));
      field.appendChild(message);

      // A server response describes the submitted value; discard that message on edit.
      const clearError = () => {
        message.remove();
        control.removeAttribute('aria-invalid');
        const descriptions = (control.getAttribute('aria-describedby') || '').split(/\s+/).filter(id => id && id !== message.id);
        if (descriptions.length) control.setAttribute('aria-describedby', descriptions.join(' '));
        else control.removeAttribute('aria-describedby');
        control.removeEventListener('input', clearError);
        control.removeEventListener('change', clearError);
        control.form?.removeEventListener('reset', clearError);
      };
      control.addEventListener('input', clearError);
      control.addEventListener('change', clearError);
      control.form?.addEventListener('reset', clearError);
    });
  }

  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type="submit"].primary, button:not([type]).primary');
      if (button) {
        button.dataset.original = button.innerHTML;
        button.disabled = true;
        button.textContent = form.dataset.loadingLabel || 'Guardando…';
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
    setMenu(false);
  });
});
