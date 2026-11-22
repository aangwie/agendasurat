import { refreshDateDisplays } from './date-display';

/**
 * Modal tambah/edit agenda kegiatan.
 */
export function initAgendas() {
    const root = document.querySelector('[data-datatable="server"]');
    const modal = document.querySelector('[data-agenda-modal]');

    if (!root || !modal) {
        return;
    }

    const panel = modal.querySelector('[data-modal-panel]');
    const form = modal.querySelector('[data-agenda-form]');
    const methodInput = modal.querySelector('[data-method-input]');
    const title = modal.querySelector('[data-modal-title]');
    const description = modal.querySelector('[data-modal-description]');
    const submitLabel = modal.querySelector('[data-modal-submit]');
    const storeAction = form?.dataset.storeAction;
    const updateTemplate = form?.dataset.updateTemplate;

    if (!panel || !form || !storeAction || !updateTemplate) {
        return;
    }

    const fields = {
        date: form.querySelector('[name="date"]'),
        place: form.querySelector('[name="place"]'),
        person_in_charge: form.querySelector('[name="person_in_charge"]'),
        participants: form.querySelector('[name="participants"]'),
    };

    let lastFocused = null;

    const setMode = (mode) => {
        const isEdit = mode === 'edit';

        if (methodInput) {
            methodInput.value = isEdit ? 'PUT' : '';
            methodInput.disabled = !isEdit;
        }

        if (title) {
            title.textContent = isEdit ? 'Edit Agenda' : 'Tambah Agenda';
        }

        if (description) {
            description.textContent = isEdit
                ? 'Perbarui data agenda kegiatan.'
                : 'Catat kegiatan yang akan dilaksanakan.';
        }

        if (submitLabel) {
            submitLabel.lastChild.textContent = isEdit ? ' Perbarui Agenda' : ' Simpan Agenda';
        }
    };

    const open = () => {
        lastFocused = document.activeElement;
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');

        window.setTimeout(() => fields.date?.focus({ preventScroll: true }), 50);
    };

    const close = () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');

        if (lastFocused instanceof HTMLElement) {
            lastFocused.focus({ preventScroll: true });
        }
    };

    const openCreate = ({ reset = true } = {}) => {
        setMode('create');
        form.setAttribute('action', storeAction);

        if (reset) {
            form.reset();
        }

        refreshDateDisplays();
        open();
    };

    const openEdit = (payload, { fill = true } = {}) => {
        setMode('edit');
        form.setAttribute('action', updateTemplate.replace('__ID__', String(payload?.id ?? '')));

        if (fill && payload) {
            Object.entries(fields).forEach(([key, field]) => {
                if (field) {
                    field.value = payload[key] ?? '';
                }
            });
        }

        refreshDateDisplays();
        open();
    };

    root.addEventListener('click', (event) => {
        const createButton = event.target.closest('[data-agenda-open="create"]');

        if (createButton) {
            event.preventDefault();
            openCreate();

            return;
        }

        const editButton = event.target.closest('[data-agenda-edit]');

        if (!editButton) {
            return;
        }

        event.preventDefault();

        try {
            openEdit(JSON.parse(editButton.dataset.agendaPayload ?? '{}'));
        } catch (error) {
            /* payload tidak valid: abaikan */
        }
    });

    modal.querySelectorAll('[data-modal-close], [data-modal-backdrop]').forEach((button) => {
        button.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            close();
        }
    });

    modal.addEventListener('keydown', (event) => {
        if (event.key !== 'Tab') {
            return;
        }

        const focusable = Array.from(
            panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select, textarea'),
        ).filter((element) => element.offsetParent !== null);

        if (focusable.length === 0) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    const openForm = root.dataset.openForm;
    const openFormId = root.dataset.openFormId;

    if (openForm === 'create') {
        openCreate({ reset: false });
    } else if (openForm === 'edit' && openFormId) {
        openEdit({ id: openFormId }, { fill: false });
    }
}
