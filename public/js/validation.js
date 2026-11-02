// Client validation with zod. Each form's schema is built from its controls' own attributes (required, type, min,
// max, step, minlength, maxlength, pattern), so the Blade markup stays the single source of rules and the server
// keeps the final word. Messages render inline through app.js (`window.formErrors`), never as native bubbles.
import { z } from './vendor/zod.mjs';

const number = value => Number(value).toLocaleString('es-CO');
const SKIPPED = ['hidden', 'submit', 'button', 'reset', 'file', 'image'];

const validatable = control => control.name && !control.disabled && !control.readOnly && !SKIPPED.includes(control.type);

// The value rule for a non-empty control.
const valueRule = control => {
  if (control.type === 'number') {
    let rule = z.coerce.number({ invalid_type_error: 'Ingresa un número válido.' });
    if (control.min !== '') rule = rule.min(+control.min, `Ingresa un valor igual o mayor a ${number(control.min)}.`);
    if (control.max !== '') rule = rule.max(+control.max, `Ingresa un valor igual o menor a ${number(control.max)}.`);
    const step = control.getAttribute('step');
    if (step && step !== 'any') {
      const base = control.min !== '' ? +control.min : 0;
      rule = rule.refine(value => Math.abs(Math.round((value - base) / step) * step - (value - base)) < 1e-9, `Usa incrementos de ${number(step)}.`);
    }
    return rule;
  }
  let rule = z.string();
  if (control.type === 'email') rule = rule.email('Ingresa un correo válido, por ejemplo nombre@empresa.com.');
  if (control.type === 'url') rule = rule.url('Ingresa un enlace completo, por ejemplo https://empresa.com.');
  if (control.type === 'date') {
    rule = rule.regex(/^\d{4}-\d{2}-\d{2}$/, 'Ingresa una fecha válida.');
    if (control.min) rule = rule.refine(value => value >= control.min, `La fecha debe ser igual o posterior al ${control.min.split('-').reverse().join('/')}.`);
    if (control.max) rule = rule.refine(value => value <= control.max, `La fecha debe ser igual o anterior al ${control.max.split('-').reverse().join('/')}.`);
  }
  if (control.minLength > 0) rule = rule.min(control.minLength, `Escribe al menos ${control.minLength} caracteres.`);
  if (control.maxLength > 0) rule = rule.max(control.maxLength, `Escribe como máximo ${control.maxLength} caracteres.`);
  if (control.pattern) rule = rule.regex(new RegExp(`^(?:${control.pattern})$`, 'u'), control.title || 'Revisa el formato de este campo.');
  return rule;
};

const fieldSchema = control => {
  if (control.type === 'checkbox') {
    return control.required ? z.literal(true, { errorMap: () => ({ message: 'Marca esta casilla para continuar.' }) }) : z.boolean();
  }
  const empty = control.tagName === 'SELECT' ? 'Selecciona una opción.' : control.type === 'date' ? 'Ingresa una fecha.' : 'Completa este campo.';
  const text = z.string().trim();
  return control.required
    ? text.min(1, empty).pipe(valueRule(control))
    : text.transform(value => value || undefined).pipe(valueRule(control).optional());
};

// Errors keyed by control, first message only. Checks zod cannot see from the value come first: a typed date the
// picker could not parse, and a number input the browser already rejected (its value reads as empty).
const validate = form => {
  const controls = [...form.elements].filter(validatable);
  const errors = new Map();
  const shape = {};
  const data = {};
  for (const control of controls) {
    const visual = control.visualControl;
    if (visual?.classList.contains('date-text') && visual.value && !control.value) errors.set(control, 'Fecha inválida. Usa el formato dd/mm/aaaa.');
    else if (control.validity?.badInput) errors.set(control, 'Ingresa un número válido.');
    else {
      shape[control.name] = fieldSchema(control);
      data[control.name] = control.type === 'checkbox' ? control.checked : control.value;
    }
  }
  const result = z.object(shape).safeParse(data);
  if (!result.success) {
    for (const issue of result.error.issues) {
      const control = controls.find(item => item.name === issue.path[0]);
      if (control && !errors.has(control)) errors.set(control, issue.message);
    }
  }
  return { controls, errors };
};

const apply = ({ controls, errors }, only) => {
  for (const control of controls) {
    if (only && control !== only) continue;
    if (errors.has(control)) window.formErrors?.show(control, errors.get(control));
    else window.formErrors?.clear(control);
  }
};

// The guided forms (guided.js) check each answer, and the whole form before saving, with these same rules.
window.formValidation = {
  check: control => validate(control.form).errors.get(control) || null,
  errors: form => validate(form).errors,
};

const nativeFor = element => element.closest('.field, .custom-select, .date-input')?.querySelector('input[name], select[name], textarea[name]') || element;

// Native bubbles are switched off as early as possible: now, once the document is parsed, and for any form created
// later as soon as one of its fields takes focus. Without JS the browser's own validation still applies.
const silenceNative = () => document.querySelectorAll('form:not([method=dialog])').forEach(form => { form.noValidate = true; });
silenceNative();
document.addEventListener('DOMContentLoaded', silenceNative);
document.addEventListener('focusin', event => {
  if (event.target.form && event.target.form.method !== 'dialog') event.target.form.noValidate = true;
});

// Runs after the delete confirmation (registered earlier): a submit that dialog already held back is skipped and
// validated when the dialog submits it again.
document.addEventListener('submit', event => {
  const form = event.target;
  if (event.defaultPrevented || form.method === 'dialog') return;
  const result = validate(form);
  form.dataset.validated = '1';
  apply(result);
  if (!result.errors.size) return;
  event.preventDefault();
  event.stopPropagation();
  const invalid = [...result.errors.keys()];
  invalid.forEach(control => window.formErrors?.shake(control));
  const first = invalid[0].visualControl || invalid[0];
  first.focus({ preventScroll: true });
  first.scrollIntoView({ block: 'center', behavior: 'smooth' });
  if (invalid.length > 1) window.toast?.({ type: 'error', title: `Revisa ${invalid.length} campos marcados`, message: 'Corrige los datos resaltados para continuar.' });
}, true);

// After a field loses focus its value is checked again, so a correction in progress is never flagged mid-typing.
// Untouched empty fields wait for the first submit.
document.addEventListener('focusout', event => {
  const form = event.target.form || event.target.closest?.('form');
  if (!form || form.method === 'dialog' || !event.target.matches('input, select, textarea, .cs-trigger')) return;
  const control = nativeFor(event.target);
  if (!validatable(control) || (!form.dataset.validated && !control.value && !control.visualControl?.value)) return;
  // A custom select's panel takes focus while it is open; wait until the choice has been made.
  setTimeout(() => {
    if (control.visualControl?.getAttribute('aria-expanded') === 'true') return;
    apply(validate(form), control);
  });
});
