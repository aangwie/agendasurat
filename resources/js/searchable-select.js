/**
 * Dropdown dengan pencarian untuk elemen <select>.
 *
 * Tambahkan atribut `data-searchable` pada <select>. Komponen akan menyembunyikan
 * select asli (nilainya tetap ikut terkirim) dan menampilkan tombol + panel
 * pencarian yang bisa dipakai dengan mouse maupun keyboard.
 *
 * Opsi yang `hidden` atau `disabled` (mis. kode yang sudah dipakai) tetap
 * diperbarui secara otomatis lewat MutationObserver dan event
 * `searchable:refresh`.
 */
const registry = new WeakMap();
const instances = new Set();
let openInstance = null;
let counter = 0;

const closeOpenInstance = (except = null) => {
    if (openInstance && openInstance !== except) {
        openInstance.close();
    }
};

const chevronIcon = () => {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');

    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.8');
    svg.setAttribute('class', 'size-4 shrink-0 text-zinc-400 transition duration-150');
    svg.setAttribute('aria-hidden', 'true');

    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    path.setAttribute('d', 'M19.5 8.25l-7.5 7.5-7.5-7.5');
    svg.append(path);

    return svg;
};

const enhance = (select) => {
    if (registry.has(select)) {
        return;
    }

    counter += 1;

    const wrapper = document.createElement('div');
    wrapper.className = 'relative';

    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'form-input flex w-full items-center justify-between gap-2 py-2.5 text-left';
    button.setAttribute('aria-haspopup', 'listbox');
    button.setAttribute('aria-expanded', 'false');

    if (select.id) {
        button.id = `${select.id}-trigger`;

        const label = document.querySelector(`label[for="${CSS.escape(select.id)}"]`);

        if (label) {
            label.htmlFor = button.id;
        }
    }

    if (select.classList.contains('form-input-error')) {
        button.classList.add('form-input-error');
    }

    if (select.getAttribute('aria-invalid') === 'true') {
        button.setAttribute('aria-invalid', 'true');

        if (select.getAttribute('aria-describedby')) {
            button.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
        }
    }

    if (select.required) {
        button.setAttribute('aria-required', 'true');
    }

    const labelText = document.createElement('span');
    labelText.className = 'truncate';

    button.append(labelText, chevronIcon());

    const panel = document.createElement('div');
    panel.className =
        'absolute z-50 mt-1 hidden w-full overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xl shadow-zinc-900/10';
    panel.id = `searchable-panel-${counter}`;

    button.setAttribute('aria-controls', panel.id);

    const searchWrapper = document.createElement('div');
    searchWrapper.className = 'border-b border-zinc-100 p-2';

    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'form-input py-2 text-sm';
    search.placeholder = select.dataset.searchPlaceholder ?? 'Cari...';
    search.setAttribute('aria-label', select.dataset.searchPlaceholder ?? 'Cari pilihan');
    search.setAttribute('autocomplete', 'off');
    searchWrapper.appendChild(search);

    const list = document.createElement('ul');
    list.setAttribute('role', 'listbox');
    list.setAttribute('aria-label', select.dataset.searchPlaceholder ?? 'Pilihan');
    list.className = 'max-h-56 overflow-y-auto p-1';

    const empty = document.createElement('p');
    empty.className = 'hidden px-3 py-4 text-center text-xs text-zinc-400';
    empty.textContent = 'Tidak ada pilihan yang cocok.';

    panel.append(searchWrapper, list, empty);

    select.classList.add('hidden');
    select.required = false;

    wrapper.append(button, panel);

    const instance = {
        isOpen: () => !panel.classList.contains('hidden'),
        open: () => {},
        close: () => {},
    };

    const optionElements = () => Array.from(select.options).filter(
        (option) => !option.hidden && option.dataset.placeholder === undefined && option.value !== '',
    );

    const syncLabel = () => {
        const selected = select.selectedOptions[0];
        const hasValue = select.value !== '' && selected !== undefined;

        labelText.textContent = hasValue
            ? selected.textContent.trim()
            : (select.dataset.placeholder ?? 'Pilih...');

        labelText.classList.toggle('text-zinc-400', !hasValue);
        button.disabled = select.disabled;
        button.classList.toggle('disabled:opacity-60', select.disabled);
        button.setAttribute('aria-disabled', String(select.disabled));
    };

    const visibleItems = () => Array.from(list.querySelectorAll('button[role="option"]')).filter(
        (item) => !item.hidden,
    );

    const enabledItems = () => visibleItems().filter((item) => !item.disabled);

    const filter = (term) => {
        const needle = term.trim().toLowerCase();
        let matches = 0;

        Array.from(list.querySelectorAll('button[role="option"]')).forEach((item) => {
            const isMatch = needle === '' || (item.dataset.search ?? '').includes(needle);

            item.hidden = !isMatch;

            if (isMatch) {
                matches += 1;
            }
        });

        empty.classList.toggle('hidden', matches > 0);
    };

    const rebuild = () => {
        list.replaceChildren();

        optionElements().forEach((option) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.setAttribute('role', 'option');
            item.dataset.value = option.value;
            item.dataset.search = option.textContent.trim().toLowerCase();
            item.disabled = option.disabled;
            item.setAttribute('aria-selected', String(select.value === option.value));
            item.className = option.disabled
                ? 'flex w-full cursor-not-allowed items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm text-zinc-300'
                : 'flex w-full items-center justify-between gap-2 rounded-lg px-3 py-2 text-left text-sm text-zinc-700 transition hover:bg-rose-50 hover:text-rose-700 focus-visible:bg-rose-50 focus-visible:text-rose-700 focus-visible:outline-none';

            const optionLabel = document.createElement('span');
            optionLabel.className = 'truncate';
            optionLabel.textContent = option.textContent.trim();

            item.appendChild(optionLabel);

            if (option.disabled) {
                const used = document.createElement('span');
                used.className = 'shrink-0 text-[10px] font-semibold tracking-wide text-zinc-300 uppercase';
                used.textContent = 'Dipakai';
                item.appendChild(used);
            }

            item.addEventListener('click', () => {
                if (item.disabled) {
                    return;
                }

                select.value = option.value;
                select.dispatchEvent(new Event('input', { bubbles: true }));
                select.dispatchEvent(new Event('change', { bubbles: true }));

                syncLabel();
                instance.close();
                button.focus({ preventScroll: true });
            });

            list.appendChild(item);
        });

        filter(search.value);
    };

    const focusFirstItem = () => {
        enabledItems()[0]?.focus({ preventScroll: true });
    };

    const moveFocus = (direction) => {
        const items = enabledItems();

        if (items.length === 0) {
            return;
        }

        const index = items.indexOf(document.activeElement);
        let nextIndex = index + direction;

        if (index === -1) {
            nextIndex = direction > 0 ? 0 : items.length - 1;
        }

        if (nextIndex < 0) {
            nextIndex = items.length - 1;
        } else if (nextIndex >= items.length) {
            nextIndex = 0;
        }

        items[nextIndex].focus({ preventScroll: true });
    };

    instance.open = () => {
        if (select.disabled) {
            return;
        }

        closeOpenInstance(instance);
        openInstance = instance;

        search.value = '';
        rebuild();
        panel.classList.remove('hidden');
        button.setAttribute('aria-expanded', 'true');

        const selected = list.querySelector('button[role="option"][aria-selected="true"]');
        selected?.scrollIntoView({ block: 'nearest' });

        window.requestAnimationFrame(() => search.focus({ preventScroll: true }));
    };

    instance.close = () => {
        panel.classList.add('hidden');
        button.setAttribute('aria-expanded', 'false');

        if (openInstance === instance) {
            openInstance = null;
        }
    };

    button.addEventListener('click', () => {
        if (instance.isOpen()) {
            instance.close();
        } else {
            instance.open();
        }
    });

    button.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            instance.open();
        }
    });

    search.addEventListener('input', () => filter(search.value));

    search.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            instance.close();
            button.focus({ preventScroll: true });
        } else if (event.key === 'Enter') {
            event.preventDefault();
            event.stopPropagation();
            enabledItems()[0]?.click();
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            event.stopPropagation();
            focusFirstItem();
        }
    });

    panel.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            moveFocus(1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            moveFocus(-1);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            instance.close();
            button.focus({ preventScroll: true });
        } else if (event.key === 'Tab') {
            instance.close();
        }
    });

    document.addEventListener('click', (event) => {
        if (instance.isOpen() && !wrapper.contains(event.target)) {
            instance.close();
        }
    });

    select.addEventListener('change', syncLabel);
    select.addEventListener('searchable:refresh', () => {
        syncLabel();

        if (instance.isOpen()) {
            rebuild();
        }
    });

    const observer = new MutationObserver(() => {
        syncLabel();

        if (instance.isOpen()) {
            rebuild();
        }
    });

    observer.observe(select, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: ['disabled', 'hidden', 'label'],
    });

    syncLabel();
    registry.set(select, instance);
    instances.add(instance);
};

export function initSearchableSelects(scope = document) {
    scope.querySelectorAll('select[data-searchable]').forEach(enhance);
}
