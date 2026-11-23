/*! Chaos Filet editor button 1.4.0 | Chaotic Fish Design | GPL-2.0-or-later */
// Builds an {article …} tag for the Chaos Filet content plugin: find an article, tick the
// fields in the order you want them, pick options, then Insert or Copy.
// Style: the shared CFD editor popup (claude/cfd-editor-popup-style.md), prefix cfp-.
// Colours read --bs-* (Bootstrap sites) then the unprefixed names Joomla's Atum admin
// template uses (--body-bg etc.), so the popup follows Atum's dark mode. .cfp-sec also undoes
// Atum's own <details> styling (background, border, 2rem margin, padding, link-coloured summary).
import { JoomlaEditorButton } from 'editor-api';

const P = 'cfp';
const KEY = 'PLG_EDITORS-XTD_CHAOSFILET_JS_';
const OPTS = (window.Joomla && Joomla.getOptions('plg_editors_xtd_chaosfilet')) || {};

const EN = {
  TITLE: 'Insert Chaos Filet tag',
  CLOSE: 'Close',
  INTRO: 'Pick an article and the fields you want. Fields come out in the order you tick them.',
  SEC_ARTICLE: 'Article',
  SEC_FIELDS: 'Fields',
  SEC_CUSTOM: 'Custom fields',
  SEC_OPTIONS: 'Options',
  SEARCH: 'Find an article',
  SEARCH_HINT: 'Title, alias or ID',
  RESULTS: 'Articles',
  NO_RESULTS: 'No articles found.',
  LOADING: 'Searching…',
  SEARCH_FAILED: 'Search failed. You can still type a tag by hand.',
  UNPUBLISHED: 'unpublished',
  ARCHIVED: 'archived',
  FIELDS_HINT: 'None ticked shows the body.',
  SHORTCUTS: 'Shortcuts',
  SHORTCUT_COMMON: 'common: title, image, body, date, author, link',
  SHORTCUT_ALL: 'all: every field, labelled, for debugging',
  CF_NONE: 'Not used',
  CF_RENDERED: 'Rendered',
  CF_RAW: 'Stored value',
  CF_HINT: 'Rendered shows the field the way Joomla displays it. Stored value is the raw value, for attributes.',
  REFERENCE: 'Refer to it by',
  REF_ID: 'ID',
  REF_ALIAS: 'Alias (survives staging-to-live moves)',
  LAYOUT: 'Layout',
  LAYOUT_DEFAULT: 'Default',
  LAYOUT_HINT: 'Layouts other than Default may ignore the field list (card does).',
  INLINE: 'Inline (no wrapper divs)',
  INLINE_HINT: 'Use inline when the tag sits inside a sentence or an attribute.',
  YOUR_TAG: 'Your tag',
  PICK_FIRST: 'Pick an article first.',
  COPY: 'Copy',
  COPIED: 'Copied',
  COPY_FAILED: "Couldn't copy. Select the tag and copy it by hand.",
  RESET: 'Reset',
  CANCEL: 'Cancel',
  INSERT: 'Insert',
  PLUGIN_OFF: "The Content - Chaos Filet plugin isn't enabled, so tags will show as plain text until it is.",
  INSERT_COPIED: "Couldn't insert automatically; the tag is on your clipboard, paste it into the editor.",
  INSERT_FAILED: "Couldn't insert the tag:",
};

const t = (k) => {
  const v = window.Joomla && Joomla.Text ? Joomla.Text._(KEY + k) : '';
  return v && v !== KEY + k ? v : EN[k];
};

const BUILTIN = [
  'title', 'link', 'readmore', 'url', 'excerpt', 'intro', 'fulltext', 'body',
  'image', 'fulltext_image', 'date', 'modified', 'author', 'category', 'tags',
  'hits', 'id', 'alias',
];
const SHORTCUTS = [['common', 'SHORTCUT_COMMON'], ['all', 'SHORTCUT_ALL']];

const CSS_TEXT = `.cfp{background:var(--bs-body-bg,var(--body-bg,#fff));border:1px solid var(--bs-border-color,var(--border-color,#ccc));border-radius:6px;box-shadow:0 10px 40px rgba(0,0,0,.25);color:var(--bs-body-color,var(--body-color,#212529));font-family:inherit;max-height:min(90vh,860px);max-width:640px;padding:0;width:92%}
.cfp[open]{display:flex;flex-direction:column}
.cfp::backdrop{background:rgba(0,0,0,.45)}
.cfp *{box-sizing:border-box}
.cfp-head{align-items:center;display:flex;gap:12px;justify-content:space-between;padding:1.25rem 1.25rem .75rem}
.cfp-head h3{color:inherit;font-size:1.1rem;font-weight:600;margin:0}
.cfp-x{align-items:center;background:none;border:0;border-radius:6px;color:inherit;cursor:pointer;display:flex;flex:none;font-size:1.4rem;height:32px;justify-content:center;line-height:1;opacity:.6;padding:0;width:32px}
.cfp-x:hover,.cfp-x:focus-visible{background:var(--bs-tertiary-bg,var(--tertiary-bg,rgba(0,0,0,.06)));opacity:1}
.cfp-body{display:flex;flex:1;flex-direction:column;gap:.75rem;min-height:0;overflow-y:auto;padding:0 1.25rem 1rem}
.cfp-note{background:var(--bs-warning-bg-subtle,var(--warning-bg-subtle,#fff3cd));border-radius:6px;color:var(--bs-warning-text-emphasis,var(--warning-text-emphasis,#664d03));font-size:.88em;margin:0;padding:.6rem .8rem}
.cfp-hint{color:var(--bs-secondary-color,var(--secondary-color,#6c757d));font-size:.85em;margin:0}
.cfp-sec{background:none;border:1px solid var(--bs-border-color,var(--border-color,#dee2e6));border-radius:6px;margin:0;padding:0}
.cfp-sec>summary{align-items:center;color:inherit;cursor:pointer;display:flex;font-size:.95em;font-weight:600;gap:.5rem;list-style:none;padding:.65rem .9rem;user-select:none}
.cfp-sec>summary~*{margin-top:0}
.cfp-sec>summary::-webkit-details-marker{display:none}
.cfp-sec>summary::before{content:"▸";display:inline-block;font-size:.8em;opacity:.6;transition:transform .15s}
.cfp-sec[open]>summary::before{transform:rotate(90deg)}
.cfp-sec[open]>summary{border-bottom:1px solid var(--bs-border-color,var(--border-color,#dee2e6))}
.cfp-sec[hidden]{display:none}
.cfp-count{background:#05b5c3;border-radius:999px;color:#fff;font-size:.72rem;font-weight:700;line-height:1.5;padding:0 .5rem}
.cfp-count[hidden]{display:none}
.cfp-pick{color:var(--bs-secondary-color,var(--secondary-color,#6c757d));font-weight:400;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cfp-grid{display:grid;gap:.9rem 1rem;grid-template-columns:repeat(2,minmax(0,1fr));padding:.9rem}
.cfp-field{display:flex;flex-direction:column;gap:.3rem;min-width:0}
.cfp-field[hidden]{display:none}
.cfp-wide{grid-column:1/-1}
.cfp-field>label{font-size:.92em;font-weight:600;margin:0}
.cfp-check{align-items:center;flex-direction:row;flex-wrap:wrap;gap:.2rem .5rem}
.cfp-check>label{font-weight:400}
.cfp-check input{flex:none;height:1.05em;margin:0;width:1.05em}
.cfp-check .cfp-hint{flex-basis:100%;padding-left:1.55em}
.cfp-checks{display:grid;gap:.55rem 1rem;grid-column:1/-1;grid-template-columns:repeat(2,minmax(0,1fr))}
.cfp-changed>label::after{color:#05b5c3;content:" •";font-weight:700}
.cfp-results{border:1px solid var(--bs-border-color,var(--border-color,#dee2e6));border-radius:6px;max-height:14rem;overflow-y:auto}
.cfp-results:empty{display:none}
.cfp-result{align-items:center;border-bottom:1px solid var(--bs-border-color,var(--border-color,#dee2e6));cursor:pointer;display:flex;gap:.6rem;margin:0;padding:.5rem .7rem}
.cfp-result:last-child{border-bottom:0}
.cfp-result:hover{background:var(--bs-tertiary-bg,var(--tertiary-bg,#f8f9fa))}
.cfp-result:has(input:checked){background:rgba(5,181,195,.14)}
.cfp-result input{flex:none;margin:0}
.cfp-result-text{display:flex;flex-direction:column;min-width:0}
.cfp-result-title{font-weight:600;overflow-wrap:anywhere}
.cfp-tagbox{border-top:1px solid var(--bs-border-color,var(--border-color,#dee2e6));display:flex;flex-direction:column;gap:.35rem;padding:.75rem 1.25rem}
.cfp-tagbar{align-items:center;display:flex;gap:.5rem;justify-content:space-between}
.cfp-tag{background:#1b1b2b;border-radius:6px;color:#f5f7ff;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:.82rem;margin:0;max-height:7em;overflow:auto;overflow-wrap:anywhere;padding:.6rem .75rem;white-space:pre-wrap}
.cfp-err{color:var(--bs-danger-text-emphasis,var(--danger-text-emphasis,#a33));font-size:.85em;margin:0}
.cfp-err[hidden]{display:none}
.cfp-foot{align-items:center;display:flex;flex-wrap:wrap;gap:8px;padding:0 1.25rem 1.25rem}
.cfp-foot .btn{white-space:nowrap}
.cfp-foot .cfp-reset{margin-right:auto;padding-left:0;padding-right:0}
@media (max-width: 560px) {
  .cfp-checks,
  .cfp-grid {
    grid-template-columns: 1fr;
  }
  .cfp-foot .cfp-reset {
    flex-basis: 100%;
    text-align: left;
  }
  .cfp-foot .btn:not(.cfp-reset) {
    flex: 1;
  }
}
@media (prefers-reduced-motion: reduce) {
  .cfp-sec > summary::before {
    transition: none;
  }
}`;

function injectStyle() {
  if (document.getElementById(`${P}-style`)) return;
  const s = document.createElement('style');
  s.id = `${P}-style`;
  s.textContent = CSS_TEXT;
  document.head.append(s);
}

function el(tag, attrs = {}, ...children) {
  const node = document.createElement(tag);
  Object.entries(attrs).forEach(([k, v]) => {
    if (v === false || v === null || v === undefined) return;
    if (k === 'class') node.className = v;
    else if (k === 'text') node.textContent = v;
    else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
    else node.setAttribute(k, v === true ? '' : v);
  });
  children.flat().forEach((c) => c !== null && c !== undefined && c !== false && node.append(c));
  return node;
}

let uid = 0;
const nextId = (p) => `${P}-${p}-${(uid += 1)}`;

async function searchArticles(q, signal) {
  const url = `${OPTS.endpoint}&q=${encodeURIComponent(q)}&${encodeURIComponent(OPTS.token)}=1`;
  const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal });
  const json = await res.json();
  if (!res.ok || json.success === false) {
    throw new Error(json.message || `HTTP ${res.status}`);
  }
  return Array.isArray(json.data) && Array.isArray(json.data[0]) ? json.data[0] : [];
}

export function buildTag(state) {
  if (!state.article) return '';
  const a = state.article;
  let ref = String(a.id);
  if (state.refMode === 'alias' && a.alias) {
    ref = a.alias_unique || !a.category_alias ? a.alias : `${a.category_alias}/${a.alias}`;
  }
  const bang = state.inline ? '!' : '';
  const fields = state.order.map((f) => f + bang);
  const fieldPart = fields.length ? `:${fields.join(',')}` : '';
  const layoutPart = state.layout ? `|${state.layout}` : '';
  return `{article ${ref}${fieldPart}${layoutPart}}`;
}

async function copyText(text) {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch (e) {
    return false;
  }
}

function section(label, open, ...content) {
  const count = el('span', { class: `${P}-count`, hidden: true });
  const extra = el('span', { class: `${P}-pick` });
  const box = el('details', { class: `${P}-sec`, open },
    el('summary', {}, label, count, extra), ...content);
  return { box, count, extra };
}

function checkField(name, value, label, hint) {
  const id = nextId('cb');
  return el('div', { class: `${P}-field ${P}-check` },
    el('input', { type: 'checkbox', class: 'form-check-input', id, name, value }),
    el('label', { for: id, text: label }),
    hint ? el('p', { class: `${P}-hint`, text: hint }) : null);
}

function openDialog() {
  injectStyle();

  return new Promise((resolve) => {
    const state = { article: null, refMode: 'id', order: [], inline: false, layout: '' };
    let aborter = null;
    let timer = null;
    let copyTimer = null;

    const titleId = nextId('title');

    // Article
    const search = el('input', {
      type: 'search', class: 'form-control form-control-sm', id: nextId('q'), autocomplete: 'off',
      placeholder: t('SEARCH_HINT'),
    });
    const status = el('p', { class: `${P}-hint`, role: 'status', 'aria-live': 'polite' });
    const results = el('div', { class: `${P}-results`, role: 'radiogroup', 'aria-label': t('RESULTS') });
    const art = section(t('SEC_ARTICLE'), true,
      el('div', { class: `${P}-grid` },
        el('div', { class: `${P}-field ${P}-wide` },
          el('label', { for: search.id, text: t('SEARCH') }), search, status, results)));

    // Fields
    const fieldChecks = BUILTIN.map((f) => checkField(`${P}-field`, f, f));
    const shortcutChecks = SHORTCUTS.map(([f, k]) => checkField(`${P}-field`, f, f, t(k).replace(/^[a-z]+: /, '')));
    const flds = section(t('SEC_FIELDS'), true,
      el('div', { class: `${P}-grid` },
        el('p', { class: `${P}-hint ${P}-wide`, text: t('FIELDS_HINT') }),
        el('div', { class: `${P}-checks` }, fieldChecks),
        el('p', { class: `${P}-hint ${P}-wide`, text: t('SHORTCUTS') }),
        el('div', { class: `${P}-checks` }, shortcutChecks)));

    // Custom fields (only when the site has some)
    const cfSelects = (OPTS.customFields || []).map((cf) => {
      const id = nextId('cf');
      const select = el('select', { class: 'form-select form-select-sm', id, 'data-cf': cf.name },
        el('option', { value: '', text: t('CF_NONE') }),
        el('option', { value: `field:${cf.name}`, text: t('CF_RENDERED') }),
        el('option', { value: `fieldraw:${cf.name}`, text: t('CF_RAW') }));
      return el('div', { class: `${P}-field` }, el('label', { for: id, text: `${cf.label} (${cf.name})` }), select);
    });
    const cust = section(t('SEC_CUSTOM'), false,
      el('div', { class: `${P}-grid` },
        el('p', { class: `${P}-hint ${P}-wide`, text: t('CF_HINT') }), cfSelects));
    if (!cfSelects.length) cust.box.hidden = true;

    // Options
    const refSelect = el('select', { class: 'form-select form-select-sm', id: nextId('ref') },
      el('option', { value: 'id', text: t('REF_ID') }),
      el('option', { value: 'alias', text: t('REF_ALIAS') }));
    const layoutSelect = el('select', { class: 'form-select form-select-sm', id: nextId('layout') },
      el('option', { value: '', text: t('LAYOUT_DEFAULT') }),
      (OPTS.layouts || []).map((l) => el('option', { value: l, text: l })));
    const inlineField = checkField(`${P}-inline`, '1', t('INLINE'), t('INLINE_HINT'));
    inlineField.classList.add(`${P}-wide`);
    const inlineBox = inlineField.querySelector('input');
    const refField = el('div', { class: `${P}-field` }, el('label', { for: refSelect.id, text: t('REFERENCE') }), refSelect);
    const layoutField = el('div', { class: `${P}-field` }, el('label', { for: layoutSelect.id, text: t('LAYOUT') }), layoutSelect,
      el('p', { class: `${P}-hint`, text: t('LAYOUT_HINT') }));
    const opts = section(t('SEC_OPTIONS'), true,
      el('div', { class: `${P}-grid` }, refField, layoutField, inlineField));

    // Tag strip and footer
    const tagPre = el('pre', { class: `${P}-tag`, id: nextId('tag') });
    const copyBtn = el('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', text: t('COPY'), disabled: true });
    const copyMsg = el('p', { class: `${P}-err`, role: 'status', hidden: true });
    const live = el('span', { class: 'visually-hidden', 'aria-live': 'polite' });
    const resetBtn = el('button', { type: 'button', class: `btn btn-link btn-sm ${P}-reset`, text: t('RESET') });
    const cancelBtn = el('button', { type: 'button', class: 'btn btn-outline-secondary', text: t('CANCEL') });
    const insertBtn = el('button', { type: 'button', class: 'btn btn-primary', text: t('INSERT'), disabled: true });
    const closeBtn = el('button', { type: 'button', class: `${P}-x`, 'aria-label': t('CLOSE'), text: '×' });

    const dialog = el('dialog', { class: P, 'aria-labelledby': titleId },
      el('div', { class: `${P}-head` }, el('h3', { id: titleId, text: t('TITLE') }), closeBtn),
      el('div', { class: `${P}-body` },
        el('p', { class: `${P}-hint`, text: t('INTRO') }),
        OPTS.pluginEnabled === false ? el('p', { class: `${P}-hint ${P}-note`, text: t('PLUGIN_OFF') }) : null,
        art.box, flds.box, cust.box, opts.box),
      el('div', { class: `${P}-tagbox` },
        el('div', { class: `${P}-tagbar` }, el('span', { class: `${P}-hint`, text: t('YOUR_TAG') }), copyBtn),
        tagPre, copyMsg, live),
      el('div', { class: `${P}-foot` }, resetBtn, cancelBtn, insertBtn));

    const setCount = (sec, n) => {
      sec.count.hidden = !n;
      sec.count.textContent = n ? String(n) : '';
    };

    const refresh = () => {
      const tag = buildTag(state);
      tagPre.textContent = tag || t('PICK_FIRST');
      insertBtn.disabled = !tag;
      copyBtn.disabled = !tag;
      art.extra.textContent = state.article ? state.article.title : '';
      setCount(flds, state.order.filter((f) => !f.includes(':')).length);
      setCount(cust, state.order.filter((f) => f.includes(':')).length);
      const changed = [refField, layoutField, inlineField];
      refField.classList.toggle(`${P}-changed`, state.refMode !== 'id');
      layoutField.classList.toggle(`${P}-changed`, state.layout !== '');
      inlineField.classList.toggle(`${P}-changed`, state.inline);
      setCount(opts, changed.filter((f) => f.classList.contains(`${P}-changed`)).length);
    };

    const renderResults = (items) => {
      results.replaceChildren();
      if (!items.length) {
        status.textContent = t('NO_RESULTS');
        return;
      }
      status.textContent = '';
      items.forEach((a) => {
        const id = nextId('art');
        const input = el('input', { type: 'radio', class: 'form-check-input', name: `${P}-article`, id, value: String(a.id) });
        if (state.article && state.article.id === a.id) input.checked = true;
        input.addEventListener('change', () => { state.article = a; refresh(); });
        const meta = [a.category, `ID ${a.id}`];
        if (a.state === 0) meta.push(t('UNPUBLISHED'));
        if (a.state === 2) meta.push(t('ARCHIVED'));
        results.append(el('label', { class: `${P}-result`, for: id }, input,
          el('span', { class: `${P}-result-text` },
            el('span', { class: `${P}-result-title`, text: a.title }),
            el('span', { class: `${P}-hint`, text: meta.filter(Boolean).join(' · ') }))));
      });
    };

    const runSearch = () => {
      if (aborter) aborter.abort();
      aborter = new AbortController();
      status.className = `${P}-hint`;
      status.textContent = t('LOADING');
      searchArticles(search.value.trim(), aborter.signal)
        .then(renderResults)
        .catch((err) => {
          if (err.name === 'AbortError') return;
          // eslint-disable-next-line no-console
          console.error('[chaosfilet]', err);
          results.replaceChildren();
          status.className = `${P}-err`;
          status.textContent = t('SEARCH_FAILED');
        });
    };

    search.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(runSearch, 250);
    });
    search.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });

    dialog.addEventListener('change', (e) => {
      const target = e.target;
      if (target.name === `${P}-field`) {
        state.order = state.order.filter((f) => f !== target.value);
        if (target.checked) state.order.push(target.value);
      } else if (target.dataset && target.dataset.cf !== undefined) {
        const name = target.dataset.cf;
        state.order = state.order.filter((f) => f !== `field:${name}` && f !== `fieldraw:${name}`);
        if (target.value) state.order.push(target.value);
      } else if (target === refSelect) {
        state.refMode = refSelect.value;
      } else if (target === layoutSelect) {
        state.layout = layoutSelect.value;
      } else if (target === inlineBox) {
        state.inline = inlineBox.checked;
      } else {
        return;
      }
      refresh();
    });

    copyBtn.addEventListener('click', async () => {
      const tag = buildTag(state);
      if (!tag) return;
      const ok = await copyText(tag);
      clearTimeout(copyTimer);
      if (ok) {
        copyMsg.hidden = true;
        copyBtn.textContent = t('COPIED');
        live.textContent = t('COPIED');
        copyTimer = setTimeout(() => { copyBtn.textContent = t('COPY'); live.textContent = ''; }, 1800);
      } else {
        copyMsg.textContent = t('COPY_FAILED');
        copyMsg.hidden = false;
        const r = document.createRange();
        r.selectNodeContents(tagPre);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(r);
      }
    });

    resetBtn.addEventListener('click', () => {
      state.article = null;
      state.order = [];
      state.refMode = 'id';
      state.layout = '';
      state.inline = false;
      dialog.querySelectorAll('input[type=checkbox], input[type=radio]').forEach((i) => { i.checked = false; });
      cfSelects.forEach((f) => { f.querySelector('select').value = ''; });
      refSelect.value = 'id';
      layoutSelect.value = '';
      copyMsg.hidden = true;
      refresh();
      search.focus();
    });

    const finish = (value) => {
      clearTimeout(timer);
      clearTimeout(copyTimer);
      if (aborter) aborter.abort();
      dialog.close();
      dialog.remove();
      resolve(value);
    };

    closeBtn.addEventListener('click', () => finish(null));
    cancelBtn.addEventListener('click', () => finish(null));
    insertBtn.addEventListener('click', () => finish(buildTag(state) || null));
    dialog.addEventListener('cancel', (e) => { e.preventDefault(); finish(null); });

    document.body.append(dialog);
    refresh();
    dialog.showModal();
    search.focus();
    runSearch();
  });
}

function notify(message, type = 'message') {
  if (window.Joomla && Joomla.renderMessages) {
    Joomla.renderMessages({ [type]: [message] });
  }
}

JoomlaEditorButton.registerAction('chaosfilet-insert', async (editor) => {
  const tag = await openDialog();
  if (!tag) return;

  try {
    editor.replaceSelection(tag);
  } catch (err) {
    // eslint-disable-next-line no-console
    console.error('[chaosfilet] Failed to insert into editor:', err);
    const copied = await copyText(tag);
    notify(copied ? t('INSERT_COPIED') : `${t('INSERT_FAILED')} ${tag}`, 'warning');
  }
});
