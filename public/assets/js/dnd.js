/*
 * Dnd.sortable() — drag & drop to reorder, with Pointer Events (mouse, pen and touch).
 * The dragged element itself becomes the placeholder and moves through the DOM while a tilted
 * copy follows the pointer, so when it is dropped the page is already in its final order.
 * Mouse: drag after a few pixels. Touch: press and hold, so that swiping still scrolls.
 *
 *   Dnd.sortable({
 *     items: '.card-tile',                 // what can be dragged (event delegation: new items work too)
 *     handle: '.list-head',                // optional: where the drag can start
 *     zone: (el) => el.closest(...),       // the container under the pointer, or null
 *     axis: 'y',                           // direction of the items inside a zone
 *     scroller: element,                   // optional: scrolled sideways near its edges
 *     onDrop: (item, zone) => {},          // called only when the position changed
 *   });
 */
window.Dnd = (() => {
    'use strict';

    const HOLD_MS = 300;    // touch: press and hold before the drag starts
    const SLOP_PX = 6;      // mouse: distance before the drag starts / touch: movement that cancels the hold
    const EDGE_PX = 60;     // auto-scroll zone near the edges
    const SKIP = 'button, select, summary, input:focus, textarea, [data-no-drag], .dropdown-menu';

    let dragging = null;    // the current drag, shared by every sortable
    let quietUntil = 0;     // the click that follows a drop must not open the item

    // Once a touch drag has started the page must not scroll under the finger, nor open a context menu.
    document.addEventListener('touchmove', (event) => { if (dragging) event.preventDefault(); }, { passive: false });
    document.addEventListener('contextmenu', (event) => { if (dragging) event.preventDefault(); });
    document.addEventListener('click', (event) => {
        if (Date.now() < quietUntil) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);

    function sortable(options) {
        const { items, handle = null, zone, axis = 'y', scroller = null, onDrop } = options;

        document.addEventListener('pointerdown', (event) => {
            if (dragging || event.button !== 0) return;
            const item = event.target.closest(items);
            if (!item || event.target.closest(SKIP) || (handle && !event.target.closest(handle))) return;

            const start = { x: event.clientX, y: event.clientY };
            const touch = event.pointerType === 'touch';
            let timer = touch ? setTimeout(() => begin(item, start), HOLD_MS) : null;

            const move = (e) => {
                const far = Math.hypot(e.clientX - start.x, e.clientY - start.y) > SLOP_PX;
                if (dragging?.item === item) return follow(e);
                if (touch && far) return stop();                 // a swipe: let the page scroll
                if (!touch && far) begin(item, start, e);
            };
            const stop = (e) => {
                clearTimeout(timer);
                timer = null;
                removeEventListener('pointermove', move);
                removeEventListener('pointerup', stop);
                removeEventListener('pointercancel', stop);
                if (dragging?.item === item) end(e?.type === 'pointerup');
            };
            addEventListener('pointermove', move);
            addEventListener('pointerup', stop);
            addEventListener('pointercancel', stop);
        });

        function begin(item, start, event = null) {
            const rect = item.getBoundingClientRect();
            const ghost = item.cloneNode(true);
            ghost.removeAttribute('id');
            ghost.classList.add('dnd-ghost');
            Object.assign(ghost.style, { width: `${rect.width}px`, height: `${rect.height}px`, left: `${rect.left}px`, top: `${rect.top}px` });
            document.body.append(ghost);

            document.activeElement?.blur();
            getSelection()?.removeAllRanges();
            item.classList.add('dnd-placeholder');
            document.body.classList.add('is-dragging');
            navigator.vibrate?.(15);

            dragging = {
                item, ghost, offset: { x: start.x - rect.left, y: start.y - rect.top },
                from: { parent: item.parentNode, next: item.nextSibling },
                pointer: { ...start }, frame: requestAnimationFrame(autoScroll),
            };
            if (event) follow(event);
        }

        function follow(event) {
            const { ghost, offset, item, pointer } = dragging;
            pointer.x = event.clientX;
            pointer.y = event.clientY;
            ghost.style.transform = `translate(${pointer.x - offset.x - parseFloat(ghost.style.left)}px, ${pointer.y - offset.y - parseFloat(ghost.style.top)}px) rotate(4deg)`;

            const target = zone(document.elementFromPoint(pointer.x, pointer.y) ?? document.body);
            if (!target) return;
            const before = [...target.children].find((el) => {
                if (el === item || !el.matches(items)) return false;
                const r = el.getBoundingClientRect();
                return axis === 'y' ? pointer.y < r.top + r.height / 2 : pointer.x < r.left + r.width / 2;
            });
            if (before) {
                if (item.nextElementSibling !== before) target.insertBefore(item, before);
            } else if (target.lastElementChild !== item) {
                target.append(item);
            }
        }

        /** Near the edges, scroll the board sideways and the list under the pointer up or down. */
        function autoScroll() {
            if (!dragging) return;
            const { x, y } = dragging.pointer;
            const nudge = (el, horizontal) => {
                const r = el.getBoundingClientRect();
                const [pos, min, max] = horizontal ? [x, r.left, r.right] : [y, r.top, r.bottom];
                const step = pos < min + EDGE_PX ? -12 : pos > max - EDGE_PX ? 12 : 0;
                if (step) el[horizontal ? 'scrollLeft' : 'scrollTop'] += step;
            };
            if (scroller) nudge(scroller, true);
            const list = dragging.item.parentElement;
            if (axis === 'y' && list.scrollHeight > list.clientHeight) nudge(list, false);
            dragging.frame = requestAnimationFrame(autoScroll);
        }

        function end(dropped) {
            const { item, ghost, from, frame } = dragging;
            cancelAnimationFrame(frame);
            ghost.remove();
            item.classList.remove('dnd-placeholder');
            document.body.classList.remove('is-dragging');
            dragging = null;
            quietUntil = Date.now() + 250;

            if (!dropped) {
                from.parent.insertBefore(item, from.next);
            } else if (item.parentNode !== from.parent || item.nextSibling !== from.next) {
                onDrop(item, item.parentNode);
            }
        }
    }

    return { sortable };
})();
