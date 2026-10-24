/*
 * View transitions between pages (styles at the end of app.css). Loaded in <head>: "pagereveal"
 * fires before the first paint. The dashboard tile of the board being opened, or just left,
 * takes the names the board page has, so the tile grows into the board and back.
 * The dashboard holds its first paint until the tiles are there (link rel="expect").
 */
(() => {
    'use strict';

    const tileFor = (url) => {
        const path = url && new URL(url).pathname;
        return path ? [...document.querySelectorAll('.board-tile-link')].find((a) => new URL(a.href).pathname === path) : null;
    };
    const name = (tile, transition) => {
        if (!tile || !transition) return;
        const title = tile.querySelector('.board-tile-name');
        tile.style.viewTransitionName = 'board';
        title?.style.setProperty('view-transition-name', 'board-title');
        transition.finished.finally(() => {
            tile.style.viewTransitionName = '';
            title?.style.removeProperty('view-transition-name');
        });
    };

    // On board pages, header, lists and title are named only for the transition: a named element
    // bounds backdrop-filter, and the frost of the lists has to see the photo behind them.
    const parts = [['.board-page .board-header', 'board-head'], ['.board-page .board-canvas', 'board-lists'], ['.topbar-title span', 'board-title']];
    const nameParts = (on) => parts.forEach(([selector, value]) => {
        document.querySelectorAll(selector).forEach((el) => { el.style.viewTransitionName = on ? value : ''; });
    });

    addEventListener('pageswap', (event) => {
        if (!event.viewTransition) return;
        nameParts(true); // this page is left: it is captured now (back from the cache it is cleared on pagereveal)
        name(tileFor(event.activation?.entry?.url), event.viewTransition);
    });
    addEventListener('pagereveal', (event) => {
        nameParts(false);
        if (!event.viewTransition) return;
        nameParts(true);
        event.viewTransition.finished.finally(() => nameParts(false));
        name(tileFor(window.navigation?.activation?.from?.url), event.viewTransition);
    });
})();
