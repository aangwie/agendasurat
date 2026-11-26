/**
 * Logout otomatis setelah tidak ada aktivitas pengguna.
 *
 * Batas waktu diambil dari meta tag yang dirender layout aplikasi
 * (`config('session.idle_timeout')`), sehingga browser dan server memakai
 * angka yang sama. Server tetap menjadi penentu akhir: middleware
 * `EnsureSessionIsNotIdle` menolak permintaan setelah batas terlampaui.
 *
 * - Aktivitas nyata (klik, ketik, gulir, sentuh) memperpanjang sesi.
 * - Detak CSRF berkala TIDAK dihitung sebagai aktivitas (lihat csrf.js).
 * - Beberapa tab disinkronkan lewat localStorage agar tidak saling logout.
 * - Peringatan SweetAlert2 memberi pilihan "Tetap Masuk" sebelum logout.
 */
const ACTIVITY_EVENTS = ['mousedown', 'mousemove', 'keydown', 'wheel', 'scroll', 'touchstart', 'click', 'input'];

// Kirim sinyal aktivitas ke server maksimal sekali per menit (hemat request).
const PING_INTERVAL = 60 * 1000;

const TICK_INTERVAL = 1000;

const STORAGE_KEY = 'agenda-surat:last-activity';

const readMeta = (name) => document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? null;

/**
 * Format sisa waktu menjadi teks yang mudah dibaca.
 */
const formatRemaining = (seconds) => {
    const total = Math.max(0, Math.ceil(seconds));
    const minutes = Math.floor(total / 60);
    const rest = total % 60;

    return minutes > 0 ? `${minutes} menit ${String(rest).padStart(2, '0')} detik` : `${rest} detik`;
};

/**
 * Simpan waktu aktivitas agar tab lain ikut memperpanjang sesi.
 */
const rememberActivity = (timestamp) => {
    try {
        window.localStorage.setItem(STORAGE_KEY, String(timestamp));
    } catch (error) {
        /* localStorage tidak tersedia (mode privat): abaikan. */
    }
};

const recallActivity = () => {
    try {
        return Number(window.localStorage.getItem(STORAGE_KEY)) || 0;
    } catch (error) {
        return 0;
    }
};

export function initIdleLogout() {
    const timeout = Number(readMeta('session-idle-timeout'));
    const logoutUrl = readMeta('session-idle-logout-url');
    const keepAliveUrl = readMeta('session-keepalive-url');

    // Halaman tanpa konfigurasi (mis. login) atau fitur dimatikan (0).
    if (!Number.isFinite(timeout) || timeout <= 0 || !logoutUrl) {
        return;
    }

    const warning = Math.min(Math.max(0, Number(readMeta('session-idle-warning')) || 60), timeout);

    let lastActivity = Math.max(Date.now(), recallActivity());
    let lastPing = Date.now();
    let lastStored = 0;
    let warningOpen = false;
    let finished = false;
    // Peringatan ditutup karena tab lain aktif, bukan karena pengguna memilih keluar.
    let syncingTab = false;
    let warningInterval = null;
    let ticker = null;

    const remaining = () => timeout - (Date.now() - lastActivity) / 1000;

    /**
     * Beri tahu server bahwa pengguna masih aktif.
     */
    const ping = () => {
        if (!keepAliveUrl) {
            return;
        }

        lastPing = Date.now();

        fetch(keepAliveUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store',
        }).catch(() => {
            /* offline / server sibuk: hitungan di server tetap berjalan */
        });
    };

    const stopWarning = () => {
        window.clearInterval(warningInterval);
        warningInterval = null;
        warningOpen = false;
    };

    /**
     * Akhiri sesi di server lalu kembali ke halaman masuk.
     */
    const logout = () => {
        if (finished) {
            return;
        }

        finished = true;
        window.clearInterval(ticker);
        stopWarning();
        window.Swal?.close();

        window.location.href = logoutUrl;
    };

    /**
     * Perpanjang sesi karena pengguna memilih "Tetap Masuk".
     */
    const extend = () => {
        lastActivity = Date.now();
        rememberActivity(lastActivity);
        stopWarning();
        ping();
    };

    const showWarning = () => {
        warningOpen = true;

        if (!window.Swal) {
            // Tanpa SweetAlert2, logout otomatis tetap berjalan saat waktu habis.
            return;
        }

        window.Swal.fire({
            icon: 'warning',
            title: 'Sesi akan berakhir',
            html:
                '<p>Tidak ada aktivitas terdeteksi. Anda akan keluar otomatis dalam ' +
                '<span data-idle-countdown class="font-semibold text-rose-600">—</span>.</p>',
            showCancelButton: true,
            confirmButtonText: 'Tetap Masuk',
            cancelButtonText: 'Keluar',
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#52525b',
            reverseButtons: true,
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                const updateCountdown = () => {
                    const node = window.Swal.getHtmlContainer()?.querySelector('[data-idle-countdown]');

                    if (node) {
                        node.textContent = formatRemaining(remaining());
                    }
                };

                updateCountdown();
                warningInterval = window.setInterval(updateCountdown, 500);
            },
        }).then((result) => {
            if (finished) {
                return;
            }

            // Ditutup karena tab lain aktif: jangan logout.
            if (syncingTab) {
                syncingTab = false;

                return;
            }

            if (result.isConfirmed) {
                extend();

                return;
            }

            logout();
        });
    };

    /**
     * Catat aktivitas pengguna dan perpanjang sesi bila perlu.
     *
     * Saat peringatan terbuka, aktivitas diabaikan agar hitungan mundur yang
     * terlihat tidak bertentangan dengan keputusan pengguna.
     */
    const onActivity = () => {
        if (finished || warningOpen) {
            return;
        }

        const now = Date.now();

        lastActivity = now;

        // Tulis localStorage maksimal sekali per detik (mousemove sangat sering).
        if (now - lastStored >= 1000) {
            lastStored = now;
            rememberActivity(now);
        }

        if (now - lastPing >= PING_INTERVAL) {
            ping();
        }
    };

    const tick = () => {
        if (finished) {
            return;
        }

        const left = remaining();

        if (left <= 0) {
            logout();

            return;
        }

        if (!warningOpen && left <= warning) {
            showWarning();
        }
    };

    ACTIVITY_EVENTS.forEach((name) => {
        document.addEventListener(name, onActivity, { passive: true, capture: true });
    });

    // Aktivitas pada tab lain ikut memperpanjang sesi di tab ini.
    window.addEventListener('storage', (event) => {
        if (event.key !== STORAGE_KEY || finished) {
            return;
        }

        const timestamp = Number(event.newValue) || 0;

        if (timestamp > lastActivity) {
            lastActivity = timestamp;

            if (warningOpen) {
                syncingTab = true;
                stopWarning();
                window.Swal?.close();
            }
        }
    });

    // Kembali dari tab lain / perangkat tidur: periksa sisa waktu segera.
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState !== 'visible' || finished) {
            return;
        }

        if (remaining() <= 0) {
            logout();

            return;
        }

        if (!warningOpen && remaining() <= warning) {
            showWarning();
        }
    });

    rememberActivity(lastActivity);
    ticker = window.setInterval(tick, TICK_INTERVAL);
}
