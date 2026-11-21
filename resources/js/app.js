import './bootstrap';
import Swal from 'sweetalert2';
import { initClassificationTable } from './klasifikasi-table';
import { initServerDatatable } from './datatable';
import { initIncomingLetters } from './surat-masuk';
import { initUserManagement } from './pengguna';
import { initDateDisplays } from './date-display';
import { initCsrfProtection } from './csrf';

// Dipakai juga oleh modul lain (mis. penanganan sesi berakhir di csrf.js).
window.Swal = Swal;

const onReady = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, { once: true });
    } else {
        callback();
    }
};

onReady(() => {
    /**
     * Sidebar drawer.
     * Visible permanently on large screens, slides in as an overlay below `lg`.
     */
    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const desktopQuery = window.matchMedia('(min-width: 1024px)');
    let sidebarTrigger = null;

    const isSidebarOpen = () => sidebar?.classList.contains('translate-x-0') ?? false;

    const setSidebar = (open, { restoreFocus = true } = {}) => {
        if (!sidebar) {
            return;
        }

        sidebar.classList.toggle('translate-x-0', open);
        sidebar.classList.toggle('-translate-x-full', !open);
        sidebar.setAttribute('aria-hidden', String(!open && !desktopQuery.matches));

        if (backdrop) {
            backdrop.classList.toggle('opacity-0', !open);
            backdrop.classList.toggle('pointer-events-none', !open);
        }

        document.body.classList.toggle('overflow-hidden', open && !desktopQuery.matches);

        document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
            button.setAttribute('aria-expanded', String(open));
        });

        if (open) {
            sidebar.querySelector('[data-sidebar-close]')?.focus({ preventScroll: true });
        } else if (restoreFocus) {
            sidebarTrigger?.focus({ preventScroll: true });
        }
    };

    if (sidebar) {
        if (desktopQuery.matches) {
            sidebar.setAttribute('aria-hidden', 'false');
        } else {
            sidebar.setAttribute('aria-hidden', 'true');
        }

        document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                sidebarTrigger = button;
                setSidebar(!isSidebarOpen());
            });
        });

        document.querySelectorAll('[data-sidebar-close]').forEach((button) => {
            button.addEventListener('click', () => setSidebar(false));
        });

        sidebar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                if (!desktopQuery.matches) {
                    setSidebar(false, { restoreFocus: false });
                }
            });
        });

        backdrop?.addEventListener('click', () => setSidebar(false));

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && isSidebarOpen() && !desktopQuery.matches) {
                setSidebar(false);
            }
        });

        desktopQuery.addEventListener('change', (event) => {
            if (event.matches) {
                setSidebar(false, { restoreFocus: false });
                sidebar.setAttribute('aria-hidden', 'false');
                document.body.classList.remove('overflow-hidden');
            } else {
                sidebar.classList.remove('translate-x-0');
                sidebar.classList.add('-translate-x-full');
                sidebar.setAttribute('aria-hidden', 'true');
            }
        });
    }

    /**
     * Sidebar minimize / maximize (desktop only).
     *
     * The state is mirrored on <html data-sidebar-minimized="…"> so CSS can
     * react to it, and persisted in localStorage so it survives page loads.
     */
    const root = document.documentElement;
    const sidebarStorageKey = 'agenda-surat:sidebar-minimized';
    const collapseButtons = Array.from(document.querySelectorAll('[data-sidebar-collapse]'));

    const isSidebarMinimized = () => root.getAttribute('data-sidebar-minimized') === 'true';

    const syncSidebarMinimizedUi = () => {
        const minimized = isSidebarMinimized();

        collapseButtons.forEach((button) => {
            button.setAttribute('aria-pressed', String(minimized));
            button.setAttribute('aria-label', minimized ? 'Perbesar menu' : 'Perkecil menu');
            button.setAttribute('title', minimized ? 'Perbesar menu' : 'Perkecil menu');
            button.querySelector('[data-collapse-icon]')?.classList.toggle('hidden', minimized);
            button.querySelector('[data-expand-icon]')?.classList.toggle('hidden', !minimized);
        });

        // Icon-only links need a text alternative; native tooltips handle the rest.
        document.querySelectorAll('[data-nav-item]').forEach((link) => {
            if (minimized && desktopQuery.matches) {
                link.setAttribute('title', link.dataset.label ?? '');
                link.setAttribute('aria-label', link.dataset.label ?? '');
            } else {
                link.removeAttribute('title');
                link.removeAttribute('aria-label');
            }
        });
    };

    const setSidebarMinimized = (minimized) => {
        root.setAttribute('data-sidebar-minimized', String(minimized));

        try {
            window.localStorage.setItem(sidebarStorageKey, minimized ? '1' : '0');
        } catch (error) {
            /* localStorage can be unavailable (private mode, quota, ...). */
        }

        syncSidebarMinimizedUi();
    };

    collapseButtons.forEach((button) => {
        button.addEventListener('click', () => setSidebarMinimized(!isSidebarMinimized()));
    });

    desktopQuery.addEventListener('change', syncSidebarMinimizedUi);

    syncSidebarMinimizedUi();

    /**
     * Profile dropdown (and any other `data-dropdown` instance).
     */
    const dropdowns = Array.from(document.querySelectorAll('[data-dropdown]'));

    const closeDropdown = (dropdown, { restoreFocus = false } = {}) => {
        const toggle = dropdown.querySelector('[data-dropdown-toggle]');
        const menu = dropdown.querySelector('[data-dropdown-menu]');

        menu?.classList.add('hidden');
        toggle?.setAttribute('aria-expanded', 'false');

        if (restoreFocus) {
            toggle?.focus({ preventScroll: true });
        }
    };

    const closeAllDropdowns = (options) => dropdowns.forEach((dropdown) => closeDropdown(dropdown, options));

    dropdowns.forEach((dropdown) => {
        const toggle = dropdown.querySelector('[data-dropdown-toggle]');
        const menu = dropdown.querySelector('[data-dropdown-menu]');

        toggle?.addEventListener('click', (event) => {
            event.stopPropagation();

            const willOpen = menu?.classList.contains('hidden') ?? false;

            closeAllDropdowns();

            if (willOpen) {
                menu?.classList.remove('hidden');
                toggle.setAttribute('aria-expanded', 'true');
                menu?.querySelector('a, button')?.focus({ preventScroll: true });
            }
        });

        dropdown.addEventListener('click', (event) => event.stopPropagation());
    });

    document.addEventListener('click', () => closeAllDropdowns());

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        const openDropdown = dropdowns.find(
            (dropdown) => !dropdown.querySelector('[data-dropdown-menu]')?.classList.contains('hidden'),
        );

        if (openDropdown) {
            closeDropdown(openDropdown, { restoreFocus: true });
        }
    });

    /**
     * Password visibility toggles on the login form.
     */
    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        const input = document.getElementById(button.dataset.passwordToggle ?? 'password');

        if (!input) {
            return;
        }

        button.addEventListener('click', () => {
            const reveal = input.type === 'password';

            input.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(reveal));
            button.setAttribute('aria-label', reveal ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
            button.querySelector('[data-icon-show]')?.classList.toggle('hidden', reveal);
            button.querySelector('[data-icon-hide]')?.classList.toggle('hidden', !reveal);
        });
    });

    /**
     * Instant preview for image uploads (e.g. the government logo).
     */
    document.querySelectorAll('[data-image-preview]').forEach((input) => {
        const preview = document.getElementById(input.dataset.imagePreview ?? '');
        const placeholder = document.getElementById(input.dataset.imagePlaceholder ?? '');
        const filename = document.getElementById(input.dataset.imageFilename ?? '');
        let objectUrl = null;

        if (!preview) {
            return;
        }

        input.addEventListener('change', () => {
            const file = input.files?.[0];

            if (!file || !file.type.startsWith('image/')) {
                return;
            }

            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
            }

            objectUrl = URL.createObjectURL(file);

            preview.src = objectUrl;
            preview.classList.remove('hidden');
            placeholder?.classList.add('hidden');

            if (filename) {
                filename.textContent = `${file.name} · ${Math.max(1, Math.round(file.size / 1024))} KB`;
            }
        });
    });

    /**
     * Auto dismiss flash toasts.
     */
    document.querySelectorAll('[data-toast]').forEach((toast) => {
        let timeout;

        const dismiss = () => {
            window.clearTimeout(timeout);
            toast.classList.add('opacity-0', 'translate-y-1');
            window.setTimeout(() => toast.remove(), 250);
        };

        const startTimer = () => {
            timeout = window.setTimeout(dismiss, 6000);
        };

        toast.querySelector('[data-toast-close]')?.addEventListener('click', dismiss);
        toast.addEventListener('mouseenter', () => window.clearTimeout(timeout));
        toast.addEventListener('mouseleave', startTimer);

        startTimer();
    });

    /**
     * Proteksi CSRF: segarkan token & jaga sesi tetap aktif agar tidak 419.
     */
    initCsrfProtection();

    /**
     * Datatable klasifikasi (pencarian, slice data, pagination via AJAX).
     */
    initClassificationTable();

    /**
     * Datatable surat masuk + modal tambah/edit.
     */
    initServerDatatable();
    initIncomingLetters();
    initUserManagement();
    initDateDisplays();

    /**
     * Konfirmasi hapus memakai SweetAlert2.
     */
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement)
            || !form.matches('[data-confirm-delete]')
            || form.dataset.confirmed === 'true') {
            return;
        }

        event.preventDefault();

        Swal.fire({
            title: form.dataset.confirmTitle ?? 'Hapus data ini?',
            text: form.dataset.confirmText ?? 'Data yang dihapus tidak dapat dikembalikan.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Ya, hapus',
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
        }).then((result) => {
            if (result.isConfirmed) {
                form.dataset.confirmed = 'true';
                form.submit();
            }
        });
    });
});
