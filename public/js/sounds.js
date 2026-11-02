// Interaction sounds with cuelume (vendored in js/vendor/cuelume; synthesized live, no audio files).
// The "bubble" theme everywhere: knocks, drips and corks, soft enough for all-day use at these emphases.
//
// Controls get their cue declaratively: `mark()` adds the data-cuelume-* attribute that fits each kind of
// control, and cuelume's `bind()` plays it, including on Enter and Space. Outcomes (toasts, AI answers) are
// imperative: classic scripts call `window.sound.play()`, modules import `play` from here.
// The preference lives in localStorage ("sound": "on" | "off"); browsers stay silent until the first click anyway.
import { bind, play as cue, setEnabled, setTheme, setVolume, unlock } from './vendor/cuelume/index.js';

const KEY = 'sound';
const VOLUME = 0.7;

// Most specific first: an element takes the first rule it matches and keeps any attribute already in the markup.
const RULES = [
  ['#sound-toggle, #theme-toggle, input[type=checkbox]', 'toggle'],
  ['input[type=radio], select, [role=option], .dp-day, .segmented input', 'select'],
  ['.dp-panel button', 'select', 'subtle'],
  ['#mobile-menu, [aria-haspopup]', 'open', 'subtle'],
  ['#sidebar-backdrop, .toast-close', 'close', 'subtle'],
  ['.confirm-dialog .btn.destructive', 'close', 'strong'],
  ['.btn.ghost, .ai-chip, .icon-button, .table-action, .ai-copy, .mini-delete', 'tap', 'subtle'],
  ['.btn, .ai-send, button[type=submit]', 'tap'],
  ['.nav-link[href]', 'navigate'],
  ['a[href]:not([download]):not([href^="#"])', 'navigate', 'subtle'],
];
const ATTRIBUTES = ['tap', 'type', 'select', 'toggle', 'open', 'close', 'navigate'].map(name => `data-cuelume-${name}`);
const SELECTOR = RULES.map(([selector]) => selector).join(', ');

const markOne = element => {
  // data-sound="off" is for controls whose script plays its own cue (the assistant's open and close).
  if (element.closest('[data-sound=off]') || ATTRIBUTES.some(attribute => element.hasAttribute(attribute))) return;
  const rule = RULES.find(([selector]) => element.matches(selector));
  if (!rule) return;
  // A toggle with a native change event (checkbox, select) plays on change; cuelume picks that up by itself.
  element.setAttribute(`data-cuelume-${rule[1]}`, '');
  if (rule[2] && !element.closest('[data-cuelume-emphasis]')) element.setAttribute('data-cuelume-emphasis', rule[2]);
};
const mark = root => {
  if (root.nodeType !== 1) return;
  if (root.matches(SELECTOR)) markOne(root);
  root.querySelectorAll(SELECTOR).forEach(markOne);
};

const stored = () => { try { return localStorage.getItem(KEY) !== 'off'; } catch { return true; } };
let enabled = stored();

export const play = (name, options) => { if (enabled) cue(name, options); };

const toggle = document.querySelector('#sound-toggle');
const render = () => {
  toggle?.setAttribute('aria-pressed', String(enabled));
  toggle?.querySelector('.t-icon-swap')?.setAttribute('data-state', enabled ? 'a' : 'b');
};

setTheme('bubble');
setVolume(VOLUME);
setEnabled(enabled);
render();
toggle?.addEventListener('click', async () => {
  enabled = !enabled;
  try { localStorage.setItem(KEY, enabled ? 'on' : 'off'); } catch { /* the choice still holds for this page */ }
  render();
  // Off is silent at once; on is heard right away, from this click.
  setEnabled(enabled);
  if (enabled) await unlock();
});

mark(document.body);
new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(mark))).observe(document.body, { childList: true, subtree: true });
bind();

window.sound = { play };
