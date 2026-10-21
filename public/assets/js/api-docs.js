/* API docs: "Try it" sends the request with the token typed at the top of the page (never stored). */
(() => {
    'use strict';

    const tokenField = document.getElementById('docs-token');

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!form.matches('form[data-try]')) return;
        event.preventDefault();
        const out = form.querySelector('.docs-result');
        const fields = new FormData(form);
        const query = new URLSearchParams();
        let url = form.dataset.path;
        for (const [name, value] of fields) {
            const [where, key] = name.split(':');
            if (where === 'path') url = url.replace(`{${key}}`, encodeURIComponent(value));
            if (where === 'query' && value !== '') query.set(key, value);
        }
        if (query.size) url += `?${query}`;

        const init = { method: form.dataset.method, headers: { Authorization: `Bearer ${tokenField.value.trim()}`, Accept: 'application/json' } };
        if (form.dataset.body === 'multipart/form-data') {
            init.body = new FormData();
            init.body.append('file', fields.get('file'));
        } else if (form.dataset.body) {
            init.headers['Content-Type'] = 'application/json';
            init.body = fields.get('body');
        }

        out.hidden = false;
        out.textContent = '…';
        try {
            const response = await fetch(url, init);
            const text = await response.text();
            let body = text;
            try { body = JSON.stringify(JSON.parse(text), null, 2); } catch { /* not JSON (a file) */ }
            out.textContent = `${response.status} ${response.statusText}\n\n${body || '(empty)'}`;
        } catch (error) {
            out.textContent = error.message;
        }
    });
})();
