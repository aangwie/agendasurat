/**
 * Surat tugas: modal tambah/edit + hierarki tahun > bulan > kode > subkode.
 *
 * Kode arsip mengikuti bulan (Januari 00-03, Februari 04-06, dan seterusnya
 * tiga kode per bulan). Kode dan subkode yang sudah dipakai (per tahun)
 * dinonaktifkan agar tidak bisa dipilih ulang. Server tetap memvalidasi
 * keunikan kombinasi tersebut.
 */
import { refreshDateDisplays } from './date-display';

export function initTaskLetters() {
    const root = document.querySelector('[data-datatable="server"]');
    const modal = document.querySelector('[data-task-modal]');

    if (!root || !modal) {
        return;
    }

    const panel = modal.querySelector('[data-modal-panel]');
    const form = modal.querySelector('[data-task-form]');
    const methodInput = modal.querySelector('[data-method-input]');
    const title = modal.querySelector('[data-modal-title]');
    const description = modal.querySelector('[data-modal-description]');
    const submitLabel = modal.querySelector('[data-modal-submit]');
    const createFields = modal.querySelector('[data-task-create-fields]');
    const editFields = modal.querySelector('[data-task-edit-fields]');

    const yearInput = form?.querySelector('[data-task-year]');
    const monthSelect = form?.querySelector('[data-task-month]');
    const codeSelect = form?.querySelector('[data-task-code]');
    const subCodeSelect = form?.querySelector('[data-task-sub-code]');
    const prefixInput = form?.querySelector('[data-task-prefix]');
    const subject = form?.querySelector('[name="subject"]');
    const letterDate = form?.querySelector('[name="letter_date"]');
    const bidangLabel = modal.querySelector('[data-task-bidang]');
    const kodeBidangLabel = modal.querySelector('[data-task-kode-bidang]');
    const bidangSelect = form?.querySelector('[data-task-bidang-select]');
    const bidangPicker = modal.querySelector('[data-task-bidang-picker]');
    const bidangReadonly = modal.querySelector('[data-task-bidang-readonly]');
    const numberPreview = modal.querySelector('[data-task-number-preview]');

    const storeAction = form?.dataset.storeAction;
    const updateTemplate = form?.dataset.updateTemplate;

    if (!panel || !form || !storeAction || !updateTemplate || !yearInput || !monthSelect
        || !codeSelect || !subCodeSelect) {
        return;
    }

    let usedByYear = {};

    try {
        usedByYear = JSON.parse(root.dataset.used || form.dataset.used || '{}') ?? {};
    } catch (error) {
        usedByYear = {};
    }

    const initialYear = Number(root.dataset.initialYear) || new Date().getFullYear();
    const initialMonth = Number(root.dataset.initialMonth) || 1;
    const defaultBidang = bidangLabel?.textContent.trim() ?? '';
    const defaultKodeBidang = (form.dataset.kodeBidang ?? '').trim();

    let currentKodeBidang = defaultKodeBidang;

    const editYear = modal.querySelector('[data-task-edit-year]');
    const editMonth = modal.querySelector('[data-task-edit-month]');
    const editCode = modal.querySelector('[data-task-edit-code]');
    const editSubCode = modal.querySelector('[data-task-edit-sub-code]');
    const editKodeBidang = modal.querySelector('[data-task-edit-kode-bidang]');

    let lastFocused = null;

    const setKodeBidang = (value) => {
        currentKodeBidang = (value ?? '').trim();

        if (kodeBidangLabel) {
            kodeBidangLabel.textContent = currentKodeBidang || '—';
        }
    };

    /**
     * Administrator memilih bidang pemilik surat; nama dan kode bidang mengikuti
     * akun bidang yang dipilih.
     */
    const syncBidangSelection = () => {
        if (!bidangSelect) {
            return;
        }

        const option = bidangSelect.selectedOptions[0];

        if (!option) {
            setKodeBidang('');

            return;
        }

        if (bidangLabel) {
            bidangLabel.textContent = (option.dataset.bidangName ?? option.textContent).trim();
        }

        setKodeBidang(option.dataset.kodeBidang ?? '');
    };

    /**
     * Pratinjau nomor lengkap: kode depan / kode+subkode / kode bidang / tahun.
     */
    const syncNumberPreview = () => {
        if (!numberPreview) {
            return;
        }

        const prefix = (prefixInput?.value ?? '').trim();
        const letterCode = `${codeSelect.value}${subCodeSelect.value}`;

        numberPreview.textContent = [
            prefix || '…',
            letterCode || '…',
            currentKodeBidang || '…',
            yearInput.value || '…',
        ].join('/');
    };

    const usedForYear = (year) => new Set(usedByYear[String(year)] ?? []);

    const codeIsFull = (used, code) => {
        let count = 0;

        used.forEach((combination) => {
            if (combination.startsWith(`${code}-`)) {
                count += 1;
            }
        });

        return count >= 99;
    };

    const syncCodeOptions = () => {
        const month = Number(monthSelect.value);
        const used = usedForYear(yearInput.value);
        let firstAvailable = null;

        Array.from(codeSelect.options).forEach((option) => {
            if (option.dataset.placeholder !== undefined) {
                option.hidden = true;
                option.disabled = true;

                return;
            }

            const isCurrentMonth = Number(option.dataset.month) === month;
            const isFull = isCurrentMonth && codeIsFull(used, option.value);

            option.hidden = !isCurrentMonth;
            option.disabled = !isCurrentMonth || isFull;

            if (isCurrentMonth && !isFull && firstAvailable === null) {
                firstAvailable = option.value;
            }
        });

        const selected = codeSelect.selectedOptions[0];

        if (!selected || selected.hidden || selected.disabled) {
            codeSelect.value = firstAvailable ?? '';
        }

        codeSelect.dispatchEvent(new Event('searchable:refresh'));
    };

    const syncSubCodeOptions = () => {
        const used = usedForYear(yearInput.value);
        const code = codeSelect.value;
        let firstAvailable = null;

        Array.from(subCodeSelect.options).forEach((option) => {
            if (option.dataset.placeholder !== undefined) {
                option.hidden = true;
                option.disabled = true;

                return;
            }

            const isUsed = code !== '' && used.has(`${code}-${option.value}`);

            option.disabled = isUsed;

            if (!isUsed && firstAvailable === null) {
                firstAvailable = option.value;
            }
        });

        const selected = subCodeSelect.selectedOptions[0];

        if (!selected || selected.disabled) {
            subCodeSelect.value = firstAvailable ?? '';
        }

        subCodeSelect.dispatchEvent(new Event('searchable:refresh'));
    };

    const syncOptions = ({ resetSubCode = false } = {}) => {
        syncCodeOptions();

        if (resetSubCode) {
            subCodeSelect.value = '';
        }

        syncSubCodeOptions();
        syncNumberPreview();
    };

    const setEditSummary = (payload) => {
        if (!payload) {
            return;
        }

        if (editYear) {
            editYear.textContent = payload.year ?? '—';
        }

        if (editMonth) {
            editMonth.textContent = payload.month_label ?? '—';
        }

        if (editCode) {
            editCode.textContent = payload.code ?? '—';
        }

        if (editSubCode) {
            editSubCode.textContent = payload.sub_code ?? '—';
        }

        if (editKodeBidang) {
            editKodeBidang.textContent = payload.kode_bidang || '—';
        }
    };

    const setMode = (mode) => {
        const isEdit = mode === 'edit';

        if (methodInput) {
            methodInput.value = isEdit ? 'PUT' : '';
            methodInput.disabled = !isEdit;
        }

        createFields?.classList.toggle('hidden', isEdit);
        editFields?.classList.toggle('hidden', !isEdit);

        createFields?.querySelectorAll('input, select').forEach((field) => {
            field.disabled = isEdit;
        });

        // Bidang hanya dapat dipilih saat menambah surat; saat edit bidang tetap.
        bidangPicker?.classList.toggle('hidden', isEdit);
        bidangReadonly?.classList.toggle('hidden', Boolean(bidangSelect) && !isEdit);

        if (bidangSelect) {
            bidangSelect.disabled = isEdit;
            bidangSelect.dispatchEvent(new Event('searchable:refresh'));
        }

        if (title) {
            title.textContent = isEdit ? 'Edit Surat Tugas' : 'Tambah Surat Tugas';
        }

        if (description) {
            description.textContent = isEdit
                ? 'Perbarui nomor surat tugas. Nomor urut tahun/kode/subkode tetap.'
                : 'Pilih tahun, bulan, kode, dan subkode yang belum terpakai.';
        }

        if (submitLabel) {
            submitLabel.lastChild.textContent = isEdit ? ' Perbarui Surat Tugas' : ' Simpan Surat Tugas';
        }
    };

    const open = () => {
        lastFocused = document.activeElement;
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');

        window.setTimeout(() => {
            (createFields?.classList.contains('hidden') ? prefixInput : yearInput)?.focus({
                preventScroll: true,
            });
        }, 50);
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
            yearInput.value = initialYear;
            monthSelect.value = initialMonth;
            codeSelect.value = '';
            subCodeSelect.value = '';
            monthSelect.dispatchEvent(new Event('searchable:refresh'));
            bidangSelect?.dispatchEvent(new Event('searchable:refresh'));
        }

        if (bidangSelect) {
            syncBidangSelection();
        } else {
            if (bidangLabel) {
                bidangLabel.textContent = defaultBidang;
            }

            setKodeBidang(defaultKodeBidang);
        }

        syncOptions({ resetSubCode: reset });
        refreshDateDisplays();
        open();
    };

    const openEdit = (payload, { fill = true } = {}) => {
        setMode('edit');
        form.setAttribute('action', updateTemplate.replace('__ID__', String(payload?.id ?? '')));

        if (fill && payload) {
            if (prefixInput) {
                prefixInput.value = payload.letter_prefix ?? '';
            }

            if (subject) {
                subject.value = payload.subject ?? '';
            }

            if (letterDate) {
                letterDate.value = payload.letter_date ?? '';
            }

            // Nomor urut tetap: tampilkan nilai surat yang diedit pada pratinjau.
            yearInput.value = payload.year ?? '';
            monthSelect.value = payload.month ?? '';
            codeSelect.value = payload.code ?? '';
            subCodeSelect.value = payload.sub_code ?? '';

            [monthSelect, codeSelect, subCodeSelect].forEach((select) => {
                select.dispatchEvent(new Event('searchable:refresh'));
            });

            if (bidangLabel) {
                bidangLabel.textContent = payload.bidang ?? defaultBidang;
            }

            setKodeBidang(payload.kode_bidang ?? '');
            setEditSummary(payload);
        }

        syncNumberPreview();
        refreshDateDisplays();
        open();
    };

    root.addEventListener('click', (event) => {
        const createButton = event.target.closest('[data-task-open="create"]');

        if (createButton) {
            event.preventDefault();
            openCreate();

            return;
        }

        const editButton = event.target.closest('[data-task-edit]');

        if (!editButton) {
            return;
        }

        event.preventDefault();

        try {
            openEdit(JSON.parse(editButton.dataset.taskPayload ?? '{}'));
        } catch (error) {
            /* payload tidak valid: abaikan */
        }
    });

    yearInput.addEventListener('input', () => syncOptions());
    monthSelect.addEventListener('change', () => syncOptions({ resetSubCode: true }));
    codeSelect.addEventListener('change', () => {
        syncSubCodeOptions();
        syncNumberPreview();
    });
    bidangSelect?.addEventListener('change', () => {
        syncBidangSelection();
        syncNumberPreview();
    });
    prefixInput?.addEventListener('input', syncNumberPreview);

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
            panel.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea'),
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
        let payload = null;

        try {
            payload = JSON.parse(form.dataset.openLetter ?? 'null');
        } catch (error) {
            payload = null;
        }

        openEdit(payload ?? { id: openFormId }, { fill: payload !== null });
    }
}
