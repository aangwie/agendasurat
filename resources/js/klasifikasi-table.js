/**
 * Server-side datatable untuk halaman Klasifikasi.
 *
 * Pencarian, slice data (jumlah baris per halaman) dan pagination dikirim ke
 * server; server mengembalikan partial tabel sehingga tidak ada duplikasi markup.
 */
export function initClassificationTable() {
    const root = document.querySelector('[data-datatable]');

    if (!root) {
        return;
    }

    const results = root.querySelector('[data-table-results]');
    const form = root.querySelector('[data-table-form]');
    const search = root.querySelector('[data-table-search]');
    const perPage = root.querySelector('[data-table-per-page]');
    const scopeInput = root.querySelector('[data-table-scope-input]');
    const spinner = root.querySelector('[data-table-spinner]');
    const tabs = Array.from(root.querySelectorAll('[data-table-scope]'));

    if (!results || !form) {
        return;
    }

    let controller = null;
    let debounce = null;

    const buildUrl = (page = 1) => {
        const url = new URL(form.getAttribute('action') || window.location.href, window.location.origin);
        const params = url.searchParams;
        const query = (search?.value ?? '').trim();

        params.set('scope', scopeInput?.value || 'pemda');

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

    const syncTabs = () => {
        tabs.forEach((tab) => {
            const active = tab.dataset.tableScope === scopeInput?.value;
            const badge = tab.querySelector('span');

            tab.setAttribute('aria-selected', String(active));
            tab.classList.toggle('bg-white', active);
            tab.classList.toggle('text-zinc-900', active);
            tab.classList.toggle('shadow-sm', active);
            tab.classList.toggle('text-zinc-500', !active);

            if (badge) {
                badge.classList.toggle('bg-rose-50', active);
                badge.classList.toggle('text-rose-600', active);
                badge.classList.toggle('bg-zinc-200/70', !active);
                badge.classList.toggle('text-zinc-500', !active);
            }
        });
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
            syncTabs();
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

    // Tombol "x" bawaan input type="search".
    search?.addEventListener('search', reload);

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        reload();
    });

    perPage?.addEventListener('change', reload);

    tabs.forEach((tab) => {
        tab.addEventListener('click', (event) => {
            if (!scopeInput) {
                return;
            }

            event.preventDefault();
            scopeInput.value = tab.dataset.tableScope;
            reload();
        });
    });

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
