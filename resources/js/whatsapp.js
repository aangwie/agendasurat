/**
 * Halaman Whatsapp Gateway (QR + sesi) dan halaman uji kirim pesan.
 *
 * Semua permintaan diteruskan ke controller Laravel yang memanggil gateway
 * Baileys, sehingga URL gateway & device ID tidak pernah dipakai langsung
 * dari browser (menghindari masalah CORS).
 */

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const requestJson = async (url, options = {}) => {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers ?? {}),
        },
    });

    let payload = {};

    try {
        payload = await response.json();
    } catch (error) {
        payload = {};
    }

    if (!response.ok) {
        const validation = payload.errors ? Object.values(payload.errors).flat()[0] : null;
        throw new Error(validation ?? payload.message ?? 'Permintaan gagal diproses.');
    }

    return payload;
};

const notify = (icon, title) => {
    window.Swal?.fire({
        toast: true,
        position: 'top-end',
        icon,
        title,
        showConfirmButton: false,
        timer: 3200,
        timerProgressBar: true,
    });
};

const confirmAction = async ({ title, text, confirmText = 'Ya, lanjutkan', icon = 'warning' }) => {
    if (!window.Swal) {
        return window.confirm(text ?? title);
    }

    const result = await window.Swal.fire({
        title,
        text,
        icon,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: 'Batal',
        confirmButtonColor: '#059669',
        cancelButtonColor: '#52525b',
        reverseButtons: true,
        focusCancel: true,
        customClass: {
            popup: 'rounded-2xl font-sans',
            confirmButton: 'rounded-xl',
            cancelButton: 'rounded-xl',
        },
    });

    return result.isConfirmed;
};

const TONES = {
    connected: {
        label: 'Terhubung',
        dot: 'bg-emerald-500',
        hero: 'bg-emerald-500/15 text-emerald-200 ring-1 ring-emerald-400/30',
        card: 'bg-emerald-50 text-emerald-700',
    },
    pending: {
        label: 'Menunggu pindai',
        dot: 'bg-amber-500',
        hero: 'bg-amber-500/15 text-amber-200 ring-1 ring-amber-400/30',
        card: 'bg-amber-50 text-amber-700',
    },
    connecting: {
        label: 'Menghubungkan',
        dot: 'bg-sky-400 animate-pulse',
        hero: 'bg-sky-500/15 text-sky-200 ring-1 ring-sky-400/30',
        card: 'bg-sky-50 text-sky-700',
    },
    disconnected: {
        label: 'Belum terhubung',
        dot: 'bg-zinc-400',
        hero: 'bg-white/10 text-zinc-200 ring-1 ring-white/10',
        card: 'bg-zinc-100 text-zinc-600',
    },
    offline: {
        label: 'Gateway offline',
        dot: 'bg-rose-500',
        hero: 'bg-rose-500/15 text-rose-200 ring-1 ring-rose-400/30',
        card: 'bg-rose-50 text-rose-700',
    },
};


const toneFor = (status) => {
    if (status === 'initializing' || status === 'connecting') {
        return TONES.connecting;
    }

    return TONES[status] ?? (status === 'error' ? TONES.offline : TONES.disconnected);
};

/**
 * Halaman koneksi: QR, polling status, simpan sesi, putuskan koneksi,
 * dan penyimpanan pengaturan gateway.
 */
export function initWhatsappGateway() {
    const root = document.querySelector('[data-whatsapp-gateway]');

    if (!root) {
        return;
    }

    const urls = {
        qr: root.dataset.qrUrl,
        status: root.dataset.statusUrl,
        save: root.dataset.saveUrl,
        session: root.dataset.sessionUrl,
        disconnect: root.dataset.disconnectUrl,
    };

    const pollInterval = Number(root.dataset.pollInterval ?? 5000) || 5000;

    const el = {
        statusBadge: root.querySelector('[data-wa-status-badge]'),
        statusDot: root.querySelector('[data-wa-status-dot]'),
        statusLabel: root.querySelector('[data-wa-status-label]'),
        gatewayLabel: root.querySelector('[data-wa-gateway-label]'),
        baseUrlLabel: root.querySelector('[data-wa-base-url]'),
        deviceLabel: root.querySelector('[data-wa-device-label]'),
        connectionBadge: root.querySelector('[data-wa-connection-badge]'),
        connectionDot: root.querySelector('[data-wa-connection-dot]'),
        connectionLabel: root.querySelector('[data-wa-connection-label]'),
        statusText: root.querySelector('[data-wa-status-text]'),
        checkedAt: root.querySelector('[data-wa-checked-at]'),
        qrImage: root.querySelector('[data-wa-qr-image]'),
        qrPlaceholder: root.querySelector('[data-wa-qr-placeholder]'),
        qrPlaceholderTitle: root.querySelector('[data-wa-qr-placeholder-title]'),
        qrPlaceholderText: root.querySelector('[data-wa-qr-placeholder-text]'),
        qrLoading: root.querySelector('[data-wa-qr-loading]'),
        sessionPhone: root.querySelector('[data-wa-session-phone]'),
        sessionDevice: root.querySelector('[data-wa-session-device]'),
        sessionSavedAt: root.querySelector('[data-wa-session-saved-at]'),
        refreshButton: root.querySelector('[data-wa-refresh]'),
        resetButton: root.querySelector('[data-wa-reset]'),
        disconnectButton: root.querySelector('[data-wa-disconnect]'),
        saveSessionButton: root.querySelector('[data-wa-save-session]'),
        settingsForm: root.querySelector('[data-wa-settings-form]'),
    };

    let pollTimer = null;
    let loading = false;

    const setQrState = (state, dataUrl = null) => {
        el.qrImage?.classList.toggle('hidden', state !== 'qr');
        el.qrPlaceholder?.classList.toggle('hidden', state !== 'placeholder');
        el.qrLoading?.classList.toggle('hidden', state !== 'loading');

        if (state === 'qr' && dataUrl && el.qrImage) {
            el.qrImage.src = dataUrl;
        }
    };

    const setBusy = (busy) => {
        [el.refreshButton, el.resetButton, el.disconnectButton, el.saveSessionButton].forEach((button) => {
            if (button) {
                button.disabled = busy;
                button.classList.toggle('opacity-60', busy);
                button.classList.toggle('pointer-events-none', busy);
            }
        });
    };

    const renderSession = (session) => {
        if (!session) {
            return;
        }

        if (el.sessionPhone) {
            el.sessionPhone.textContent = session.phone_number ? `+${session.phone_number}` : 'Belum ada';
        }

        if (el.sessionDevice) {
            el.sessionDevice.textContent = session.device_id ?? '—';
        }

        if (el.sessionSavedAt) {
            el.sessionSavedAt.textContent = session.saved_at ?? '—';
        }
    };

    const applyResult = (result) => {
        const status = String(result.status ?? 'unknown');
        const tone = toneFor(status);

        if (el.statusBadge) {
            el.statusBadge.className = `badge ${tone.hero}`;
        }

        if (el.connectionBadge) {
            el.connectionBadge.className = `badge ${tone.card}`;
        }

        [el.statusDot, el.connectionDot].forEach((dot) => {
            if (dot) {
                dot.className = `size-1.5 rounded-full ${tone.dot}`;
            }
        });

        if (el.statusLabel) {
            el.statusLabel.textContent = tone.label;
        }

        if (el.connectionLabel) {
            el.connectionLabel.textContent = tone.label;
        }

        if (el.statusText) {
            el.statusText.textContent = result.message ?? '';
        }

        if (el.checkedAt) {
            el.checkedAt.textContent = new Date().toLocaleTimeString('id-ID', { hour12: false });
        }

        if (result.qr) {
            setQrState('qr', result.qr);
        } else if (status === 'connected') {
            setQrState('placeholder');
            if (el.qrPlaceholderTitle) el.qrPlaceholderTitle.textContent = 'Perangkat terhubung';
            if (el.qrPlaceholderText) el.qrPlaceholderText.textContent = 'Tidak perlu memindai QR lagi.';
        } else if (status === 'disconnected') {
            setQrState('placeholder');
            if (el.qrPlaceholderTitle) el.qrPlaceholderTitle.textContent = 'QR belum dimuat';
            if (el.qrPlaceholderText) el.qrPlaceholderText.textContent = 'Klik "Muat QR" untuk menampilkan kode QR.';
        } else {
            setQrState('loading');
        }

        if (result.session) {
            renderSession(result.session);
        }
    };

    const stopPolling = () => {
        if (pollTimer) {
            window.clearTimeout(pollTimer);
            pollTimer = null;
        }
    };

    const schedulePoll = (status) => {
        stopPolling();

        if (['connected', 'disconnected', 'offline', 'error'].includes(status)) {
            return;
        }

        pollTimer = window.setTimeout(() => {
            if (document.hidden) {
                schedulePoll(status);
                return;
            }

            loadQr({ silent: true });
        }, pollInterval);
    };

    const loadQr = async ({ force = false, silent = false } = {}) => {
        if (loading) {
            return;
        }

        loading = true;

        if (!silent) {
            setQrState('loading');
            setBusy(true);
        }

        try {
            const result = await requestJson(force ? `${urls.qr}?force=1` : urls.qr);
            applyResult(result);
            schedulePoll(String(result.status ?? 'unknown'));
        } catch (error) {
            applyResult({ status: 'error', message: error.message });
            schedulePoll('error');
        } finally {
            loading = false;
            setBusy(false);
        }
    };

    el.refreshButton?.addEventListener('click', () => loadQr());

    el.resetButton?.addEventListener('click', async () => {
        const confirmed = await confirmAction({
            title: 'Reset sesi & buat QR baru?',
            text: 'Sesi perangkat pada gateway akan dihapus. Perangkat yang sudah tertaut harus dipindai ulang.',
            confirmText: 'Ya, reset',
        });

        if (confirmed) {
            await loadQr({ force: true });
        }
    });

    el.disconnectButton?.addEventListener('click', async () => {
        const confirmed = await confirmAction({
            title: 'Putuskan koneksi WhatsApp?',
            text: 'Perangkat akan dikeluarkan dari gateway dan sesi tersimpan dihapus.',
            confirmText: 'Ya, putuskan',
            icon: 'warning',
        });

        if (!confirmed) {
            return;
        }

        setBusy(true);

        try {
            const result = await requestJson(urls.disconnect, { method: 'POST' });

            notify(result.ok ? 'success' : 'error', result.message ?? 'Permintaan diproses.');
            applyResult({ status: 'disconnected', message: result.message });

            if (result.session) {
                renderSession(result.session);
            }
        } catch (error) {
            notify('error', error.message);
        } finally {
            setBusy(false);
        }
    });

    el.saveSessionButton?.addEventListener('click', async () => {
        setBusy(true);

        try {
            const result = await requestJson(urls.session, { method: 'POST' });

            notify('success', result.message ?? 'Sesi disimpan.');

            if (result.session) {
                renderSession(result.session);
            }
        } catch (error) {
            notify('error', error.message);
        } finally {
            setBusy(false);
        }
    });

    el.settingsForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(el.settingsForm);
        const submitButton = el.settingsForm.querySelector('[type="submit"]');

        submitButton?.setAttribute('disabled', 'disabled');

        try {
            const result = await requestJson(urls.save, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    gateway_url: String(formData.get('gateway_url') ?? '').trim(),
                    device_id: String(formData.get('device_id') ?? '').trim(),
                }),
            });

            if (el.gatewayLabel) {
                el.gatewayLabel.textContent = result.base_url ?? '';
            }

            if (el.baseUrlLabel) {
                el.baseUrlLabel.textContent = result.base_url ?? '';
            }

            if (el.deviceLabel) {
                el.deviceLabel.textContent = result.device_id ?? '';
            }

            if (result.session) {
                renderSession(result.session);
            }

            notify('success', result.message ?? 'Pengaturan disimpan.');
            await loadQr();
        } catch (error) {
            notify('error', error.message);
        } finally {
            submitButton?.removeAttribute('disabled');
        }
    });

    // Muat QR otomatis saat halaman dibuka.
    loadQr();
}


/**
 * Halaman uji pesan: kirim pesan percobaan & tampilkan riwayat singkat.
 */
export function initWhatsappTest() {
    const root = document.querySelector('[data-whatsapp-test]');

    if (!root) {
        return;
    }

    const form = root.querySelector('[data-wa-test-form]');
    const recipient = form?.querySelector('[name="recipient"]');
    const message = form?.querySelector('[name="message"]');
    const submitButton = root.querySelector('[data-wa-submit]');
    const counter = root.querySelector('[data-wa-counter]');
    const resultBox = root.querySelector('[data-wa-result]');
    const resultTitle = root.querySelector('[data-wa-result-title]');
    const resultText = root.querySelector('[data-wa-result-text]');
    const historyList = root.querySelector('[data-wa-history]');
    const connectionBadge = root.querySelector('[data-wa-test-connection]');
    const connectionDot = root.querySelector('[data-wa-test-dot]');
    const connectionLabel = root.querySelector('[data-wa-test-label]');
    const connectionStatus = root.querySelector('[data-wa-test-status]');
    const attachmentInput = form?.querySelector('[data-wa-attachment]');
    const attachmentPreview = root.querySelector('[data-wa-attachment-preview]');
    const attachmentThumb = root.querySelector('[data-wa-attachment-thumb]');
    const attachmentIcon = root.querySelector('[data-wa-attachment-icon]');
    const attachmentName = root.querySelector('[data-wa-attachment-name]');
    const attachmentMeta = root.querySelector('[data-wa-attachment-meta]');
    const attachmentClear = root.querySelector('[data-wa-attachment-clear]');
    const maxAttachmentKb = Number(root.dataset.maxAttachmentKb ?? 0) || 8192;
    const maxAttachmentBytes = maxAttachmentKb * 1024;
    const sessionPhone = root.dataset.sessionPhone ?? '';

    const history = [];

    const setResult = (tone, title, detail) => {
        if (!resultBox) {
            return;
        }

        const tones = {
            success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
            error: 'border-rose-200 bg-rose-50 text-rose-700',
        };

        resultBox.className = `mt-5 rounded-2xl border px-4 py-3 ${tones[tone] ?? tones.error}`;
        resultBox.classList.remove('hidden');

        if (resultTitle) {
            resultTitle.textContent = title;
        }

        if (resultText) {
            resultText.textContent = detail ?? '';
        }
    };

    const renderHistory = () => {
        if (!historyList) {
            return;
        }

        historyList.textContent = '';

        if (history.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'rounded-xl bg-zinc-50 px-3 py-2.5 text-xs text-zinc-500';
            empty.textContent = 'Belum ada pesan uji yang dikirim.';
            historyList.append(empty);

            return;
        }

        history.forEach((entry) => {
            const item = document.createElement('li');
            item.className = 'flex flex-wrap items-center justify-between gap-2 rounded-xl bg-zinc-50 px-3 py-2.5';

            const info = document.createElement('span');
            info.className = 'min-w-0';

            const phone = document.createElement('span');
            phone.className = 'block font-mono text-xs font-semibold text-zinc-800';
            phone.textContent = entry.recipient;

            const note = document.createElement('span');
            note.className = 'block truncate text-[11px] text-zinc-500';
            note.textContent = entry.attachment
                ? `${entry.message || 'Tanpa pesan'} · Lampiran: ${entry.attachment}`
                : entry.message;
            note.title = note.textContent;

            info.append(phone, note);

            const badge = document.createElement('span');
            badge.className = `badge ${entry.ok ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}`;
            badge.textContent = `${entry.ok ? 'Terkirim' : 'Gagal'} · ${entry.time}`;

            item.append(info, badge);
            historyList.append(item);
        });
    };

    const pushHistory = (entry) => {
        history.unshift(entry);
        history.splice(10);
        renderHistory();
    };

    let attachmentUrl = null;

    const formatSize = (bytes) => {
        if (bytes >= 1024 * 1024) {
            return `${(bytes / (1024 * 1024)).toFixed(1).replace('.', ',')} MB`;
        }

        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    };

    const currentAttachment = () => attachmentInput?.files?.[0] ?? null;

    const renderAttachment = () => {
        const file = currentAttachment();

        if (attachmentUrl) {
            URL.revokeObjectURL(attachmentUrl);
            attachmentUrl = null;
        }

        if (!attachmentPreview) {
            return;
        }

        // `display` diatur lewat style agar tidak bergantung pada urutan utility Tailwind.
        attachmentPreview.style.display = file ? 'flex' : '';

        if (!file) {
            attachmentPreview.classList.add('hidden');

            if (attachmentThumb) {
                attachmentThumb.classList.add('hidden');
                attachmentThumb.removeAttribute('src');
            }

            return;
        }

        attachmentPreview.classList.remove('hidden');

        if (attachmentName) {
            attachmentName.textContent = file.name;
        }

        if (attachmentMeta) {
            attachmentMeta.textContent = `${formatSize(file.size)} · ${file.type || 'berkas'}`;
        }

        const isImage = file.type.startsWith('image/');

        if (attachmentThumb && isImage) {
            attachmentUrl = URL.createObjectURL(file);
            attachmentThumb.src = attachmentUrl;
            attachmentThumb.classList.remove('hidden');
        } else if (attachmentThumb) {
            attachmentThumb.classList.add('hidden');
            attachmentThumb.removeAttribute('src');
        }

        attachmentIcon?.classList.toggle('hidden', isImage);
    };

    const clearAttachment = () => {
        if (attachmentInput) {
            attachmentInput.value = '';
        }

        renderAttachment();
    };

    attachmentInput?.addEventListener('change', () => {
        const file = currentAttachment();

        if (file && file.size > maxAttachmentBytes) {
            setResult(
                'error',
                'Lampiran terlalu besar',
                `Ukuran maksimal ${Math.round(maxAttachmentKb / 1024)} MB.`,
            );
            clearAttachment();

            return;
        }

        renderAttachment();
    });

    attachmentClear?.addEventListener('click', clearAttachment);

    renderAttachment();

    const updateCounter = () => {
        if (counter) {
            counter.textContent = `${message?.value.length ?? 0} / 4096`;
        }
    };

    message?.addEventListener('input', updateCounter);
    updateCounter();

    root.querySelectorAll('[data-wa-pick-phone]').forEach((button) => {
        button.addEventListener('click', () => {
            if (recipient) {
                recipient.value = button.dataset.waPickPhone ?? '';
                recipient.focus();
            }
        });
    });

    root.querySelector('[data-wa-fill-self]')?.addEventListener('click', () => {
        if (recipient && sessionPhone) {
            recipient.value = sessionPhone;
            recipient.focus();
        }
    });


    form?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const file = currentAttachment();
        const text = message?.value.trim() ?? '';
        const payload = {
            recipient: recipient?.value.trim() ?? '',
            message: text,
        };

        if (!payload.recipient) {
            setResult('error', 'Data belum lengkap', 'Nomor tujuan wajib diisi.');
            return;
        }

        if (!payload.message && !file) {
            setResult('error', 'Data belum lengkap', 'Isi pesan atau pilih lampiran terlebih dahulu.');
            return;
        }

        if (file && file.size > maxAttachmentBytes) {
            setResult(
                'error',
                'Lampiran terlalu besar',
                `Ukuran maksimal ${Math.round(maxAttachmentKb / 1024)} MB.`,
            );
            return;
        }

        submitButton?.setAttribute('disabled', 'disabled');
        submitButton?.classList.add('opacity-60');

        const time = new Date().toLocaleTimeString('id-ID', { hour12: false });

        try {
            let requestOptions;

            if (file) {
                const body = new FormData();
                body.append('recipient', payload.recipient);
                body.append('message', payload.message);
                body.append('attachment', file);

                // Jangan set Content-Type: browser menambahkan boundary multipart.
                requestOptions = { method: 'POST', body };
            } else {
                requestOptions = {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                };
            }

            const result = await requestJson(root.dataset.sendUrl, requestOptions);

            setResult(
                result.ok ? 'success' : 'error',
                result.ok ? 'Pesan terkirim' : 'Pengiriman gagal',
                result.message ?? '',
            );

            pushHistory({
                recipient: result.recipient ?? payload.recipient,
                message: payload.message.slice(0, 80),
                attachment: file?.name ?? null,
                ok: result.ok === true,
                time,
            });

            if (result.ok) {
                clearAttachment();
            }
        } catch (error) {
            setResult('error', 'Pengiriman gagal', error.message);

            pushHistory({
                recipient: payload.recipient,
                message: payload.message.slice(0, 80),
                attachment: file?.name ?? null,
                ok: false,
                time,
            });
        } finally {
            submitButton?.removeAttribute('disabled');
            submitButton?.classList.remove('opacity-60');
        }
    });

    const checkStatus = async () => {
        try {
            const result = await requestJson(root.dataset.statusUrl);
            const status = String(result.status ?? 'unknown');
            const connected = status === 'connected';
            const offline = status === 'offline';

            if (connectionLabel) {
                connectionLabel.textContent = connected ? 'Terhubung' : (offline ? 'Gateway offline' : 'Belum terhubung');
            }

            if (connectionDot) {
                connectionDot.className = `size-1.5 rounded-full ${
                    connected ? 'bg-emerald-400' : (offline ? 'bg-rose-500' : 'bg-amber-400')
                }`;
            }

            if (connectionBadge) {
                connectionBadge.className = `badge ${
                    connected
                        ? 'bg-emerald-500/15 text-emerald-200 ring-1 ring-emerald-400/30'
                        : 'bg-white/10 text-zinc-200 ring-1 ring-white/10'
                }`;
            }

            if (connectionStatus) {
                connectionStatus.textContent = result.message ?? (connected ? 'Terhubung' : 'Belum terhubung');
            }
        } catch (error) {
            if (connectionLabel) {
                connectionLabel.textContent = 'Tidak diketahui';
            }

            if (connectionStatus) {
                connectionStatus.textContent = error.message;
            }
        }
    };

    renderHistory();
    checkStatus();
}

