/*
 * Kanban — the page talks to the server through a few data-* attributes; the server answers
 * with ready-made markup ({html, append, remove}) so there is no templating here.
 *
 *   form[data-ajax]          sent with fetch; inline errors; data-ask = confirm first, data-reset = clear on success;
 *                            a submit button with data-close also closes the window on success
 *   form[data-autosubmit]    sent on every change (its fields may live elsewhere, with form="…")
 *   [data-post="url"]        POST on click; [data-action="url"][data-confirm="text"] asks first
 *   [data-filter="sel"]      search box: hides the elements matching sel whose data-search doesn't contain the text
 *   [data-load="url?q="]     search box: loads {html} into data-load-into (after data-load-delay ms); [data-load-from] loads once when visible
 *   [data-expand]            a button that opens into its search field, focused (on phones the keyboard comes up)
 *   "/" or Cmd/Ctrl+K        opens the global search (#search-modal)
 *   form[data-duration]      calendar event: a new start moves the end to start + that many minutes
 *   input[data-max-bytes]    file too large → inline error (data-too-big), nothing is uploaded
 *   [data-copy="#field"]     copies the value of that field;  input[data-ask-off]: confirm before turning a switch off
 *   [data-preview]           radio: copies its data-preview-style onto the element data-preview points to
 *   [data-open-post="url"]   dropdown toggle: POST once when it opens (e.g. mark notifications as read)
 *   details[data-remember]   keeps open/closed across reloads (this tab; data-remember-in="local": always); opens when the address points to it (#id)
 *   [data-sort="sel"]        menu of [data-sort-by] choices: orders the children of sel by their data-sort-{key} (remembered); "app:patched" after every DOM patch
 *   textarea[data-enter-submit], details.quick-add, [data-close-details], [data-open-details]
 */
(() => {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

    /** POST (FormData or plain object) → parsed JSON ({ok, message, errors, ...}). */
    async function api(url, body = null) {
        if (body && !(body instanceof FormData)) body = toFormData(body);
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrf },
            body,
            credentials: 'same-origin',
        });
        try {
            return await response.json();
        } catch {
            return { ok: false, message: response.status === 413 ? 'Upload too large' : `HTTP ${response.status}` };
        }
    }

    /** GET returning JSON (or text, when asked). */
    async function get(url, as = 'json') {
        const response = await fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return as === 'text' ? response.text() : response.json();
    }

    function toFormData(data) {
        const form = new FormData();
        for (const [key, value] of Object.entries(data)) {
            if (Array.isArray(value)) value.forEach((v) => form.append(`${key}[]`, v));
            else form.append(key, value);
        }
        return form;
    }

    function toast(message, type = 'info') {
        if (!message) return;
        const el = document.createElement('div');
        el.className = `toast align-items-center border-0 ${type === 'error' ? 'text-bg-danger' : 'is-info'}`;
        el.setAttribute('role', 'status');
        el.innerHTML = '<div class="d-flex"><div class="toast-body"></div><button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>';
        el.querySelector('.toast-body').textContent = message;
        document.getElementById('toasts')?.append(el);
        el.addEventListener('hidden.bs.toast', () => el.remove());
        bootstrap.Toast.getOrCreateInstance(el, { delay: 4000 }).show();
    }

    function showErrors(form, errors = {}) {
        const scope = form.id ? [form, ...$$(`[form="${form.id}"]`)] : [form];
        scope.forEach((el) => el.classList?.remove('is-invalid'));
        $$('.is-invalid', form).forEach((el) => el.classList.remove('is-invalid'));
        const root = form.closest('.modal-content, .card-back, .offcanvas, main') ?? document;
        $$('[data-error-for]', root).forEach((el) => { el.textContent = ''; el.style.display = ''; });
        for (const [field, message] of Object.entries(errors)) {
            const input = form.elements.namedItem(field) ?? form.elements.namedItem(`${field}[]`);
            (input instanceof RadioNodeList ? input[0] : input)?.classList.add('is-invalid');
            const feedback = form.querySelector(`[data-error-for="${field}"]`) ?? root.querySelector(`[data-error-for="${field}"]`);
            if (feedback) {
                feedback.textContent = message;
                feedback.style.display = 'block';
            }
        }
    }

    /** Applies a server answer: navigation, DOM patches, then the message. */
    function handle(data, trigger = null) {
        if (data.reload) return window.location.reload();
        if (data.redirect) {
            // the same page with another #anchor would not load again by itself (e.g. back to Settings after saving)
            const target = new URL(data.redirect, location.href);
            window.location.href = target.href;
            if (target.pathname === location.pathname && target.search === location.search) window.location.reload();
            return;
        }

        for (const [selector, html] of Object.entries(data.html ?? {})) {
            $$(selector).forEach((el) => { el.outerHTML = html; });
        }
        for (const [selector, html] of Object.entries(data.append ?? {})) {
            $$(selector).forEach((el) => el.insertAdjacentHTML('beforeend', html));
        }
        (data.remove ?? []).forEach((selector) => $$(selector).forEach((el) => el.remove()));
        if (data.close) $$('.modal.show').forEach((el) => bootstrap.Modal.getInstance(el)?.hide());
        if (data.toggle && trigger?.dataset.post) {
            // every button for the same action (a board can be on the page twice)
            $$(`[data-post="${CSS.escape(trigger.dataset.post)}"]`).forEach((el) => {
                el.classList.toggle(data.toggle.class, data.toggle.on);
                el.setAttribute('aria-pressed', String(data.toggle.on));
            });
        }
        if (data.html || data.append) document.dispatchEvent(new CustomEvent('app:patched'));
        if (data.message) toast(data.message, data.ok ? 'info' : 'error');
    }

    // Shared confirmation modal: confirmDialog(text) resolves true/false.
    const modalEl = document.getElementById('confirm-modal');
    const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    let resolveConfirm = null;

    function confirmDialog(text) {
        if (!modal) return Promise.resolve(window.confirm(text));
        modalEl.querySelector('[data-confirm-text]').textContent = text;
        modal.show();
        return new Promise((resolve) => { resolveConfirm = resolve; });
    }

    if (modalEl) {
        const settle = (value) => { resolveConfirm?.(value); resolveConfirm = null; };
        modalEl.querySelector('[data-confirm-ok]').addEventListener('click', () => { settle(true); modal.hide(); });
        modalEl.addEventListener('hidden.bs.modal', () => settle(false));
    }

    async function busy(element, task) {
        element?.setAttribute('disabled', '');
        try {
            return await task();
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            element?.removeAttribute('disabled');
        }
    }

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!form.matches('form[data-ajax]')) return;
        event.preventDefault();
        if (form.dataset.ask && !(await confirmDialog(form.dataset.ask))) return;

        // a form that is sent on purpose (not saving itself on every change) goes once at a time:
        // a second tap on its buttons, or Enter, while it is on its way is ignored
        const once = !form.hasAttribute('data-autosubmit');
        if (once && form.hasAttribute('data-sending')) return;
        const buttons = [...form.querySelectorAll('[type="submit"]'), ...(form.id ? $$(`[type="submit"][form="${CSS.escape(form.id)}"]`) : [])];
        const button = event.submitter ?? buttons[0];
        if (once) {
            form.setAttribute('data-sending', '');
            buttons.forEach((el) => { el.disabled = true; });
        }
        await busy(button, async () => {
            const data = await api(form.action, new FormData(form));
            showErrors(form, data.errors);
            if (data.ok && form.hasAttribute('data-reset')) form.reset();
            // a field error is enough: no toast on top of it
            if (data.ok || !Object.keys(data.errors ?? {}).length) handle(data, form);
            if (data.ok && button?.hasAttribute('data-close')) $$('.modal.show').forEach((el) => bootstrap.Modal.getInstance(el)?.hide());
            // on document: the form may have just been redrawn away
            document.dispatchEvent(new CustomEvent('app:done', { detail: { data, form } }));
        });
        if (once) {
            form.removeAttribute('data-sending');
            buttons.forEach((el) => { el.disabled = false; });
        }
    });

    // form[data-duration="30"] (calendar event): changing the start sets the end that many minutes later
    function followStart(field) {
        const form = field.form;
        const minutes = Number(form?.dataset.duration);
        if (!minutes || !['start_date', 'start_time'].includes(field.name)) return;
        const [startDate, startTime, endDate, endTime] = ['start_date', 'start_time', 'end_date', 'end_time'].map((n) => form.elements.namedItem(n));
        if (!startDate?.value) return;
        if (form.elements.namedItem('all_day')?.checked) {
            if (endDate.value < startDate.value) endDate.value = startDate.value;
            return;
        }
        const end = new Date(`${startDate.value}T${startTime?.value || '00:00'}`);
        end.setMinutes(end.getMinutes() + minutes);
        const pad = (n) => String(n).padStart(2, '0');
        endDate.value = `${end.getFullYear()}-${pad(end.getMonth() + 1)}-${pad(end.getDate())}`;
        if (endTime) endTime.value = `${pad(end.getHours())}:${pad(end.getMinutes())}`;
    }

    document.addEventListener('change', async (event) => {
        const { target } = event;
        followStart(target);
        if (target.dataset.askOff && !target.checked) {
            if (!(await confirmDialog(target.dataset.askOff))) {
                target.checked = true;
                return;
            }
        }
        // input[type=file][data-max-bytes]: a file over the limit is refused before uploading it
        if (target.dataset.maxBytes && [...target.files].some((f) => f.size > Number(target.dataset.maxBytes))) {
            showErrors(target.form, { [target.name]: target.dataset.tooBig });
            target.value = '';
            return;
        }
        if (target.form?.matches('[data-autosubmit]') && !target.matches('[type="search"]')) target.form.requestSubmit();
        if (target.matches('[data-preview]:checked')) {
            const preview = document.querySelector(target.dataset.preview);
            if (preview) preview.style.cssText = target.dataset.previewStyle;
        }
    });

    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-action], [data-post]');
        if (trigger) {
            if (trigger.dataset.confirm && !(await confirmDialog(trigger.dataset.confirm))) return;
            await busy(trigger, async () => handle(await api(trigger.dataset.action ?? trigger.dataset.post), trigger));
            return;
        }
        const copy = event.target.closest('[data-copy]');
        if (copy) {
            const field = document.querySelector(copy.dataset.copy);
            field?.select();
            navigator.clipboard?.writeText(field?.value ?? '').then(() => toast(copy.dataset.copied ?? '✓'), () => document.execCommand('copy'));
        }
        const expand = event.target.closest('[data-expand] > button');
        if (expand) {
            const box = expand.parentElement;
            box.classList.add('is-open');
            box.querySelector('input').focus(); // in the same tap: phones open the keyboard
        }
        // a note in a log: "Edit" swaps its text for a field, "Cancel" back
        const noteEdit = event.target.closest('[data-note-edit], [data-note-cancel]');
        if (noteEdit) {
            const note = noteEdit.closest('.is-note');
            const editing = noteEdit.matches('[data-note-edit]');
            note.querySelector('.note-edit').hidden = !editing;
            note.querySelector('.note-text').hidden = editing;
            if (editing) {
                const field = note.querySelector('.note-edit textarea');
                field.focus();
                field.setSelectionRange(field.value.length, field.value.length);
            }
        }
        const close = event.target.closest('[data-close-details]');
        if (close) close.closest('details').open = false;
        const open = event.target.closest('[data-open-details]');
        if (open) {
            const details = document.querySelector(open.dataset.openDetails);
            if (details) details.open = true;
        }
    });

    // Quick add (details.quick-add): focus the field when it opens; Enter submits, Esc closes.
    document.addEventListener('toggle', (event) => {
        const details = event.target;
        if (details.matches?.('details.quick-add') && details.open) details.querySelector('textarea, input:not([type="hidden"])')?.focus();
    }, true);

    // [data-expand] closes again when its field is left empty
    document.addEventListener('focusout', (event) => {
        const box = event.target.closest?.('[data-expand]');
        if (box && event.target.matches('input') && event.target.value === '') box.classList.remove('is-open');
    });

    document.addEventListener('keydown', (event) => {
        const field = event.target;
        const typing = field.matches?.('input, textarea, select, [contenteditable]');
        const search = document.getElementById('search-modal');
        if (search && ((event.key === '/' && !typing) || (event.key.toLowerCase() === 'k' && (event.metaKey || event.ctrlKey)))) {
            event.preventDefault();
            bootstrap.Modal.getOrCreateInstance(search).show();
            return;
        }
        if (event.key === 'Escape' && field.closest?.('[data-expand]')) {
            field.value = '';
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.blur();
        }
        if (event.key === 'Enter' && !event.shiftKey && field.matches('textarea[data-enter-submit]')) {
            event.preventDefault();
            field.form?.requestSubmit();
        }
        if (event.key === 'Escape' && field.closest('details.quick-add')) field.closest('details').open = false;
        if (event.key === 'Escape' && field.closest('.note-edit')) field.closest('.is-note').querySelector('[data-note-cancel]').click();
    });

    // Search boxes that filter what is already on the page.
    document.addEventListener('input', (event) => {
        const box = event.target;
        if (!box.matches('[data-filter]')) return;
        const text = box.value.trim().toLowerCase();
        $$(box.dataset.filter).forEach((el) => { el.hidden = text !== '' && !el.dataset.search.includes(text); });
        $$('[data-filter-group]').forEach((group) => {
            group.hidden = text !== '' && !$$('[data-search]', group).some((el) => !el.hidden);
        });
    });

    // after a DOM patch the new elements are filtered like the others
    document.addEventListener('app:patched', () => {
        $$('[data-filter]').forEach((box) => { if (box.value) box.dispatchEvent(new Event('input', { bubbles: true })); });
    });

    // Order: [data-sort="sel"] holds [data-sort-by="key"] choices (data-desc: newest first) and
    // orders the children of sel by their data-sort-{key}; the others (a "new" tile) come first.
    const sortStore = (menu) => `sort:${menu.dataset.sortKey ?? location.pathname}`;
    function sort(menu) {
        const choice = menu.querySelector('[data-sort-by].is-on') ?? menu.querySelector('[data-sort-by]');
        if (!choice) return;
        const key = choice.dataset.sortBy;
        const by = `sort${key.charAt(0).toUpperCase()}${key.slice(1)}`;
        const desc = 'desc' in choice.dataset;
        $$(menu.dataset.sort).forEach((parent) => {
            const items = [...parent.children];
            const sorted = items.filter((el) => by in el.dataset).sort((a, b) => {
                const order = a.dataset[by].localeCompare(b.dataset[by], undefined, { sensitivity: 'base', numeric: true });
                return desc ? -order : order;
            });
            [...items.filter((el) => !(by in el.dataset)), ...sorted].forEach((el) => parent.append(el));
        });
    }
    function choose(menu, choice) {
        $$('[data-sort-by]', menu).forEach((el) => {
            el.classList.toggle('is-on', el === choice);
            el.setAttribute('aria-checked', String(el === choice));
        });
        const label = menu.querySelector('[data-sort-label]');
        if (label) label.textContent = choice.textContent.trim();
        sort(menu);
    }
    $$('[data-sort]').forEach((menu) => {
        let saved = null;
        try { saved = localStorage.getItem(sortStore(menu)); } catch { /* storage disabled */ }
        const choice = saved && menu.querySelector(`[data-sort-by="${CSS.escape(saved)}"]`);
        if (choice) choose(menu, choice); else sort(menu); // the calendars come by name from the server
        menu.addEventListener('click', (event) => {
            const picked = event.target.closest('[data-sort-by]');
            if (!picked) return;
            choose(menu, picked);
            try { localStorage.setItem(sortStore(menu), picked.dataset.sortBy); } catch { /* storage disabled */ }
        });
    });
    document.addEventListener('app:patched', () => $$('[data-sort]').forEach(sort));

    // Search boxes that ask the server (debounced) and remote content loaded when it shows up.
    async function load(url, target) {
        try {
            const data = await get(url);
            if (data.message) toast(data.message, 'error');
            if (data.html !== undefined) target.innerHTML = data.html;
        } catch (error) {
            toast(error.message, 'error');
        }
    }

    let timer = null;
    document.addEventListener('input', (event) => {
        const box = event.target;
        if (!box.matches('[data-load]')) return;
        clearTimeout(timer);
        timer = setTimeout(() => load(box.dataset.load + encodeURIComponent(box.value.trim()), document.querySelector(box.dataset.loadInto)), Number(box.dataset.loadDelay ?? 400));
    });

    const lazy = new IntersectionObserver((entries) => entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        lazy.unobserve(entry.target);
        load(entry.target.dataset.loadFrom, entry.target);
    }));
    $$('[data-load-from]').forEach((el) => lazy.observe(el));

    document.addEventListener('show.bs.dropdown', (event) => {
        const toggle = event.target;
        if (!toggle.dataset.openPost) return;
        api(toggle.dataset.openPost);
        delete toggle.dataset.openPost;
        toggle.querySelector('[data-unread]')?.remove();
    });

    // A modal form starts clean every time it opens.
    document.addEventListener('show.bs.modal', (event) => {
        const form = event.target.querySelector('form[data-ajax]');
        if (!form) return;
        showErrors(form);
        if (form.hasAttribute('data-reset')) form.reset();
    });
    document.addEventListener('shown.bs.modal', (event) => {
        const field = event.target.querySelector('[autofocus]') ?? event.target.querySelector('input[required]');
        field?.focus();
        field?.select?.();
    });

    // details[data-remember]: closed or opened by hand stays so (data-remember-in="local": across visits too)
    const remembered = (el) => {
        try { return el.dataset.rememberIn === 'local' ? localStorage : sessionStorage; } catch { return null; }
    };
    const remember = (root = document) => $$('details[data-remember]:not([data-remembered])', root).forEach((el) => {
        el.dataset.remembered = '';
        const key = `open:${el.dataset.remember}`;
        try {
            const state = remembered(el)?.getItem(key);
            if (state) el.open = state === '1';
        } catch { /* storage disabled */ }
        el.addEventListener('toggle', () => {
            try { remembered(el)?.setItem(key, el.open ? '1' : '0'); } catch { /* storage disabled */ }
        });
    });
    remember();
    document.addEventListener('app:patched', () => remember());
    const openTarget = () => {
        const target = location.hash.length > 1 && document.getElementById(decodeURIComponent(location.hash.slice(1)));
        for (let el = target || null; el; el = el.parentElement?.closest('details')) {
            if (el instanceof HTMLDetailsElement) el.open = true;
        }
    };
    openTarget();
    addEventListener('hashchange', openTarget);

    window.App = { api, get, handle, toast, confirm: confirmDialog };
})();
