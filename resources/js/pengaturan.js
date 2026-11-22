/**
 * Halaman Pengaturan: update GitHub, perintah artisan, backup/import database,
 * dan terminal log. Semua aksi dikonfirmasi dengan SweetAlert2.
 */
const CONFIRMATIONS = {
    'git-push': {
        title: 'Push ke GitHub?',
        text: 'Perubahan dari komputer ini akan di-commit lalu di-push ke repository. Lanjutkan?',
        confirm: 'Ya, push',
        icon: 'question',
    },
    'git-pull': {
        title: 'Pull dari GitHub?',
        text: 'Server ini akan mengambil perubahan terbaru dari repository. Lanjutkan?',
        confirm: 'Ya, pull',
        icon: 'question',
    },
    migrate: {
        title: 'Jalankan migrasi?',
        text: 'php artisan migrate --force akan dijalankan pada database aplikasi.',
        confirm: 'Ya, migrasi',
        icon: 'warning',
    },
    'clear-cache': {
        title: 'Bersihkan cache?',
        text: 'Cache config, route, view, dan event akan dihapus.',
        confirm: 'Ya, bersihkan',
        icon: 'question',
    },
    symlink: {
        title: 'Buat symlink storage?',
        text: 'Perintah php artisan storage:link akan dijalankan.',
        confirm: 'Ya, buat symlink',
        icon: 'question',
    },
};

const LABELS = {
    'git-push': 'Push ke GitHub',
    'git-pull': 'Pull dari GitHub',
    migrate: 'Migrasi database',
    'clear-cache': 'Bersihkan cache',
    symlink: 'Buat symlink',
    backup: 'Backup database',
    import: 'Import database',
};

export function initSettingsPage() {
    const root = document.querySelector('[data-settings]');

    if (!root) {
        return;
    }

    const terminal = root.querySelector('[data-terminal]');
    const statusText = root.querySelector('[data-terminal-status-text]');
    const statusDot = root.querySelector('[data-terminal-dot]');
    const timeLabel = root.querySelector('[data-terminal-time]');
    const actionButtons = Array.from(root.querySelectorAll('[data-run]'));
    const verifyButtons = Array.from(root.querySelectorAll('[data-verify]'));
    const backupButton = root.querySelector('[data-backup]');
    const backupList = root.querySelector('[data-backup-list]');
    const githubForm = root.querySelector('[data-github-form]');
    const importForm = root.querySelector('[data-import-form]');

    const urls = {
        action: root.dataset.actionUrl,
        save: root.dataset.saveUrl,
        backup: root.dataset.backupUrl,
        import: root.dataset.importUrl,
        log: root.dataset.logUrl,
        logClear: root.dataset.logClearUrl,
    };

    const token = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const setStatus = (label, tone = 'idle') => {
        if (statusText) {
            statusText.textContent = label;
        }

        if (statusDot) {
            statusDot.className = 'size-1.5 rounded-full ' + (
                tone === 'running' ? 'bg-amber-400 animate-pulse'
                    : tone === 'error' ? 'bg-rose-500'
                        : 'bg-emerald-400'
            );
        }

        if (timeLabel) {
            timeLabel.textContent = new Date().toLocaleTimeString('id-ID', { hour12: false });
        }
    };

    const renderTerminal = (lines) => {
        if (!terminal) {
            return;
        }

        const list = Array.isArray(lines) ? lines : [];

        terminal.textContent = list.length
            ? list.join('\n')
            : '> Belum ada aktivitas. Jalankan salah satu aksi di atas.';

        terminal.scrollTop = terminal.scrollHeight;
    };

    const setBusy = (busy) => {
        [...actionButtons, ...verifyButtons, backupButton].forEach((button) => {
            if (button) {
                button.disabled = busy;
                button.classList.toggle('opacity-60', busy);
                button.classList.toggle('pointer-events-none', busy);
            }
        });
    };

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': token(),
                ...(options.headers ?? {}),
            },
        });

        let payload = {};

        try {
            payload = await response.json();
        } catch (error) {
            payload = { ok: false, log: 'Respons server tidak dapat dibaca.' };
        }

        if (!response.ok) {
            const message = payload.message
                ?? Object.values(payload.errors ?? {}).flat().join(' ')
                ?? `Permintaan gagal (HTTP ${response.status}).`;

            throw new Error(message);
        }

        return payload;
    };

    const notify = (ok, title, text) => {
        if (!window.Swal) {
            return;
        }

        window.Swal.fire({
            icon: ok ? 'success' : 'error',
            title,
            text,
            confirmButtonColor: '#e11d48',
            customClass: { popup: 'rounded-2xl font-sans', confirmButton: 'rounded-xl' },
        });
    };

    const confirm = async (key) => {
        const config = CONFIRMATIONS[key] ?? {
            title: 'Lanjutkan?',
            text: 'Aksi ini akan dijalankan di server.',
            confirm: 'Ya, lanjutkan',
            icon: 'question',
        };

        if (!window.Swal) {
            return window.confirm(config.text);
        }

        const result = await window.Swal.fire({
            icon: config.icon,
            title: config.title,
            text: config.text,
            showCancelButton: true,
            confirmButtonText: config.confirm,
            cancelButtonText: 'Batal',
            confirmButtonColor: '#e11d48',
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

    // ---- Simpan pengaturan GitHub ----
    githubForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const body = new URLSearchParams();

        new FormData(githubForm).forEach((value, key) => body.append(key, String(value)));

        setStatus('Menyimpan…', 'running');

        try {
            const payload = await request(urls.save, { method: 'PUT', body });
            const log = await request(urls.log);

            setStatus('Tersimpan');
            notify(true, 'Tersimpan', payload.message ?? 'Pengaturan GitHub disimpan.');
            renderTerminal(log.lines);
        } catch (error) {
            setStatus('Gagal', 'error');
            notify(false, 'Gagal menyimpan', error.message);
        }
    });

    // ---- Tombol aksi (git push, migrate, clear cache, symlink) ----
    actionButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const action = button.dataset.run;

            if (!(await confirm(action))) {
                return;
            }

            const body = new URLSearchParams({ action });
            const message = githubForm?.querySelector('[name="message"]')?.value;

            if (message) {
                body.append('message', message);
            }

            setBusy(true);
            setStatus(`${LABELS[action] ?? 'Memproses'}…`, 'running');

            try {
                const payload = await request(urls.action, { method: 'POST', body });

                renderTerminal(payload.lines);
                setStatus(payload.ok ? 'Selesai' : 'Gagal', payload.ok ? 'idle' : 'error');
                notify(
                    payload.ok,
                    payload.ok ? 'Berhasil' : 'Gagal',
                    payload.ok
                        ? (payload.hint ?? `${LABELS[action] ?? 'Aksi'} selesai dijalankan.`)
                        : (payload.hint ?? 'Periksa terminal log untuk detail kesalahan.'),
                );
            } catch (error) {
                setStatus('Gagal', 'error');
                notify(false, 'Gagal', error.message);
            } finally {
                setBusy(false);
            }
        });
    });

    // ---- Tes token GitHub (tanpa konfirmasi) ----
    verifyButtons.forEach((button) => {
        button.addEventListener('click', async () => {
            const body = new URLSearchParams({ action: button.dataset.verify });
            const tokenInput = githubForm?.querySelector('[name="token"]');

            // Pakai token yang baru diketik (bila ada) agar bisa diuji sebelum disimpan.
            if (tokenInput?.value) {
                body.append('token', tokenInput.value);
            }

            setBusy(true);
            setStatus('Memeriksa token…', 'running');

            try {
                const payload = await request(urls.action, { method: 'POST', body });

                renderTerminal(payload.lines);
                setStatus(payload.ok ? 'Token valid' : 'Token bermasalah', payload.ok ? 'idle' : 'error');
                notify(
                    payload.ok,
                    payload.ok ? 'Token valid' : 'Token tidak dapat dipakai',
                    payload.hint ?? payload.log,
                );
            } catch (error) {
                setStatus('Gagal', 'error');
                notify(false, 'Gagal memeriksa token', error.message);
            } finally {
                setBusy(false);
            }
        });
    });

    // ---- Backup database ----
    backupButton?.addEventListener('click', async () => {
        if (!(await confirm('backup'))) {
            return;
        }

        setBusy(true);
        setStatus('Backup database…', 'running');

        try {
            const payload = await request(urls.backup, { method: 'POST', body: new URLSearchParams() });

            renderTerminal(payload.lines);
            setStatus(payload.ok ? 'Selesai' : 'Gagal', payload.ok ? 'idle' : 'error');

            if (payload.ok && Array.isArray(payload.backups) && backupList) {
                renderBackups(backupList, payload.backups);
            }

            notify(payload.ok, payload.ok ? 'Backup dibuat' : 'Backup gagal', payload.log);
        } catch (error) {
            setStatus('Gagal', 'error');
            notify(false, 'Backup gagal', error.message);
        } finally {
            setBusy(false);
        }
    });

    // ---- Import database ----
    importForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const fileInput = importForm.querySelector('input[type="file"]');

        if (!fileInput?.files?.length) {
            notify(false, 'Berkas belum dipilih', 'Pilih berkas .sql terlebih dahulu.');

            return;
        }

        const confirmed = window.Swal
            ? (await window.Swal.fire({
                icon: 'warning',
                title: 'Import database?',
                html: 'Seluruh data saat ini akan <b>ditimpa</b> oleh isi berkas.<br>Backup pengaman dibuat otomatis sebelum import.',
                showCancelButton: true,
                confirmButtonText: 'Ya, import',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#52525b',
                reverseButtons: true,
                focusCancel: true,
            })).isConfirmed
            : window.confirm('Import akan menimpa data. Lanjutkan?');

        if (!confirmed) {
            return;
        }

        const formData = new FormData();
        formData.append('sql', fileInput.files[0]);

        setBusy(true);
        setStatus('Import database…', 'running');

        try {
            const payload = await request(urls.import, { method: 'POST', body: formData });

            renderTerminal(payload.lines);
            setStatus(payload.ok ? 'Selesai' : 'Gagal', payload.ok ? 'idle' : 'error');
            notify(payload.ok, payload.ok ? 'Import berhasil' : 'Import gagal', payload.log);
        } catch (error) {
            setStatus('Gagal', 'error');
            notify(false, 'Import gagal', error.message);
        } finally {
            setBusy(false);
        }
    });

    // ---- Log: muat ulang & bersihkan ----
    root.querySelector('[data-log-refresh]')?.addEventListener('click', async () => {
        try {
            const payload = await request(urls.log);

            renderTerminal(payload.lines);
            setStatus('Log dimuat');
        } catch (error) {
            setStatus('Gagal', 'error');
        }
    });

    root.querySelector('[data-log-clear]')?.addEventListener('click', async () => {
        const confirmed = window.Swal
            ? (await window.Swal.fire({
                icon: 'question',
                title: 'Bersihkan log?',
                text: 'Riwayat log terminal akan dihapus.',
                showCancelButton: true,
                confirmButtonText: 'Ya, bersihkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#52525b',
                reverseButtons: true,
                focusCancel: true,
            })).isConfirmed
            : window.confirm('Bersihkan log terminal?');

        if (!confirmed) {
            return;
        }

        try {
            const payload = await request(urls.logClear, { method: 'DELETE', body: new URLSearchParams() });

            renderTerminal(payload.lines);
            setStatus('Log dibersihkan');
        } catch (error) {
            setStatus('Gagal', 'error');
        }
    });

    renderTerminal((terminal?.textContent ?? '').split('\n'));
    setStatus('Siap');
}



/**
 * Render ulang daftar berkas backup setelah backup baru dibuat.
 */
function renderBackups(container, backups) {
    if (!Array.isArray(backups) || backups.length === 0) {
        container.innerHTML = '<p class="rounded-xl border border-dashed border-zinc-200 px-3 py-4 text-center text-xs text-zinc-400">Belum ada berkas backup.</p>';

        return;
    }

    container.innerHTML = backups.map((backup) => `
        <div class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white px-3 py-2.5">
            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-zinc-100 text-zinc-500">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6"
                    stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round"
                    d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate font-mono text-[11px] font-semibold text-zinc-800">${escapeHtml(backup.filename)}</p>
                <p class="text-[10px] text-zinc-400">${escapeHtml(backup.created_at)}</p>
            </div>
            <a href="${escapeHtml(backup.download_url ?? '#')}"
                class="shrink-0 rounded-lg px-2 py-1 text-[11px] font-semibold text-rose-600 transition hover:bg-rose-50">
                Unduh
            </a>
        </div>
    `).join('');
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
