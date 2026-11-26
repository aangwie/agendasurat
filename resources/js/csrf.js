/**
 * Proteksi CSRF untuk mencegah "419 Page Expired".
 *
 * - Menyegarkan token dari /csrf-token secara berkala (mencegah 419 saat sesi
 *   lama dibuka) dan saat tab kembali aktif.
 * - Menyisipkan token pada setiap request fetch non-GET.
 * - Menampilkan notifikasi bila sesi benar-benar berakhir.
 *
 * Catatan: detak berkala ini hanya menyegarkan token, BUKAN aktivitas pengguna,
 * sehingga logout otomatis (idle-logout.js) tetap berjalan sesuai
 * `config('session.idle_timeout')`.
 */
const REFRESH_INTERVAL = 5 * 60 * 1000; // 5 menit

export function initCsrfProtection() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    if (!meta) {
        return;
    }

    const currentToken = () => meta.getAttribute('content') ?? '';

    const syncToken = (token) => {
        if (!token) {
            return;
        }

        meta.setAttribute('content', token);

        document.querySelectorAll('input[name="_token"]').forEach((input) => {
            input.value = token;
        });

        if (window.axios) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        }
    };

    let refreshing = null;

    const refresh = () => {
        if (refreshing) {
            return refreshing;
        }

        refreshing = fetch('/csrf-token', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store',
        })
            .then((response) => (response.ok ? response.json() : null))
            .then((data) => syncToken(data?.token))
            .catch(() => {
                /* offline / server sibuk: coba lagi pada jadwal berikutnya */
            })
            .finally(() => {
                refreshing = null;
            });

        return refreshing;
    };

    syncToken(currentToken());

    window.setInterval(() => {
        if (document.visibilityState !== 'hidden') {
            refresh();
        }
    }, REFRESH_INTERVAL);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refresh();
        }
    });

    // Tambahkan token ke semua request fetch non-GET (datatable, modal, dsb.).
    const originalFetch = window.fetch?.bind(window);

    if (originalFetch) {
        window.fetch = async (resource, options = {}) => {
            const method = (options.method ?? (resource instanceof Request ? resource.method : 'GET')).toUpperCase();

            if (method !== 'GET' && method !== 'HEAD') {
                const headers = new Headers(
                    options.headers ?? (resource instanceof Request ? resource.headers : undefined),
                );

                if (!headers.has('X-CSRF-TOKEN')) {
                    headers.set('X-CSRF-TOKEN', currentToken());
                }

                options = { ...options, headers, credentials: options.credentials ?? 'same-origin' };
            }

            const response = await originalFetch(resource, options);

            if (response.status === 419) {
                window.dispatchEvent(new CustomEvent('csrf:expired'));
            }

            return response;
        };
    }

    // Sesi benar-benar habis: beri tahu pengguna untuk memuat ulang halaman.
    window.addEventListener('csrf:expired', () => {
        if (window.__csrfExpiredNotified) {
            return;
        }

        window.__csrfExpiredNotified = true;

        window.setTimeout(() => {
            window.__csrfExpiredNotified = false;
        }, 10000);

        if (window.Swal) {
            window.Swal.fire({
                icon: 'warning',
                title: 'Sesi berakhir',
                text: 'Halaman perlu dimuat ulang untuk melanjutkan.',
                confirmButtonText: 'Muat ulang',
                confirmButtonColor: '#e11d48',
                allowOutsideClick: false,
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.reload();
                }
            });

            return;
        }

        window.location.reload();
    });
}
