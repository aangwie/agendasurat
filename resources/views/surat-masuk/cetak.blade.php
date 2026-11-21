<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Cetak {{ $letter->agenda_number }} · {{ config('app.name', 'Agenda Surat') }}</title>

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    @vite(['resources/css/app.css'])

    <style>
        /*
         * Lembar disposisi dicetak pada kertas A4 portrait, dengan area isi
         * setinggi 1/2 A4 (210 x 148 mm) di bagian atas kertas.
         */
        @page {
            size: A4 portrait;
            margin: 5mm 8mm;
        }

        .sheet {
            width: 194mm;
            min-height: 148mm;
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            background: #fff;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .disposisi {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .disposisi th,
        .disposisi td {
            border: 0.6pt solid #000;
            padding: 0.9mm 1.6mm;
            font-size: 8pt;
            line-height: 1.2;
            vertical-align: top;
            text-align: left;
            font-weight: normal;
        }

        .disposisi .field-label {
            white-space: nowrap;
            width: 26mm;
        }

        .disposisi .cell-center {
            text-align: center;
        }

        /*
         * Garis tegak di sekitar tanda ":" dihilangkan agar titik dua tidak
         * terhimpit. Garis luar tabel tetap utuh.
         */
        .disposisi .cell-colon {
            border-left: 0;
            border-right: 0;
            padding: 0.9mm 0.9mm;
            text-align: center;
            white-space: nowrap;
        }

        /*
         * Titik dua pada kolom kanan (Diterima Tanggal & Nomor Agenda)
         * digeser lebih ke kanan. `position: relative; left` menggeser
         * tampilan tanpa mengubah lebar kolom 2mm (aman untuk cetak).
         */
        .disposisi .cell-colon-right {
            padding-left: 1.5mm;
            padding-right: 0.3mm;
            position: relative;
            left: 3mm;
        }

        .disposisi .cell-label {
            border-right: 0;
            font-size:9pt;
        }

        .disposisi .cell-value {
            border-left: 0;
            font-size: 9pt;
        }

        .checkbox {
            display: inline-block;
            width: 3.6mm;
            height: 3.6mm;
            border: 0.6pt solid #000;
            vertical-align: -0.7mm;
            text-align: center;
            line-height: 3.2mm;
            font-size: 7pt;
            font-weight: bold;
        }

        .checkbox.is-checked::after {
            content: '\2713';
        }

        .kop-divider {
            margin-top: 1mm;
            border-bottom: 2.5pt double #000;
        }

        .tujuan-row {
            display: flex;
            align-items: flex-start;
            gap: 1mm;
            line-height: 1.3;
            font-size: 9pt;
        }

        .tujuan-no {
            width: 4mm;
            flex: none;
        }

        .tujuan-name {
            flex: 1;
        }

        .tindakan-row {
            display: flex;
            align-items: flex-start;
            gap: 1.5mm;
            margin-bottom: 1.6mm;
            font-size: 9pt;
        }

        .catatan-area {
            /* Area catatan diperpanjang agar lega untuk tulisan tangan.
               Tinggi total lembar tetap < area cetak A4 (±285mm). */
            min-height: 170mm;
            height: 2cm;
            font-size: 9pt;
        }

        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: #fff !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .sheet {
                width: 100% !important;
                min-height: 0 !important;
                box-shadow: none !important;
                border: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>

<body class="min-h-dvh bg-zinc-200 py-6 print:bg-white print:py-0">
    {{-- Toolbar (tidak tercetak) --}}
    <div class="no-print mx-auto mb-4 flex w-full max-w-[210mm] flex-wrap items-center justify-between gap-2 px-2">
        <a href="{{ route('surat-masuk.index') }}"
            class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-xs font-semibold text-zinc-600 shadow-xs transition hover:bg-zinc-50">
            <x-icon name="arrow-right" class="size-4 rotate-180" />
            Kembali
        </a>

        <div class="flex items-center gap-2">
            <span class="hidden text-xs text-zinc-500 sm:inline">
                Pratinjau A4 &middot; area cetak &frac12; A4 (210 &times; 148 mm)
            </span>

            <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm shadow-rose-600/25 transition hover:bg-rose-500">
                <x-icon name="printer" class="size-4" />
                Cetak
            </button>
        </div>
    </div>

    <main class="sheet mx-auto bg-white px-0 py-0 shadow-xl">
        {{-- Kop instansi: logo, nama pemerintahan, nama instansi, alamat,
             website, dan email bersumber dari menu Profil Instansi. --}}
        <header class="flex items-center gap-3 px-1">
            @if ($profile->logo_url)
                <img src="{{ $profile->logo_url }}" alt="Logo {{ $profile->display_name }}"
                    class="h-[17mm] w-[17mm] shrink-0 object-contain">
            @else
                <span
                    class="grid h-[17mm] w-[17mm] shrink-0 place-items-center rounded-full border border-dashed border-zinc-400 text-[6pt] leading-tight text-zinc-500">
                    LOGO
                </span>
            @endif

            <div class="min-w-0 flex-1 text-center leading-tight">
                <p class="text-[14pt] font-semibold tracking-wide uppercase">
                    {{ $profile->display_government }}
                </p>
                <p class="text-[16pt] font-bold tracking-wide uppercase">
                    {{ $profile->display_name }}
                </p>
                <p class="mt-0.5 text-[12pt]">
                    {{ $profile->address ?: 'Alamat instansi belum diatur pada menu Profil Instansi' }}
                </p>
                <p class="text-[12pt]">
                    @if ($profile->website)
                        <span style="color:blue">www.{{ $profile->website }}</span>
                    @endif
                    @if ($profile->website && $profile->email)
                        &nbsp;
                    @endif
                    @if ($profile->email)
                        {{ $profile->email }}
                    @endif
                </p>
            </div>
        </header>

        <div class="kop-divider"></div>

        {{-- Lembar disposisi: kertas putih, A4 dengan tinggi 1/2 A4 (A5 landscape) --}}
        <table class="disposisi mt-[1.5mm]">
            {{-- Lebar kolom: garis pemisah kiri/kanan digeser ke tengah (±55%),
                 sehingga kolom "Diterima Tanggal" lebih dekat ke tengah lembar. --}}
            <colgroup>
                <col style="width: 24mm">
                <col style="width: 2mm">
                <col style="width: 80mm">
                <col style="width: 27mm">
                <col style="width: 2mm">
                <col style="width: 59mm">
            </colgroup>

            <tbody>
                <tr>
                    <td colspan="6" class="cell-center"
                        style="font-size: 9.5pt; font-weight: bold; letter-spacing: 0.08em;">
                        LEMBAR DISPOSISI
                    </td>
                </tr>

                <tr>
                    <td class="cell-label">Surat Dari</td>
                    <td class="cell-colon">:</td>
                    <td class="cell-value">{{ $letter->sender }}</td>
                    <td class="cell-label">Diterima Tanggal</td>
                    <td class="cell-colon cell-colon-right">:</td>
                    <td class="cell-value">{{ $letter->received_date?->locale('id')->translatedFormat('j F Y') }}</td>
                </tr>

                <tr>
                    <td class="cell-label">Nomor Surat</td>
                    <td class="cell-colon">:</td>
                    <td class="cell-value">{{ $letter->letter_number }}</td>
                    <td class="cell-label">Nomor Agenda</td>
                    <td class="cell-colon cell-colon-right">:</td>
                    <td class="cell-value">{{ $letter->agenda_number }}</td>
                </tr>

                <tr>
                    <td class="cell-label">Tanggal Surat</td>
                    <td class="cell-colon">:</td>
                    <td class="cell-value" colspan="4">{{ $letter->letter_date?->locale('id')->translatedFormat('j F Y') }}</td>
                </tr>

                <tr>
                    <td class="cell-label">Sifat</td>
                    <td class="cell-colon">:</td>
                    <td class="cell-value" colspan="4">
                        <div style="display: flex; flex-wrap: wrap; gap: 1mm 6mm;">
                            @foreach (\App\Models\IncomingLetter::SIFAT_OPTIONS as $value => $label)
                                @php
                                    $isChecked = in_array($value, $letter->sifat ?? [], true);
                                @endphp

                                <span style="white-space: nowrap;">
                                    <span @class(['checkbox', 'is-checked' => $isChecked])
                                        data-sifat="{{ $value }}"
                                        data-checked="{{ $isChecked ? 'true' : 'false' }}"></span>
                                    {{ $label }}
                                </span>
                            @endforeach
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="cell-label">Hal</td>
                    <td class="cell-colon">:</td>
                    <td class="cell-value" colspan="4" style="font-weight: bold; text-transform: uppercase;">{{ $letter->subject }}</td>
                </tr>

                <tr>
                    <td colspan="3" style="font-weight: bold;">Diteruskan Kepada</td>
                    <td colspan="3" style="font-weight: bold;">Dengan hormat harap</td>
                </tr>

                <tr>
                    <td colspan="3">
                        @foreach (config('surat.disposisi.tujuan', []) as $index => $tujuan)
                            <div class="tujuan-row">
                                <span class="tujuan-no">{{ $index + 1 }}</span>
                                <span class="checkbox"></span>
                                <span class="tujuan-name">{{ $tujuan }}</span>
                            </div>
                        @endforeach
                    </td>
                    <td colspan="3">
                        @foreach (config('surat.disposisi.tindakan', []) as $tindakan)
                            <div class="tindakan-row">
                                <span class="checkbox"></span>
                                <span>{{ $tindakan }}</span>
                            </div>
                        @endforeach
                    </td>
                </tr>

                <tr>
                    <td colspan="6" class="catatan-area" style="font-size:9pt;">Catatan :</td>
                </tr>
            </tbody>
        </table>
    </main>

    <script>
        // Buka dialog cetak otomatis setelah halaman siap.
        window.addEventListener('load', () => {
            window.setTimeout(() => window.print(), 400);
        });
    </script>
</body>

</html>
