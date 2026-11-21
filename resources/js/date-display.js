/**
 * Menampilkan tanggal dari <input type="date"> dalam format Indonesia,
 * contoh: "12 Agustus 2026".
 *
 * Elemen input memakai atribut `data-date-display="<id-target>"`.
 */
const INDONESIAN_MONTHS = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

export function formatIndonesianDate(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec((value ?? '').trim());

    if (!match) {
        return '—';
    }

    const [, year, month, day] = match;
    const monthName = INDONESIAN_MONTHS[Number(month) - 1];

    if (!monthName) {
        return '—';
    }

    return `${Number(day)} ${monthName} ${year}`;
}

export function refreshDateDisplays() {
    document.querySelectorAll('[data-date-display]').forEach((input) => {
        const output = document.getElementById(input.dataset.dateDisplay ?? '');

        if (output) {
            output.textContent = formatIndonesianDate(input.value);
        }
    });
}

export function initDateDisplays() {
    refreshDateDisplays();

    document.querySelectorAll('[data-date-display]').forEach((input) => {
        input.addEventListener('input', refreshDateDisplays);
        input.addEventListener('change', refreshDateDisplays);
    });
}
