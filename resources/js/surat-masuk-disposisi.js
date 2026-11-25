/**
 * Modal disposisi surat masuk.
 *
 * Menampilkan ringkasan surat, lampiran (tautan unduh), dan ceklist bidang.
 * Bidang yang dicentang akan menerima notifikasi WhatsApp berisi tautan surat.
 */

const STATUS_STYLES = {
    sent: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
    failed: 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
    skipped: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
};

const STATUS_LABELS = {
    sent: 'Terkirim',
    failed: 'Gagal',
    skipped: 'Dilewati',
};

const SHARE_ICON =
    '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="size-3 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" /></svg>';

const escapeHtml = (value) =>
    String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

export function initIncomingLetterDisposition() {
    const root = document.querySelector('[data-datatable="server"]');
    const modal = document.querySelector('[data-disposisi-modal]');

    if (!root || !modal) {
        return;
    }

    const panel = modal.querySelector('[data-disposisi-panel]');
    const form = modal.querySelector('[data-disposisi-form]');
    const list = modal.querySelector('[data-disposisi-list]');
    const countLabel = modal.querySelector('[data-disposisi-count]');
    const submitButton = modal.querySelector('[data-disposisi-submit]');
    const resultBox = modal.querySelector('[data-disposisi-result]');
    const resultTitle = modal.querySelector('[data-disposisi-result-title]');
    const resultList = modal.querySelector('[data-disposisi-result-list]');
    const attachmentLink = modal.querySelector('[data-disposisi-attachment-link]');
    const attachmentName = modal.querySelector('[data-disposisi-attachment-name]');
    const attachmentMeta = modal.querySelector('[data-disposisi-attachment-meta]');
    const subtitle = modal.querySelector('[data-disposisi-subtitle]');

    if (!panel || !form || !list) {
        return;
    }

    const fields = {
        agenda: modal.querySelector('[data-disposisi-agenda]'),
        number: modal.querySelector('[data-disposisi-number]'),
        subject: modal.querySelector('[data-disposisi-subject]'),
        sender: modal.querySelector('[data-disposisi-sender]'),
        date: modal.querySelector('[data-disposisi-date]'),
    };

    let sendUrl = '';
    let lastFocused = null;
    let activeButton = null;
    let bidangs = [];

    const setText = (element, value) => {
        if (element) {
            element.textContent = value ?? '—';
        }
    };

    const selectedValues = () =>
        Array.from(list.querySelectorAll('input[name="bidangs[]"]:checked')).map((input) => input.value);

    const updateCount = () => {
        const total = selectedValues().length;

        if (countLabel) {
            countLabel.textContent = total === 0 ? 'Belum ada bidang dipilih' : `${total} bidang dipilih`;
        }
    };

    const setResult = (tone, title, rows = []) => {
        if (!resultBox) {
            return;
        }

        const tones = {
            success: 'border-emerald-200 bg-emerald-50',
            error: 'border-rose-200 bg-rose-50',
        };

        resultBox.className = `rounded-xl border px-4 py-3 ${tones[tone] ?? tones.error}`;
        resultBox.classList.remove('hidden');

        if (resultTitle) {
            resultTitle.textContent = title;
        }

        if (resultList) {
            resultList.innerHTML = rows
                .map(
                    (row) =>
                        `<li class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-zinc-700">${escapeHtml(row.name)}</span>
                            <span class="badge ${STATUS_STYLES[row.status] ?? 'bg-zinc-100 text-zinc-600'}">
                                ${escapeHtml(row.label)}
                            </span>
                        </li>`,
                )
                .join('');
        }
    };

    const hideResult = () => {
        resultBox?.classList.add('hidden');

        if (resultList) {
            resultList.innerHTML = '';
        }
    };

    const renderBidangs = () => {
        if (bidangs.length === 0) {
            list.innerHTML =
                '<p class="rounded-xl bg-zinc-50 px-3 py-2.5 text-xs text-zinc-500">Belum ada akun bidang. Tambahkan akun pada menu Pengguna terlebih dahulu.</p>';
            updateCount();

            return;
        }

        list.innerHTML = bidangs
            .map((bidang, index) => {
                const disabled = !bidang.can_receive;
                const status = bidang.last_status
                    ? `<span class="badge ${STATUS_STYLES[bidang.last_status] ?? 'bg-zinc-100 text-zinc-600'}">
                            ${escapeHtml(bidang.last_status_label ?? STATUS_LABELS[bidang.last_status] ?? '')}
                       </span>`
                    : '';

                const phone = bidang.phone
                    ? `<span class="font-mono">+${escapeHtml(bidang.phone)}</span>`
                    : '<span class="text-amber-600">Tanpa nomor WhatsApp</span>';

                const primary = bidang.alias || bidang.name;
                const secondaryName = bidang.alias
                    ? `<span class="truncate">${escapeHtml(bidang.name)}</span>`
                    : '';

                return `<label class="flex cursor-pointer items-start gap-3 rounded-xl border border-zinc-200 bg-white px-3 py-2.5 transition hover:border-zinc-300 hover:bg-zinc-50 has-checked:border-rose-400 has-checked:bg-rose-50/60 ${
                    disabled ? 'cursor-not-allowed opacity-60' : ''
                }" title="${escapeHtml(bidang.name)}">
                    <input type="checkbox" name="bidangs[]" value="${escapeHtml(bidang.kode_bidang)}"
                        data-disposisi-checkbox data-index="${index}" ${disabled ? 'disabled' : ''}
                        class="mt-0.5 size-4 shrink-0 rounded border-zinc-300 accent-rose-600">

                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-xs font-semibold text-zinc-800">
                            ${escapeHtml(primary)}
                        </span>
                        <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-zinc-400">
                            <span class="font-mono">${escapeHtml(bidang.kode_bidang)}</span>
                            <span aria-hidden="true">&middot;</span>
                            ${phone}
                            ${status}
                            ${secondaryName}
                        </span>
                    </span>
                </label>`;
            })
            .join('');

        updateCount();
    };

    /**
     * Perbarui badge tujuan disposisi di bawah nomor surat pada baris tabel.
     */
    const renderTableBadges = () => {
        const cell = activeButton?.closest('[data-letter-dispositions-cell]');
        const container = cell?.querySelector('[data-letter-dispositions]');

        if (!container) {
            return;
        }

        const rows = bidangs.filter((bidang) => bidang.last_status);

        container.innerHTML = rows
            .map((bidang) => {
                const label = bidang.last_status_label ?? STATUS_LABELS[bidang.last_status] ?? '';
                const primary = bidang.alias || bidang.name;

                return `<span data-disposition-bidang="${escapeHtml(bidang.kode_bidang)}"
                    title="Disposisi: ${escapeHtml(bidang.name)} — ${escapeHtml(label)} (${escapeHtml(bidang.kode_bidang)})"
                    class="badge max-w-[10rem] ${STATUS_STYLES[bidang.last_status] ?? 'bg-zinc-100 text-zinc-600'}">
                    ${SHARE_ICON}
                    <span class="truncate">${escapeHtml(primary)}</span>
                </span>`;
            })
            .join('');

        container.classList.toggle('hidden', rows.length === 0);
    };

    const renderLetter = (data) => {
        setText(fields.agenda, data.letter?.agenda_number);
        setText(fields.number, data.letter?.letter_number);
        setText(fields.subject, data.letter?.subject);
        setText(fields.sender, data.letter?.sender);
        setText(fields.date, data.letter?.letter_date);

        if (subtitle) {
            subtitle.textContent = `Surat ${data.letter?.agenda_number ?? ''} — ${data.letter?.sender ?? ''}`;
        }

        const attachment = data.attachment ?? {};

        if (attachmentLink) {
            attachmentLink.href = attachment.url ?? '#';
            attachmentLink.style.display = attachment.available ? 'inline-flex' : '';
            attachmentLink.classList.toggle('hidden', !attachment.available);
        }

        if (attachmentName) {
            attachmentName.textContent = attachment.available
                ? (attachment.name ?? 'Lampiran surat')
                : 'Belum ada lampiran';
        }

        if (attachmentMeta) {
            attachmentMeta.textContent = attachment.available
                ? 'Klik unduh untuk memeriksa berkas sebelum disposisi.'
                : 'Unggah lampiran pada formulir surat agar penerima dapat mengunduhnya.';
        }
    };

    const open = () => {
        lastFocused = document.activeElement;
        modal.classList.remove('hidden');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    };

    const close = () => {
        modal.classList.add('hidden');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');

        if (lastFocused instanceof HTMLElement) {
            lastFocused.focus({ preventScroll: true });
        }
    };

    const load = async (url) => {
        bidangs = [];
        sendUrl = '';
        hideResult();
        list.innerHTML =
            '<p class="rounded-xl bg-zinc-50 px-3 py-2.5 text-xs text-zinc-500">Memuat daftar bidang...</p>';
        open();

        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            if (!response.ok) {
                throw new Error('Data disposisi tidak dapat dimuat.');
            }

            const data = await response.json();

            bidangs = Array.isArray(data.bidangs) ? data.bidangs : [];
            sendUrl = data.send_url ?? '';

            renderLetter(data);
            renderBidangs();
        } catch (error) {
            list.innerHTML = `<p class="rounded-xl bg-rose-50 px-3 py-2.5 text-xs text-rose-600">${escapeHtml(
                error.message,
            )}</p>`;
        }
    };

    // Buka popup disposisi dari kolom aksi tabel.
    root.addEventListener('click', (event) => {
        const button = event.target.closest('[data-letter-disposisi]');

        if (!button) {
            return;
        }

        event.preventDefault();

        const url = button.dataset.disposisiUrl;

        if (url) {
            activeButton = button;
            load(url);
        }
    });

    list.addEventListener('change', (event) => {
        if (event.target.matches('input[name="bidangs[]"]')) {
            updateCount();
        }
    });

    modal.querySelector('[data-disposisi-select-all]')?.addEventListener('click', () => {
        list.querySelectorAll('input[name="bidangs[]"]:not([disabled])').forEach((input) => {
            input.checked = true;
        });

        updateCount();
    });

    modal.querySelector('[data-disposisi-clear-all]')?.addEventListener('click', () => {
        list.querySelectorAll('input[name="bidangs[]"]').forEach((input) => {
            input.checked = false;
        });

        updateCount();
    });

    modal.querySelectorAll('[data-disposisi-close], [data-disposisi-backdrop]').forEach((element) => {
        element.addEventListener('click', close);
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

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const selected = selectedValues();

        if (selected.length === 0) {
            setResult('error', 'Belum ada bidang dipilih', []);

            return;
        }

        if (!sendUrl) {
            setResult('error', 'Alamat pengiriman disposisi tidak tersedia', []);

            return;
        }

        submitButton?.setAttribute('disabled', 'disabled');
        submitButton?.classList.add('opacity-60');

        try {
            const response = await fetch(sendUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ bidangs: selected }),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const validation = payload.errors ? Object.values(payload.errors).flat()[0] : null;

                throw new Error(validation ?? payload.message ?? 'Disposisi gagal dikirim.');
            }

            const results = Array.isArray(payload.results) ? payload.results : [];

            setResult(
                payload.ok ? 'success' : 'error',
                payload.message ?? 'Disposisi diproses.',
                results.map((row) => ({
                    name: row.name,
                    status: row.status,
                    label: row.status === 'sent' ? 'Terkirim' : row.status === 'skipped' ? 'Dilewati' : 'Gagal',
                })),
            );

            // Perbarui status terakhir pada ceklist.
            const byCode = new Map(results.map((row) => [row.kode_bidang, row.status]));

            bidangs = bidangs.map((bidang) =>
                byCode.has(bidang.kode_bidang)
                    ? {
                          ...bidang,
                          last_status: byCode.get(bidang.kode_bidang),
                          last_status_label:
                              byCode.get(bidang.kode_bidang) === 'sent'
                                  ? 'Terkirim'
                                  : byCode.get(bidang.kode_bidang) === 'skipped'
                                    ? 'Dilewati'
                                    : 'Gagal',
                      }
                    : bidang,
            );

            renderBidangs();
            renderTableBadges();
        } catch (error) {
            setResult('error', 'Disposisi gagal dikirim', [
                { name: error.message, status: 'failed', label: 'Gagal' },
            ]);
        } finally {
            submitButton?.removeAttribute('disabled');
            submitButton?.classList.remove('opacity-60');
        }
    });
}
