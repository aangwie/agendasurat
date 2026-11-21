/**
 * Modal tambah/edit pengguna.
 *
 * Form yang sama dipakai untuk create & update. Saat edit, password boleh
 * dikosongkan (berarti tidak diubah).
 */
export function initUserManagement() {
    const root = document.querySelector('[data-datatable="server"]');
    const modal = document.querySelector('[data-user-modal]');

    if (!root || !modal) {
        return;
    }

    const panel = modal.querySelector('[data-modal-panel]');
    const form = modal.querySelector('[data-user-form]');
    const methodInput = modal.querySelector('[data-method-input]');
    const title = modal.querySelector('[data-modal-title]');
    const description = modal.querySelector('[data-modal-description]');
    const submitLabel = modal.querySelector('[data-modal-submit]');
    const passwordHint = modal.querySelector('[data-password-hint]');
    const passwordInput = modal.querySelector('[data-password-input]');
    const passwordConfirm = modal.querySelector('[data-password-confirm]');
    const storeAction = form?.dataset.storeAction;
    const updateTemplate = form?.dataset.updateTemplate;

    if (!panel || !form || !storeAction || !updateTemplate) {
        return;
    }

    const fields = {
        name: form.querySelector('[name="name"]'),
        email: form.querySelector('[name="email"]'),
        phone: form.querySelector('[name="phone"]'),
    };

    const adminCheckbox = form.querySelector('[data-admin-checkbox]');

    let lastFocused = null;

    const setPasswordRequired = (required) => {
        [passwordInput, passwordConfirm].forEach((input) => {
            if (input) {
                input.required = required;
            }
        });

        passwordHint?.classList.toggle('hidden', required);
    };

    const setMode = (mode) => {
        const isEdit = mode === 'edit';

        if (methodInput) {
            methodInput.value = isEdit ? 'PUT' : '';
            methodInput.disabled = !isEdit;
        }

        if (title) {
            title.textContent = isEdit ? 'Edit Pengguna' : 'Tambah Pengguna';
        }

        if (description) {
            description.textContent = isEdit
                ? 'Perbarui data akun pengguna.'
                : 'Buat akun untuk bidang/unit kerja.';
        }

        if (submitLabel) {
            submitLabel.lastChild.textContent = isEdit ? ' Perbarui Pengguna' : ' Simpan Pengguna';
        }

        setPasswordRequired(!isEdit);
    };

    const open = () => {
        lastFocused = document.activeElement;
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');

        window.setTimeout(() => fields.name?.focus({ preventScroll: true }), 50);
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

            if (adminCheckbox) {
                adminCheckbox.checked = false;
            }
        }

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

            if (passwordInput) {
                passwordInput.value = '';
            }

            if (passwordConfirm) {
                passwordConfirm.value = '';
            }

            if (adminCheckbox) {
                adminCheckbox.checked = payload.is_admin === true;
            }
        }

        open();
    };

    root.addEventListener('click', (event) => {
        const createButton = event.target.closest('[data-user-open="create"]');

        if (createButton) {
            event.preventDefault();
            openCreate();
            return;
        }

        const editButton = event.target.closest('[data-user-edit]');

        if (!editButton) {
            return;
        }

        event.preventDefault();

        try {
            openEdit(JSON.parse(editButton.dataset.userPayload ?? '{}'));
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

    // Buka kembali modal bila validasi server gagal.
    const openForm = root.dataset.openForm;
    const openFormId = root.dataset.openFormId;

    if (openForm === 'create') {
        openCreate({ reset: false });
    } else if (openForm === 'edit' && openFormId) {
        openEdit({ id: openFormId }, { fill: false });
    }
}
