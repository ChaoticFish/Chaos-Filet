/*! Chaos Filet editor button 1.3.0 | Chaotic Fish Design | GPL-2.0-or-later */
/**
 * Chaos Filet editor button: dialog that builds an {article …} tag.
 *
 * Pick an article (search by title, alias or ID), tick fields in the order
 * you want them, optionally make them inline ("!") and choose a layout,
 * then Insert. The tag preview updates live and is exactly what gets
 * inserted.
 */

import { JoomlaEditorButton } from 'editor-api';

const OPTS = (window.Joomla && Joomla.getOptions('plg_editors_xtd_chaosfilet')) || {};

const BUILTIN = [
    'title', 'link', 'readmore', 'url', 'excerpt', 'intro', 'fulltext', 'body',
    'image', 'fulltext_image', 'date', 'modified', 'author', 'category', 'tags',
    'hits', 'id', 'alias',
];
const SHORTCUTS = ['common', 'all'];

const t = (key, fallback) => {
    const full = `PLG_EDITORS-XTD_CHAOSFILET_JS_${key}`;
    const v = window.Joomla && Joomla.Text ? Joomla.Text._(full) : '';
    return v && v !== full ? v : fallback;
};

function el(tag, attrs = {}, ...children) {
    const node = document.createElement(tag);
    Object.entries(attrs).forEach(([k, v]) => {
        if (v === false || v === null || v === undefined) return;
        if (k === 'class') node.className = v;
        else if (k === 'text') node.textContent = v;
        else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
        else node.setAttribute(k, v === true ? '' : v);
    });
    children.flat().forEach((c) => c && node.append(c));
    return node;
}

let uid = 0;
const nextId = (p) => `cff-${p}-${(uid += 1)}`;

function checkbox(name, label, value) {
    const id = nextId('cb');
    return el('label', { class: 'cff-check', for: id },
        el('input', { type: 'checkbox', id, name, value }),
        el('span', { text: label }));
}

async function searchArticles(q, signal) {
    const url = `${OPTS.endpoint}&q=${encodeURIComponent(q)}&${encodeURIComponent(OPTS.token)}=1`;
    const res = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal });
    const json = await res.json();
    if (!res.ok || json.success === false) {
        throw new Error(json.message || `HTTP ${res.status}`);
    }
    return Array.isArray(json.data) && Array.isArray(json.data[0]) ? json.data[0] : [];
}

function buildTag(state) {
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

function openDialog() {
    return new Promise((resolve) => {
        const state = { article: null, refMode: 'id', order: [], inline: false, layout: '' };
        let aborter = null;
        let timer = null;

        const titleId = nextId('title');
        const results = el('div', { class: 'cff-results', role: 'radiogroup', 'aria-label': t('ARTICLE', 'Article') });
        const status = el('p', { class: 'cff-status', role: 'status', 'aria-live': 'polite' });
        const search = el('input', {
            type: 'search', class: 'cff-input', id: nextId('q'), autocomplete: 'off',
            placeholder: t('SEARCH_HINT', 'Title, alias or ID'),
        });
        const preview = el('output', { class: 'cff-preview' });
        const insertBtn = el('button', { type: 'button', class: 'cff-btn cff-btn--primary', text: t('INSERT', 'Insert'), disabled: true });
        const cancelBtn = el('button', { type: 'button', class: 'cff-btn', text: t('CANCEL', 'Cancel') });

        const refAlias = el('input', { type: 'radio', name: 'cff-ref', value: 'alias', id: nextId('ref') });
        const refId = el('input', { type: 'radio', name: 'cff-ref', value: 'id', id: nextId('ref'), checked: true });

        const builtinBox = el('div', { class: 'cff-grid' }, BUILTIN.map((f) => checkbox('cff-field', f, f)));
        const shortcutBox = el('div', { class: 'cff-grid' }, SHORTCUTS.map((f) => checkbox('cff-field', f, f)));

        const cfRows = (OPTS.customFields || []).map((cf) => {
            const id = nextId('cf');
            const select = el('select', { class: 'cff-select', id, 'data-cf': cf.name },
                el('option', { value: '', text: t('CF_NONE', '—') }),
                el('option', { value: `field:${cf.name}`, text: t('CF_RENDERED', 'Rendered') }),
                el('option', { value: `fieldraw:${cf.name}`, text: t('CF_RAW', 'Stored value') }));
            return el('div', { class: 'cff-cf' },
                el('label', { for: id }, el('span', { text: cf.label }), ' ', el('code', { text: cf.name })),
                select);
        });

        const inlineBox = el('input', { type: 'checkbox', id: nextId('inline') });
        const layoutSelect = el('select', { class: 'cff-select', id: nextId('layout') },
            el('option', { value: '', text: t('LAYOUT_DEFAULT', 'Default') }),
            (OPTS.layouts || []).map((l) => el('option', { value: l, text: l })));

        const dialog = el('dialog', { class: 'cff-dialog', 'aria-labelledby': titleId },
            el('form', { class: 'cff-form', onsubmit: (e) => e.preventDefault() },
                el('h2', { id: titleId, class: 'cff-title', text: t('TITLE', 'Insert Chaos Filet tag') }),
                OPTS.pluginEnabled === false
                    ? el('p', { class: 'cff-warn', text: t('PLUGIN_OFF', 'The Content - Chaos Filet plugin is not enabled, so tags will show as plain text until it is.') })
                    : null,

                el('div', { class: 'cff-section' },
                    el('label', { for: search.id, class: 'cff-label', text: t('SEARCH', 'Find an article') }),
                    search, status, results),

                el('fieldset', { class: 'cff-section cff-inline' },
                    el('legend', { class: 'cff-label', text: t('REFERENCE', 'Refer to it by') }),
                    el('label', { for: refId.id }, refId, ' ', t('REF_ID', 'ID')),
                    el('label', { for: refAlias.id }, refAlias, ' ', t('REF_ALIAS', 'Alias'))),

                el('fieldset', { class: 'cff-section' },
                    el('legend', { class: 'cff-label', text: t('FIELDS', 'Fields') }),
                    el('p', { class: 'cff-hint', text: t('FIELDS_HINT', 'Output follows the order you tick them. None ticked = body.') }),
                    builtinBox,
                    el('p', { class: 'cff-sub', text: t('SHORTCUTS', 'Shortcuts') }),
                    shortcutBox,
                    cfRows.length ? el('p', { class: 'cff-sub', text: t('CUSTOM_FIELDS', 'Custom fields') }) : null,
                    cfRows),

                el('div', { class: 'cff-section cff-row' },
                    el('label', { for: inlineBox.id, class: 'cff-check' }, inlineBox,
                        el('span', { text: t('INLINE', 'Inline (no wrapper divs)') })),
                    el('label', { for: layoutSelect.id, class: 'cff-layout' },
                        el('span', { text: t('LAYOUT', 'Layout') }), ' ', layoutSelect)),
                el('p', { class: 'cff-hint', text: t('INLINE_HINT', 'Use inline when the tag sits inside a sentence or an attribute.') }),

                el('div', { class: 'cff-foot' },
                    el('span', { class: 'cff-label', text: t('PREVIEW', 'Tag') }), preview,
                    el('div', { class: 'cff-actions' }, cancelBtn, insertBtn))));

        const refresh = () => {
            const tag = buildTag(state);
            preview.textContent = tag || t('PICK_FIRST', 'Pick an article first.');
            insertBtn.disabled = !tag;
        };

        const renderResults = (items) => {
            results.replaceChildren();
            if (!items.length) {
                status.textContent = t('NO_RESULTS', 'No articles found.');
                return;
            }
            status.textContent = '';
            items.forEach((a) => {
                const id = nextId('art');
                const input = el('input', { type: 'radio', name: 'cff-article', id, value: String(a.id) });
                if (state.article && state.article.id === a.id) input.checked = true;
                input.addEventListener('change', () => { state.article = a; refresh(); });
                const meta = [a.category, `ID ${a.id}`];
                if (a.state === 0) meta.push(t('UNPUBLISHED', 'unpublished'));
                if (a.state === 2) meta.push(t('ARCHIVED', 'archived'));
                results.append(el('label', { class: 'cff-result', for: id }, input,
                    el('span', { class: 'cff-result__title', text: a.title }),
                    el('span', { class: 'cff-result__meta', text: meta.filter(Boolean).join(' · ') })));
            });
        };

        const runSearch = () => {
            if (aborter) aborter.abort();
            aborter = new AbortController();
            status.textContent = t('LOADING', 'Searching…');
            searchArticles(search.value.trim(), aborter.signal)
                .then(renderResults)
                .catch((err) => {
                    if (err.name === 'AbortError') return;
                    // eslint-disable-next-line no-console
                    console.error('[chaosfilet-insert]', err);
                    results.replaceChildren();
                    status.textContent = t('SEARCH_FAILED', 'Search failed. You can still type a tag by hand.');
                });
        };

        search.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(runSearch, 250);
        });
        search.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });

        [refId, refAlias].forEach((r) => r.addEventListener('change', () => { state.refMode = r.value; refresh(); }));

        dialog.addEventListener('change', (e) => {
            const target = e.target;
            if (target.name === 'cff-field') {
                state.order = state.order.filter((f) => f !== target.value);
                if (target.checked) state.order.push(target.value);
                refresh();
            } else if (target.dataset && target.dataset.cf !== undefined) {
                const name = target.dataset.cf;
                state.order = state.order.filter((f) => f !== `field:${name}` && f !== `fieldraw:${name}`);
                if (target.value) state.order.push(target.value);
                refresh();
            }
        });

        inlineBox.addEventListener('change', () => { state.inline = inlineBox.checked; refresh(); });
        layoutSelect.addEventListener('change', () => { state.layout = layoutSelect.value; refresh(); });

        const finish = (value) => {
            clearTimeout(timer);
            if (aborter) aborter.abort();
            dialog.close();
            dialog.remove();
            resolve(value);
        };

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

async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch (e) {
        return false;
    }
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
        console.error('[chaosfilet-insert] Failed to insert into editor:', err);
        const copied = await copyToClipboard(tag);
        notify(copied
            ? t('COPIED', 'Could not insert automatically; the tag is on your clipboard.')
            : `${t('INSERT_FAILED', 'Could not insert the tag:')} ${tag}`, 'warning');
    }
});
