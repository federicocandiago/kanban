/*
 * Board-like pages (boards and the calendar month): drag & drop, the card window, quick add.
 * Needs app.js and dnd.js. What differs is read from .board-canvas:
 *   data-cards-url    base URL of the items (/cards/ or /events/): opened at {url}{id}, moved at {url}{id}/move
 *   data-lists-order  lists can be reordered (boards only)
 *   data-readonly     a public board open to read: no drag & drop
 *   data-sync         calendar: asked on load and every few minutes, redraws what changed
 *   data-view         calendar: the month on screen, sent along so the server redraws that one
 *   data-start-offset calendar: days from today to start the month at (wider screens only)
 * Lists with data-date are days: a card dropped there moves to that date.
 */
(() => {
    'use strict';

    const canvas = document.querySelector('.board-canvas');
    if (!canvas) return;
    const { cardsUrl, listsOrder, sync, view } = canvas.dataset;
    const ids = (container, selector) => [...container.querySelectorAll(selector)].map((el) => el.dataset.cardId ?? el.dataset.listId);
    const send = async (url, body, redraw = false) => {
        const data = await App.api(url, body);
        if (!data.ok || redraw) App.handle(data);
    };

    const readonly = 'readonly' in canvas.dataset; // a public board to read: nothing moves
    if (!readonly) Dnd.sortable({
        items: '[data-cards] > .card-tile:not(.is-dimmed)',
        zone: (el) => el.closest('.list')?.querySelector('[data-cards]'),
        scroller: canvas,
        onDrop: (card, cards) => {
            const list = cards.closest('.list');
            if (list.dataset.date) {
                send(`${cardsUrl}${card.dataset.cardId}/move`, { date: list.dataset.date, view: view ?? '' }, true);
            } else {
                send(`${cardsUrl}${card.dataset.cardId}/move`, { list_id: list.dataset.listId, ids: ids(cards, '.card-tile') });
            }
        },
    });

    // a card's checklist, by its grip
    if (!readonly) Dnd.sortable({
        items: '[data-checks] > .check-item',
        handle: '.check-grip',
        zone: (el) => el.closest('[data-checks]'),
        onDrop: (item, list) => send(list.closest('#card-checklist').dataset.order, { ids: [...list.children].map((li) => li.dataset.checkId) }),
    });

    if (listsOrder) {
        Dnd.sortable({
            items: '[data-lists] > .list',
            handle: '.list-head',
            zone: (el) => el.closest('[data-lists]'),
            axis: 'x',
            scroller: canvas,
            onDrop: (list, lists) => send(listsOrder, { ids: ids(lists, ':scope > .list') }),
        });
    }

    // The card window: loaded from the server, address kept in ?card= so it can be linked and reloaded.
    const modalEl = document.getElementById('card-modal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const content = modalEl.querySelector('[data-card-content]');

    async function load(url) {
        bootstrap.Offcanvas.getInstance('#board-menu')?.hide();
        content.innerHTML = '<div class="card-back-loading"></div>';
        modal.show();
        try {
            content.innerHTML = await App.get(url, 'text');
            document.dispatchEvent(new CustomEvent('app:patched'));
            return true;
        } catch (error) {
            modal.hide();
            App.toast(error.message, 'error');
            return false;
        }
    }

    async function open(id) {
        if (!(await load(`${cardsUrl}${id}${view ? `?view=${view}` : ''}`))) return;
        const params = new URLSearchParams(location.search);
        params.set('card', id);
        history.replaceState(null, '', `?${params}`);
    }

    // [data-new-card="url"] (the + in the top bar): the "New card" window, its name field ready
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-new-card]');
        if (!button) return;
        if (await load(button.dataset.newCard)) content.querySelector('[autofocus]')?.focus();
    });

    document.addEventListener('click', (event) => {
        const link = event.target.closest('[data-cards] > .card-tile, [data-card-open]');
        if (!link || event.metaKey || event.ctrlKey) return;
        event.preventDefault();
        open(link.dataset.cardId ?? link.dataset.cardOpen);
    });

    // An answer with {open: html} shows it in the window (a new timed event, to check before saving).
    document.addEventListener('app:done', (event) => {
        const { data, form } = event.detail;
        if (!data.ok || !data.open) return;
        form.closest('details')?.removeAttribute('open');
        content.innerHTML = data.open;
        document.dispatchEvent(new CustomEvent('app:patched'));
        modal.show();
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        content.innerHTML = '';
        const params = new URLSearchParams(location.search);
        params.delete('card');
        history.replaceState(null, '', params.size ? `?${params}` : location.pathname);
    });

    const linked = new URLSearchParams(location.search).get('card');
    if (linked) open(linked);

    // After a quick add the new card is in view, and the form stays open for the next one.
    document.addEventListener('app:done', (event) => {
        const { data, form } = event.detail;
        if (!data.ok) return;
        const day = form.closest('[data-date]')?.dataset.date;
        if (day && !form.isConnected) {                  // the days were redrawn: open the same form again
            const details = document.querySelector(`#day-${day} .quick-add`);
            if (details) details.open = true;
        }
        const list = (day ? document.getElementById(`day-${day}`) : form.closest('.list'))?.querySelector('[data-cards]');
        if (list && form.closest('.quick-add')) list.scrollTop = list.scrollHeight;
        if (form.closest('.add-list')) canvas.scrollLeft = canvas.scrollWidth;
    });

    // Calendar: start from today — on wider screens data-start-offset days away (-1: yesterday
    // shows too, within the month) — then keep in step with the server.
    const toToday = (behavior = 'auto') => {
        const today = canvas.querySelector('.is-today');
        if (!today) return false;
        const days = [...today.parentElement.children];
        const offset = matchMedia('(min-width: 576px)').matches ? Number(canvas.dataset.startOffset ?? 0) || 0 : 0;
        const start = days[Math.min(days.length - 1, Math.max(0, days.indexOf(today) + offset))];
        canvas.scrollTo({ left: start.offsetLeft - 8, behavior });
        return true;
    };
    toToday();
    // [data-today]: back to today; on this month's page just scroll there, otherwise the link opens it
    document.querySelector('[data-today]')?.addEventListener('click', (event) => {
        if (toToday('smooth')) event.preventDefault();
    });

    if (sync) {
        const state = document.querySelector('[data-sync-state]');
        const busy = () => document.body.classList.contains('is-dragging') || canvas.querySelector('details[open], :focus');
        const refresh = async () => {
            if (document.hidden || busy()) return;
            state?.classList.add('is-busy');
            const data = await App.api(sync).catch(() => ({ ok: false }));
            state?.classList.remove('is-busy');
            if (!busy()) App.handle(data);
        };
        refresh();
        setInterval(refresh, 120_000);
    }
})();
