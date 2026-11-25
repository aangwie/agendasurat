import { refreshDateDisplays } from './date-display';

/**
 * Modal tambah/edit surat masuk.
 *
 * Form yang sama dipakai untuk create dan update; method & action diatur
 * lewat tombol yang diklik. Bila validasi server gagal, controller mengirim
 * `open_form` sehingga modal terbuka kembali dengan nilai lama.
 */
export function initIncomingLetters() {
    const root = document.querySelector('[data-datatable="server"]');
    const modal = document.querySelector('[data-letter-modal]');

    if (!root || !modal) {
        return;
    }

    const panel = modal.querySelector('[data-modal-panel]');
    const form = modal.querySelector('[data-letter-form]');
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
        agenda_number: form.querySelector('[name="agenda_number"]'),
        received_date: form.querySelector('[name="received_date"]'),
        letter_date: form.querySelector('[name="letter_date"]'),
        letter_number: form.querySelector('[name="letter_number"]'),
        sender: form.querySelector('[name="sender"]'),
        subject: form.querySelector('[name="subject"]'),
    };

    const attachmentInput = form.querySelector('[data-letter-attachment]');
    const attachmentCurrent = modal.querySelector('[data-letter-attachment-current]');
    const attachmentCurrentName = modal.querySelector('[data-letter-attachment-name]');

    const setCurrentAttachment = (name) => {
        if (!attachmentCurrent) {
            return;
        }

        const hasName = typeof name === 'string' && name.trim() !== '';

        attachmentCurrent.style.display = hasName ? 'flex' : '';
        attachmentCurrent.classList.toggle('hidden', !hasName);

        if (attachmentCurrentName) {
            attachmentCurrentName.textContent = hasName ? name : '';
        }
    };

    let lastFocused = null;

    const sifatCheckboxes = Array.from(form.querySelectorAll('[data-sifat-checkbox]'));

    const setSifat = (values) => {
        const selected = Array.isArray(values) ? values : [];

        sifatCheckboxes.forEach((checkbox) => {
            checkbox.checked = selected.includes(checkbox.value);
        });
    };

    const setMode = (mode) => {
        const isEdit = mode === 'edit';

        if (methodInput) {
            methodInput.value = isEdit ? 'PUT' : '';
            methodInput.disabled = !isEdit;
        }

        if (title) {
            title.textContent = isEdit ? 'Edit Surat Masuk' : 'Tambah Surat Masuk';
        }

        if (description) {
            description.textContent = isEdit
                ? 'Perbarui data surat masuk yang sudah tercatat.'
                : 'Lengkapi data surat yang diterima instansi.';
        }

        if (submitLabel) {
            submitLabel.lastChild.textContent = isEdit ? ' Perbarui Surat' : ' Simpan Surat';
        }
    };

    const open = () => {
        lastFocused = document.activeElement;
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');

        window.setTimeout(() => fields.agenda_number?.focus({ preventScroll: true }), 50);
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
            setSifat([]);
            setCurrentAttachment(null);
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

            setSifat(payload.sifat);
            setCurrentAttachment(payload.attachment_name ?? null);
        }

        // Berkas baru tidak boleh terbawa dari surat sebelumnya.
        if (attachmentInput) {
            attachmentInput.value = '';
        }

        refreshDateDisplays();
        open();
    };

    // Tombol "Tambah Surat Masuk" (toolbar & empty state).
    root.addEventListener('click', (event) => {
        const createButton = event.target.closest('[data-letter-open="create"]');

        if (createButton) {
            event.preventDefault();
            openCreate();
            return;
        }

        const editButton = event.target.closest('[data-letter-edit]');

        if (!editButton) {
            return;
        }

        event.preventDefault();

        try {
            openEdit(JSON.parse(editButton.dataset.letterPayload ?? '{}'));
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

    // Fokus tetap berada di dalam dialog.
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

    // Buka kembali modal bila validasi server gagal.
    const openForm = root.dataset.openForm;
    const openFormId = root.dataset.openFormId;

    if (openForm === 'create') {
        openCreate({ reset: false });
    } else if (openForm === 'edit' && openFormId) {
        openEdit({ id: openFormId }, { fill: false });
    }
}
