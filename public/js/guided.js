// Guided forms: the assistant asks for one field at a time and fills the real form as you answer. The questions live
// on the form's own controls (data-ask, data-ask-hint, data-ask-when, data-ask-order, data-ask-group), so the Blade
// markup stays the single description of the form and the classic layout is one click away. Answers go through the
// same zod rules as the classic form (validation.js exposes them as window.formValidation) and the server keeps the
// final word. Without JS only the classic form exists.
// Uses icon(), normalize(), notify() and MONTHS from app.js; escapeHtml(), stream(), thinking(), thinkLine(),
// matrixLoader(), aiRequest(), wait() and reduceMotion from ai.js.
(() => {
  const ACKS = ['Perfecto.', 'Anotado.', 'Listo.', 'Bien.'];
  const ASK_DELAY_MS = 420;

  // Answers in people's words: "2,4 millones", "15 de enero de 2024", "hoy".
  const parseMoney = raw => {
    let text = normalize(raw).replace(/\$|cop|pesos|\s/g, '');
    if (/^(no|ninguno|ninguna|nada|cero)$/.test(text)) return 0;
    let factor = 1;
    if (/(millones|millon|mm|m)$/.test(text)) { factor = 1e6; text = text.replace(/(millones|millon|mm|m)$/, ''); }
    else if (/(mil|k)$/.test(text)) { factor = 1e3; text = text.replace(/(mil|k)$/, ''); }
    if (!/^[\d.,]+$/.test(text)) return null;
    const lastDot = text.lastIndexOf('.');
    const lastComma = text.lastIndexOf(',');
    // Colombian style: dots group thousands, the comma is decimal; a lone separator with three digits after groups.
    const decimalAt = lastDot >= 0 && lastComma >= 0 ? Math.max(lastDot, lastComma)
      : [lastDot, lastComma].map(at => (at >= 0 && text.length - at - 1 !== 3 && text.split(text[at]).length === 2 ? at : -1)).find(at => at >= 0) ?? -1;
    const whole = (decimalAt >= 0 ? text.slice(0, decimalAt) : text).replace(/[.,]/g, '');
    const fraction = decimalAt >= 0 ? text.slice(decimalAt + 1).replace(/[.,]/g, '') : '';
    const value = Number(`${whole || 0}.${fraction || 0}`) * factor;
    return Number.isFinite(value) ? Math.round(value * 100) / 100 : null;
  };
  const pad = number => String(number).padStart(2, '0');
  const isoDate = date => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
  const realDate = (year, month, day) => {
    const date = new Date(year, month - 1, day);
    return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day ? date : null;
  };
  const parseDate = raw => {
    const text = normalize(raw).replace(/,/g, ' ').replace(/\s+/g, ' ');
    const today = new Date();
    const relative = { hoy: 0, ayer: -1, manana: 1 }[text];
    if (relative !== undefined) return isoDate(new Date(today.getFullYear(), today.getMonth(), today.getDate() + relative));
    let match = text.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
    if (match) return realDate(+match[1], +match[2], +match[3]) && isoDate(realDate(+match[1], +match[2], +match[3]));
    match = text.match(/^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2}|\d{4})$/);
    if (match) {
      const date = realDate(match[3].length === 2 ? 2000 + +match[3] : +match[3], +match[2], +match[1]);
      return date && isoDate(date);
    }
    const months = MONTHS.map(normalize);
    const monthOf = word => (word === 'setiembre' ? 8 : months.findIndex(month => month === word || (word.length >= 3 && month.startsWith(word))));
    match = text.match(/^(\d{1,2}) (?:de )?([a-z]+)(?: (?:de |del )?(\d{4}))?$/) || text.match(/^([a-z]+) (\d{1,2})(?: (?:de |del )?(\d{4}))?$/);
    if (match) {
      const [day, word] = /^\d/.test(match[1]) ? [+match[1], match[2]] : [+match[2], match[1]];
      const month = monthOf(word);
      const date = month >= 0 && realDate(match[3] ? +match[3] : today.getFullYear(), month + 1, day);
      return date ? isoDate(date) : null;
    }
    return null;
  };
  const showDate = iso => {
    const [year, month, day] = iso.split('-').map(Number);
    return `${day} de ${MONTHS[month - 1]} de ${year}`;
  };
  const showMoney = value => `$${Number(value).toLocaleString('es-CO')}`;

  const initGuided = form => {
    const key = `guided:${location.pathname}`;
    const title = form.dataset.guided;
    const isMoney = control => control.closest('.input-prefix') !== null;
    const kindOf = control => {
      if (control.tagName === 'SELECT') return 'select';
      if (control.tagName === 'TEXTAREA') return 'textarea';
      if (control.type === 'checkbox') return 'checkbox';
      if (control.type === 'date') return 'date';
      if (control.type === 'number') return isMoney(control) ? 'money' : 'number';
      return control.type;
    };
    // app.js renames enhanced natives (`…-native`), so the field's own label is the reliable source.
    const labelOf = control => (control.dataset.askLabel || (control.closest('.field')?.querySelector('.field-label') || form.querySelector(`label[for="${CSS.escape(control.id)}"]`))?.firstChild?.textContent || control.name).replace(/\*/g, '').trim();

    const steps = [...form.querySelectorAll('[data-ask]')].map((control, index) => ({
      control,
      kind: kindOf(control),
      order: Number(control.dataset.askOrder || (index + 1) * 10),
      group: control.dataset.askGroup || null,
      label: labelOf(control),
    })).sort((a, b) => a.order - b.order);
    if (!steps.length) return;
    const groups = {};
    steps.forEach(step => {
      if (step.group && !groups[step.group]) {
        const { askGroupQuestion: question, askGroupEnter: enter, askGroupSkip: skip } = step.control.dataset;
        groups[step.group] = { question, enter, skip, decided: null };
      }
    });

    // Values as people read them: the option's text, a formatted date or amount.
    const display = step => {
      const { control, kind } = step;
      if (kind === 'select') return control.value ? control.selectedOptions[0].text : '';
      if (kind === 'checkbox') return control.checked ? 'Sí' : 'No';
      if (!control.value) return '';
      if (kind === 'date') return showDate(control.value);
      if (kind === 'money') return showMoney(control.value);
      return control.value;
    };
    const stepFor = name => steps.find(step => step.control.name === name);
    // "{worker_id|first|la persona}": an earlier answer, its first word, or the fallback when it is still empty.
    const fill = template => template.replace(/\{([\w]+)((?:\|[^}|]+)*)\}/g, (_, name, modifiers) => {
      const options = modifiers.split('|').filter(Boolean);
      const step = stepFor(name);
      let value = step ? display(step).split(' · ')[0] : '';
      if (value && options.includes('first')) value = value.split(/\s+/)[0];
      return value || options.find(option => option !== 'first') || '';
    });
    // "status=terminated&contract_type_id.method=manual|obra"; `.method` reads the selected option's data-method.
    const visible = step => (step.control.dataset.askWhen || '').split('&').filter(Boolean).every(rule => {
      const [left, expected] = rule.split('=');
      const [name, data] = left.split('.');
      const control = form.querySelector(`[name="${CSS.escape(name)}"]`);
      const actual = data ? control?.selectedOptions?.[0]?.dataset[data] : control?.value;
      return expected.split('|').includes(actual ?? '');
    });
    const skippedByGroup = step => step.group && groups[step.group].decided === 'skip';
    const relevant = () => steps.filter(step => visible(step) && !skippedByGroup(step));

    // UI: a mode switch above the form and the conversation next to it.
    const extractCard = form.dataset.guidedExtract ? document.querySelector(form.dataset.guidedExtract) : null;
    const switcher = document.createElement('div');
    switcher.className = 'form-mode';
    switcher.innerHTML = `<div class="segmented" role="radiogroup" aria-label="Cómo llenar el formulario"><label><input type="radio" name="guided-mode" value="guided"><span>${icon('sparkles')} Conversación con IA</span></label><label><input type="radio" name="guided-mode" value="classic"><span>Formulario completo</span></label></div>`;
    const panel = document.createElement('section');
    panel.className = 'surface guided';
    panel.setAttribute('aria-labelledby', `${key}-title`);
    panel.innerHTML = `<header class="guided-head"><span class="ai-mark" aria-hidden="true">${icon('sparkles')}</span><div class="guided-title"><h2 id="${key}-title"></h2><p>Te pregunto lo necesario, uno a la vez. Puedes volver o cambiar cualquier respuesta.</p></div><div class="guided-progress"><span class="guided-count" aria-hidden="true"></span><span class="guided-bar" role="progressbar" aria-label="Progreso del formulario" aria-valuemin="0"><i></i></span></div></header><div class="guided-log" aria-live="polite"></div><form class="guided-composer" novalidate></form>`;
    panel.querySelector('h2').textContent = title;
    (extractCard || form).before(switcher, panel);
    const log = panel.querySelector('.guided-log');
    const composer = panel.querySelector('.guided-composer');
    const bar = panel.querySelector('.guided-bar');
    const count = panel.querySelector('.guided-count');

    const handled = new Set();
    const history = [];
    let current = null;
    let asked = 0;
    let messageCount = 0;
    let busy = false;
    let started = false;

    // Each group's yes/no is one question; its fields count only once the group is opened.
    const progress = () => {
      const list = relevant();
      const open = list.filter(step => !step.group || groups[step.group].decided === 'enter');
      const decisions = Object.entries(groups).filter(([name, group]) => group.decided || list.some(step => step.group === name)).map(([, group]) => group);
      const total = open.length + decisions.length;
      const done = open.filter(step => handled.has(step)).length + decisions.filter(group => group.decided).length;
      bar.setAttribute('aria-valuemax', String(total));
      bar.setAttribute('aria-valuenow', String(done));
      bar.setAttribute('aria-valuetext', `${done} de ${total} preguntas`);
      bar.style.setProperty('--progress', `${total ? (done / total) * 100 : 0}%`);
      count.textContent = `${done} de ${total}`;
    };
    const scrollDown = () => log.lastElementChild?.scrollIntoView({ block: 'nearest', behavior: reduceMotion.matches ? 'auto' : 'smooth' });

    // An assistant message: a short thinking beat, then the text streams in.
    const say = async (text, hint = '', { think = true } = {}) => {
      const message = document.createElement('div');
      message.className = 'guided-msg is-ai';
      const id = `${key}-msg-${++messageCount}`;
      message.innerHTML = `<span class="guided-avatar" aria-hidden="true">${icon('sparkles')}</span><div class="guided-bubble"><p id="${id}"></p></div>`;
      log.append(message);
      const paragraph = message.querySelector('p');
      if (think && !reduceMotion.matches) {
        const beat = document.createElement('span');
        beat.className = 'guided-beat';
        const line = thinkLine(['Pensando']);
        beat.append(matrixLoader('pulse', true), line.element);
        paragraph.append(beat);
        scrollDown();
        await wait(ASK_DELAY_MS);
        line.stop();
      }
      await stream(paragraph, text);
      if (hint) paragraph.insertAdjacentHTML('afterend', `<small>${escapeHtml(hint)}</small>`);
      scrollDown();
      return id;
    };
    const reply = text => {
      const message = document.createElement('div');
      message.className = 'guided-msg is-user';
      message.innerHTML = `<p class="ai-question"></p>`;
      message.querySelector('p').textContent = text;
      log.append(message);
      scrollDown();
    };

    const chip = (label, onPick, className = '') => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `ai-chip ${className}`.trim();
      button.innerHTML = label;
      button.addEventListener('click', () => { if (!busy) onPick(); });
      return button;
    };
    const backChip = () => (history.length ? chip(`${icon('arrow-left')} Anterior`, back, 'is-quiet') : null);
    const chipRow = list => {
      const row = document.createElement('div');
      row.className = 'ai-followups guided-chips';
      list.filter(Boolean).forEach(item => row.append(item));
      return row;
    };

    // The composer adapts to the field: chips for choices, a free-text box that understands dates and amounts.
    const PLACEHOLDERS = { date: 'dd/mm/aaaa, «hoy» o «15 de enero de 2024»', money: 'Ej. 2.400.000 o 2,4 millones', number: 'Escribe un número', email: 'nombre@ejemplo.com', tel: '+57 300 000 0000', select: 'Escribe para filtrar…', textarea: 'Escribe aquí…' };
    const composeText = ({ labelledBy, kind, value = '', placeholder, autocomplete, onSend, extras = [] }) => {
      composer.replaceChildren();
      const field = document.createElement(kind === 'textarea' ? 'textarea' : 'input');
      field.className = 'guided-input';
      field.setAttribute('aria-labelledby', labelledBy);
      if (kind === 'textarea') field.rows = 3;
      else field.type = kind === 'email' ? 'email' : kind === 'tel' ? 'tel' : 'text';
      if (kind === 'money') field.inputMode = 'decimal';
      if (kind === 'number') field.inputMode = 'numeric';
      field.autocomplete = autocomplete || 'off';
      field.placeholder = placeholder || PLACEHOLDERS[kind] || 'Escribe tu respuesta…';
      field.value = value;
      const send = document.createElement('button');
      send.type = 'submit';
      send.className = 'ai-send';
      send.setAttribute('aria-label', 'Responder');
      send.innerHTML = icon('send');
      const row = document.createElement('div');
      row.className = 'ai-ask guided-ask';
      row.append(field, send);
      composer.append(row);
      if (extras.length) composer.append(chipRow(extras));
      composer.onsubmit = event => {
        event.preventDefault();
        if (!busy && (field.value.trim() || kind !== 'textarea')) onSend(field.value);
      };
      field.addEventListener('keydown', event => {
        if (kind === 'textarea' && event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
          event.preventDefault();
          composer.requestSubmit();
        }
      });
      field.focus({ preventScroll: true });
      return field;
    };

    const optionsOf = control => [...control.options].filter(option => option.value);
    const matchOptions = (control, text) => {
      const query = normalize(text);
      const options = optionsOf(control);
      if (!query) return options;
      const words = query.split(/\s+/);
      return options
        .map(option => {
          const name = normalize(option.text);
          const rank = name === query ? 4 : name.startsWith(query) ? 3 : name.split(/[\s·]+/).some(word => word.startsWith(query)) ? 2 : words.every(word => name.includes(word)) ? 1 : 0;
          return { option, rank };
        })
        .filter(entry => entry.rank)
        .sort((a, b) => b.rank - a.rank)
        .map(entry => entry.option);
    };

    // Writes the answer into the real control and checks it with the form's own rules; reverts when rejected.
    const commit = (step, value) => {
      const { control } = step;
      const previous = control.type === 'checkbox' ? control.checked : control.value;
      if (control.type === 'checkbox') control.checked = value;
      else control.value = value;
      notify(control);
      const error = window.formValidation?.check(control) || (control.checkValidity() ? null : control.validationMessage);
      if (error) {
        if (control.type === 'checkbox') control.checked = previous;
        else control.value = previous;
        notify(control);
      }
      return error;
    };

    const accept = (step, shown) => {
      reply(shown);
      handled.add(step);
      history.push({ step });
      step.control.closest('.field')?.classList.remove('ai-filled');
      advance();
    };

    const reject = async (step, error, typed) => {
      busy = true;
      composer.replaceChildren();
      const id = await say(error, '', { think: false });
      busy = false;
      renderComposer(step, id, typed);
    };

    const answer = (step, raw, skipLabel = 'Lo dejo en blanco') => {
      const text = String(raw).trim();
      const { control, kind } = step;
      if (!text) {
        if (control.required) return reject(step, 'Necesito este dato para continuar.', raw);
        commit(step, '');
        return accept(step, skipLabel);
      }
      let value = text;
      let shown = text;
      if (kind === 'date') {
        value = parseDate(text);
        if (!value) return reject(step, 'No reconocí esa fecha. Prueba con 15/01/2024, «hoy» o «15 de enero de 2024».', raw);
        shown = showDate(value);
      } else if (kind === 'money' || kind === 'number') {
        value = kind === 'money' ? parseMoney(text) : parseMoney(text.replace(/[^\d.,a-z\s]/gi, ''));
        if (value === null) return reject(step, 'Escríbelo como número, por ejemplo 2.400.000 o 2,4 millones.', raw);
        shown = kind === 'money' ? showMoney(value) : value.toLocaleString('es-CO');
      } else if (kind === 'select') {
        const [best] = matchOptions(control, text);
        if (!best) return reject(step, `No encontré «${text}» entre las opciones. Elige una de la lista.`, raw);
        value = best.value;
        shown = best.text;
      }
      const error = commit(step, value);
      if (error) return reject(step, error, raw);
      accept(step, shown);
    };

    const renderComposer = (step, labelledBy, typed = '') => {
      const { control, kind } = step;
      const optional = !control.required;
      const keep = display(step) && !typed ? chip(`Mantener: ${escapeHtml(display(step))}`, () => accept(step, display(step)), 'is-keep') : null;
      const skip = optional ? chip(escapeHtml(control.dataset.askSkip || 'Omitir'), () => answer(step, '', control.dataset.askSkip || 'Omitir')) : null;
      if (kind === 'checkbox') {
        composer.replaceChildren(chipRow([chip('Sí', () => (commit(step, true) ? null : accept(step, 'Sí'))), chip('No', () => { const error = commit(step, false); if (error) reject(step, error); else accept(step, 'No'); }), backChip()]));
        composer.querySelector('button')?.focus({ preventScroll: true });
        return;
      }
      if (kind === 'select') {
        const options = optionsOf(control);
        const few = options.length <= 6;
        const choices = document.createElement('div');
        const paint = text => {
          const found = matchOptions(control, text).slice(0, 8);
          choices.replaceChildren(...found.map(option => chip(escapeHtml(option.text), () => answer(step, option.text), option.value === control.value ? 'is-current' : '')));
          if (!found.length) choices.innerHTML = '<p class="guided-empty">Sin coincidencias. Prueba con otra palabra.</p>';
        };
        choices.className = 'ai-followups guided-options';
        choices.setAttribute('role', 'group');
        choices.setAttribute('aria-label', 'Opciones');
        if (few) {
          composer.replaceChildren(choices, chipRow([skip, backChip()]));
          paint('');
          composer.querySelector('.guided-options button')?.focus({ preventScroll: true });
          return;
        }
        const field = composeText({ labelledBy, kind, value: typed, onSend: text => answer(step, text), extras: [skip, backChip()] });
        composer.insertBefore(choices, composer.children[1] || null);
        field.addEventListener('input', () => paint(field.value));
        paint(typed);
        return;
      }
      const extras = [keep, kind === 'date' ? chip('Hoy', () => answer(step, 'hoy')) : null, skip, backChip()];
      composeText({ labelledBy, kind, value: typed, autocomplete: control.getAttribute('autocomplete'), placeholder: control.dataset.askPlaceholder, onSend: text => answer(step, text), extras });
    };

    const askGroup = async (name, step) => {
      const group = groups[name];
      busy = true;
      composer.replaceChildren();
      await say(fill(group.question), step.control.dataset.askGroupHint || '');
      busy = false;
      const decide = (decision, shown) => {
        group.decided = decision;
        history.push({ group: name });
        reply(shown);
        advance();
      };
      composer.replaceChildren(chipRow([chip(escapeHtml(group.enter), () => decide('enter', group.enter)), chip(escapeHtml(group.skip), () => decide('skip', group.skip)), backChip()]));
      composer.querySelector('button')?.focus({ preventScroll: true });
    };

    const ask = async step => {
      current = step;
      busy = true;
      composer.replaceChildren();
      const ack = asked++ && !history.at(-1)?.group ? `${ACKS[asked % ACKS.length]} ` : '';
      const id = await say(ack + fill(step.control.dataset.ask), step.control.dataset.askHint ? fill(step.control.dataset.askHint) : '');
      busy = false;
      renderComposer(step, id, step.kind === 'select' ? '' : '');
    };

    // Next question: the first relevant field not answered yet, opening its group first. None left: the summary.
    const advance = () => {
      progress();
      const step = relevant().find(item => !handled.has(item));
      if (!step) return summary();
      if (step.group && groups[step.group].decided === null) return askGroup(step.group, step);
      ask(step);
    };

    const back = () => {
      const last = history.pop();
      if (!last) return;
      if (last.group) groups[last.group].decided = null;
      else handled.delete(last.step);
      reply('Volver a la anterior');
      advance();
    };

    const summary = async () => {
      current = null;
      busy = true;
      composer.replaceChildren();
      const name = fill(form.dataset.guidedName || '');
      await say(name ? `Listo. Esto es lo que tengo de ${name}; revisa antes de guardar.` : 'Listo. Revisa los datos antes de guardar.');
      busy = false;
      const card = document.createElement('div');
      card.className = 'guided-summary';
      const rows = relevant().filter(step => handled.has(step));
      card.innerHTML = `<dl>${rows.map((step, index) => `<div><dt>${escapeHtml(step.label)}</dt><dd>${escapeHtml(display(step) || '—')}</dd><button type="button" class="table-action" data-edit="${index}" aria-label="Cambiar ${escapeHtml(step.label)}">${icon('pencil-line')}</button></div>`).join('')}</dl>`;
      card.querySelectorAll('[data-edit]').forEach(button => button.addEventListener('click', () => {
        if (busy) return;
        const step = rows[Number(button.dataset.edit)];
        handled.delete(step);
        card.remove();
        ask(step);
      }));
      log.append(card);
      const submit = document.createElement('button');
      submit.type = 'button';
      submit.className = 'btn primary';
      submit.innerHTML = `${escapeHtml(form.dataset.guidedSubmit || 'Guardar')} ${icon('arrow-right')}`;
      submit.addEventListener('click', () => save(submit));
      const review = chip('Ver formulario completo', () => setMode('classic'), 'is-quiet');
      const actions = document.createElement('div');
      actions.className = 'guided-actions';
      actions.append(submit, review);
      composer.replaceChildren(actions);
      scrollDown();
      submit.focus({ preventScroll: true });
    };

    // Saving checks the whole form first: a field the conversation can ask goes back into it; anything else opens
    // the classic form with its inline errors.
    const save = button => {
      const errors = window.formValidation?.errors(form) || new Map();
      const control = [...errors.keys()][0];
      if (control) {
        const step = steps.find(item => item.control === control);
        if (step) {
          handled.delete(step);
          log.querySelector('.guided-summary')?.remove();
          say(`Antes de guardar: ${errors.get(control)}`, '', { think: false }).then(() => ask(step));
          return;
        }
        setMode('classic');
        form.requestSubmit();
        return;
      }
      button.disabled = true;
      button.textContent = 'Guardando…';
      form.requestSubmit();
    };

    // The contract form opens with free text: the assistant extracts what it can and only asks for the rest.
    const intro = async () => {
      started = true;
      busy = true;
      if (form.dataset.guidedExtractUrl) {
        const id = await say(form.dataset.guidedIntro);
        busy = false;
        const field = composeText({
          labelledBy: id,
          kind: 'textarea',
          placeholder: 'Ej. Laura Martínez, CC 52.345.678, entró el 15/01/2024 a Logística del Cauca como coordinadora, término fijo hasta el 14/01/2025, salario de 2,4 millones…',
          onSend: text => extract(text),
          extras: [chip('Prefiero que me preguntes uno por uno', () => { reply('Prefiero que me preguntes uno por uno'); advance(); }, 'is-quiet')],
        });
        field.rows = 4;
        return;
      }
      await say(form.dataset.guidedIntro || `Vamos con ${title.toLowerCase()}.`, '', { think: false });
      busy = false;
      advance();
    };

    const extract = async text => {
      if (text.trim().length < 15) {
        await say('Cuéntame un poco más: un nombre, unas fechas o el salario me sirven.', '', { think: false });
        return;
      }
      reply(text.trim());
      busy = true;
      composer.replaceChildren();
      const message = document.createElement('div');
      message.className = 'guided-msg is-ai';
      message.innerHTML = `<span class="guided-avatar" aria-hidden="true">${icon('sparkles')}</span><div class="guided-bubble"></div>`;
      log.append(message);
      const bubble = message.querySelector('.guided-bubble');
      const stop = thinking(bubble, 'extract');
      scrollDown();
      try {
        const data = await aiRequest(form.dataset.guidedExtractUrl, { method: 'POST', body: { text } });
        stop();
        const filled = [];
        Object.entries(data.fields).forEach(([name, value]) => {
          const control = form.querySelector(`[name="${CSS.escape(name)}"]`);
          if (!control) return;
          control.value = value;
          notify(control);
          control.closest('.field')?.classList.add('ai-filled');
          const step = steps.find(item => item.control === control);
          if (step && !window.formValidation?.check(control)) {
            handled.add(step);
            filled.push(step.label.toLowerCase());
          }
        });
        bubble.innerHTML = '<p></p>';
        const notes = data.notes?.length ? ` ${data.notes.join(' ')}` : '';
        await stream(bubble.querySelector('p'), filled.length
          ? `Encontré ${filled.length} ${filled.length === 1 ? 'dato' : 'datos'}: ${filled.join(', ')}. Te pregunto lo que falta.${notes}`
          : `No reconocí datos del contrato en ese texto, así que te pregunto uno por uno.${notes}`);
        busy = false;
        advance();
      } catch (error) {
        stop();
        bubble.innerHTML = `<p class="guided-error"></p>`;
        await stream(bubble.querySelector('p'), `${error.message} Podemos seguir campo por campo.`);
        busy = false;
        advance();
      }
    };

    // Answers typed into the classic form count as answered when switching back to the conversation.
    const changed = control => (control.tagName === 'SELECT' ? [...control.options].some(option => option.selected !== option.defaultSelected) : control.type === 'checkbox' ? control.checked !== control.defaultChecked : control.value !== control.defaultValue);
    const setMode = mode => {
      const guided = mode === 'guided';
      switcher.querySelector(`input[value="${mode}"]`).checked = true;
      panel.hidden = !guided;
      form.hidden = guided;
      if (extractCard) extractCard.hidden = guided;
      try { sessionStorage.setItem(key, mode); } catch { /* optional */ }
      if (!guided) return;
      steps.forEach(step => { if (step.control.value && changed(step.control)) handled.add(step); });
      if (!started) intro();
      else if (current) renderComposer(current, log.querySelector('.guided-msg.is-ai:last-of-type p')?.id || '');
      else if (!busy) advance();
    };
    switcher.addEventListener('change', event => setMode(event.target.value));

    let saved = null;
    try { saved = sessionStorage.getItem(key); } catch { /* optional */ }
    setMode(saved || form.dataset.guidedStart || 'classic');
  };

  document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('form[data-guided]').forEach(initGuided));
})();
