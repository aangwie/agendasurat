/**
 * Datatable server-side generik (pencarian, slice data, pagination via AJAX).
 *
 * Dipakai oleh tabel yang memakai atribut `data-datatable="server"`.
 * Server mengembalikan partial tabel sehingga markup tidak terduplikasi.
 */
export function initServerDatatable() {
    const root = document.querySelector('[data-datatable="server"]');

    if (!root) {
        return;
    }

    const results = root.querySelector('[data-table-results]');
    const form = root.querySelector('[data-table-form]');
    const search = root.querySelector('[data-table-search]');
    const perPage = root.querySelector('[data-table-per-page]');
    const spinner = root.querySelector('[data-table-spinner]');

    if (!results || !form) {
        return;
    }

    let controller = null;
    let debounce = null;

    const buildUrl = (page = 1) => {
        const url = new URL(form.getAttribute('action') || window.location.href, window.location.origin);
        const params = url.searchParams;
        const query = (search?.value ?? '').trim();

        if (query !== '') {
            params.set('q', query);
        } else {
            params.delete('q');
        }

        if (perPage?.value) {
            params.set('per_page', perPage.value);
        }

        params.set('page', String(page));

        return url;
    };

    const setLoading = (loading) => {
        results.classList.toggle('opacity-60', loading);
        results.classList.toggle('pointer-events-none', loading);
        results.setAttribute('aria-busy', String(loading));
        spinner?.classList.toggle('hidden', !loading);
    };

    const load = async (url) => {
        controller?.abort();
        controller = new AbortController();
        setLoading(true);

        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            results.innerHTML = await response.text();
            window.history.replaceState({}, '', url.toString());
        } catch (error) {
            if (error.name !== 'AbortError') {
                // Fallback ke navigasi biasa bila permintaan AJAX gagal.
                window.location.assign(url.toString());
            }
        } finally {
            setLoading(false);
        }
    };

    const reload = () => {
        window.clearTimeout(debounce);
        load(buildUrl());
    };

    search?.addEventListener('input', () => {
        window.clearTimeout(debounce);
        debounce = window.setTimeout(reload, 350);
    });

    search?.addEventListener('search', reload);

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        reload();
    });

    perPage?.addEventListener('change', reload);

    results.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-table-page], a[data-table-reset]');

        if (!link) {
            return;
        }

        event.preventDefault();

        if (link.hasAttribute('data-table-reset') && search) {
            search.value = '';
        }

        const href = link.getAttribute('href');

        if (href) {
            load(new URL(href, window.location.origin));
        }
    });
}
