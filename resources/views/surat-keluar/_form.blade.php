@php
    $initialYear = (int) old('year', now()->year);
    $initialMonth = (int) old('month', now()->month);

    if ($initialMonth < 1 || $initialMonth > 12) {
        $initialMonth = (int) now()->month;
    }

    $initialCodes = \App\Models\OutgoingLetter::codesForMonth($initialMonth);
    $initialCode = (string) old('code', $initialCodes[0]);
    $initialSubCode = (string) old('sub_code', '01');

    $letterDateValue = old('letter_date', now()->format('Y-m-d'));

    $letterDatePreview = rescue(
        fn () => \Illuminate\Support\Carbon::parse($letterDateValue)->locale('id')->translatedFormat('j F Y'),
        '—',
        false,
    );

    // Administrator memilih bidang pemilik surat dari akun bidang yang memiliki
    // kode bidang; pengguna biasa memakai bidang akunnya sendiri.
    $bidangOptions = $bidangOptions ?? collect();
    $canChooseBidang = auth()->user()->isAdmin() && $bidangOptions->isNotEmpty();
    $selectedBidangUserId = (string) old('bidang_user_id', $bidangOptions->first()->id ?? '');

    $openLetterPayload = $openLetter
        ? [
            'id' => $openLetter->id,
            'year' => $openLetter->year,
            'month' => $openLetter->month,
            'month_label' => $openLetter->monthLabel(),
            'code' => $openLetter->code,
            'sub_code' => $openLetter->sub_code,
            'letter_prefix' => $openLetter->letter_prefix,
            'kode_bidang' => $openLetter->kode_bidang,
            'letter_number' => $openLetter->letter_number,
            'subject' => $openLetter->subject,
            'letter_date' => $openLetter->letter_date?->format('Y-m-d'),
            'bidang' => $openLetter->bidang,
            'update_url' => route('surat-keluar.update', $openLetter),
        ]
        : null;
@endphp

<div data-outgoing-modal class="fixed inset-0 z-[70] hidden" aria-hidden="true">
    <div data-modal-backdrop class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm"></div>

    <div class="relative flex min-h-full items-end justify-center p-4 sm:items-center">
        <div data-modal-panel role="dialog" aria-modal="true" aria-labelledby="outgoing-modal-title"
            class="max-h-[calc(100dvh-2rem)] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl shadow-zinc-950/25">
            <div class="flex items-start justify-between gap-3 border-b border-zinc-100 px-5 py-4 sm:px-6">
                <div>
                    <h2 id="outgoing-modal-title" data-modal-title class="text-base font-bold text-zinc-900">
                        Tambah Surat Keluar
                    </h2>
                    <p data-modal-description class="mt-0.5 text-xs text-zinc-500">
                        Pilih tahun, bulan, kode, dan subkode yang belum terpakai.
                    </p>
                </div>

                <button type="button" data-modal-close aria-label="Tutup formulir"
                    class="grid size-9 shrink-0 place-items-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700">
                    <x-icon name="x-mark" class="size-5" />
                </button>
            </div>

            <form data-outgoing-form method="POST" action="{{ route('surat-keluar.store') }}"
                data-store-action="{{ route('surat-keluar.store') }}"
                data-update-template="{{ route('surat-keluar.update', ['outgoingLetter' => '__ID__']) }}"
                data-open-letter="{{ json_encode($openLetterPayload) }}"
                data-kode-bidang="{{ auth()->user()->kode_bidang }}"
                data-used="{{ json_encode($usedByYear) }}">
                @csrf
                <input type="hidden" name="_method" value="" data-method-input disabled>

                <div class="space-y-5 px-5 py-5 sm:px-6">
                    {{-- Pemilihan nomor urut (tahun > bulan > kode > subkode) --}}
                    <div data-outgoing-create-fields>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div>
                                <label for="year" class="form-label">
                                    <x-icon name="calendar" class="size-4 text-zinc-400" />
                                    Tahun
                                    <span class="text-rose-500" aria-hidden="true">*</span>
                                </label>

                                <input id="year" name="year" type="number" inputmode="numeric"
                                    min="{{ \App\Models\OutgoingLetter::MIN_YEAR }}"
                                    max="{{ \App\Models\OutgoingLetter::MAX_YEAR }}" value="{{ $initialYear }}"
                                    required data-outgoing-year
                                    @error('year') aria-invalid="true" aria-describedby="year-error" @enderror
                                    @class(['form-input', 'form-input-error' => $errors->has('year')])>

                                <x-form-error field="year" />
                            </div>

                            <div>
                                <label for="month" class="form-label">
                                    <x-icon name="calendar" class="size-4 text-zinc-400" />
                                    Bulan
                                    <span class="text-rose-500" aria-hidden="true">*</span>
                                </label>

                                <select id="month" name="month" required data-outgoing-month data-searchable
                                    data-placeholder="Pilih bulan" data-search-placeholder="Cari bulan..."
                                    @error('month') aria-invalid="true" aria-describedby="month-error" @enderror
                                    @class(['form-input', 'form-input-error' => $errors->has('month')])>
                                    @foreach ($months as $number => $label)
                                        <option value="{{ $number }}" @selected($initialMonth === $number)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>

                                <x-form-error field="month" />
                            </div>

                            <div>
                                <label for="code" class="form-label">
                                    <x-icon name="folder" class="size-4 text-zinc-400" />
                                    Kode
                                    <span class="text-rose-500" aria-hidden="true">*</span>
                                </label>

                                <select id="code" name="code" required data-outgoing-code data-searchable
                                    data-placeholder="Tidak tersedia" data-search-placeholder="Cari kode..."
                                    @error('code') aria-invalid="true" aria-describedby="code-error" @enderror
                                    @class(['form-input', 'form-input-error' => $errors->has('code')])>
                                    <option value="" data-placeholder hidden>Tidak tersedia</option>

                                    @foreach ($allCodes as $code)
                                        <option value="{{ $code }}"
                                            data-month="{{ \App\Models\OutgoingLetter::monthForCode($code) }}"
                                            @selected($initialCode === $code)>
                                            {{ $code }}
                                        </option>
                                    @endforeach
                                </select>

                                <x-form-error field="code" />
                            </div>

                            <div>
                                <label for="sub_code" class="form-label">
                                    <x-icon name="document-text" class="size-4 text-zinc-400" />
                                    Subkode
                                    <span class="text-rose-500" aria-hidden="true">*</span>
                                </label>

                                <select id="sub_code" name="sub_code" required data-outgoing-sub-code data-searchable
                                    data-placeholder="Tidak tersedia" data-search-placeholder="Cari subkode..."
                                    @error('sub_code') aria-invalid="true" aria-describedby="sub_code-error" @enderror
                                    @class(['form-input', 'form-input-error' => $errors->has('sub_code')])>
                                    <option value="" data-placeholder hidden>Tidak tersedia</option>

                                    @foreach ($allSubCodes as $subCode)
                                        <option value="{{ $subCode }}" @selected($initialSubCode === $subCode)>
                                            {{ $subCode }}
                                        </option>
                                    @endforeach
                                </select>

                                <x-form-error field="sub_code" />
                            </div>
                        </div>

                        <p class="mt-2 flex items-start gap-1.5 text-xs text-zinc-400">
                            <x-icon name="lock" class="mt-px size-3.5 shrink-0" />
                            Kode dan subkode yang sudah dipakai otomatis dinonaktifkan. Setiap kode memiliki
                            subkode 01–99.
                        </p>
                    </div>

                    {{-- Ringkasan nomor pada mode edit --}}
                    <div data-outgoing-edit-fields
                        class="hidden rounded-xl border border-amber-200 bg-amber-50/60 px-4 py-3">
                        <p class="text-[11px] font-semibold tracking-wide text-amber-700 uppercase">
                            Nomor urut surat
                        </p>

                        <p class="mt-1 font-mono text-lg font-bold text-zinc-900">
                            <span data-edit-year>—</span>/<span data-edit-code>—</span>/<span data-edit-sub-code>—</span>
                        </p>

                        <p class="mt-0.5 text-xs text-zinc-600">
                            <span data-edit-month>—</span> &middot; kode bidang
                            <span data-edit-kode-bidang class="font-mono font-semibold">—</span> &middot;
                            nomor urut tidak dapat diubah saat edit.
                        </p>
                    </div>

                    {{-- Nomor surat: cukup kode depan, sisanya disusun otomatis. --}}
                    <div>
                        <label for="letter_prefix" class="form-label">
                            <x-icon name="document-text" class="size-4 text-zinc-400" />
                            Nomor Surat Keluar
                            <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>

                        <input id="letter_prefix" name="letter_prefix" type="text" maxlength="50"
                            value="{{ old('letter_prefix', $openLetter?->letter_prefix) }}"
                            placeholder="Contoh: 400.3.5" required autocomplete="off" data-outgoing-prefix
                            @error('letter_prefix') aria-invalid="true" aria-describedby="letter_prefix-error" @enderror
                            @class(['form-input', 'form-input-error' => $errors->has('letter_prefix')])>

                        <p class="mt-1.5 text-xs text-zinc-400">
                            Cukup tulis kode depan. Kode surat, kode bidang, dan tahun ditambahkan otomatis.
                        </p>

                        <x-form-error field="letter_prefix" />

                        <div class="mt-2 rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5">
                            <p class="text-[10px] font-semibold tracking-wide text-zinc-500 uppercase">
                                Nomor lengkap
                            </p>
                            <p data-outgoing-number-preview
                                class="mt-1 font-mono text-sm font-bold break-all text-zinc-900">—</p>
                        </div>
                    </div>

                    <div>
                        <label for="subject" class="form-label">
                            <x-icon name="document-text" class="size-4 text-zinc-400" />
                            Perihal
                            <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>

                        <textarea id="subject" name="subject" rows="3" required maxlength="255"
                            placeholder="Ringkasan isi surat keluar"
                            @error('subject') aria-invalid="true" aria-describedby="subject-error" @enderror
                            @class(['form-input', 'resize-y', 'form-input-error' => $errors->has('subject')])>{{ old('subject') }}</textarea>

                        <x-form-error field="subject" />
                    </div>

                    <div>
                        <label for="letter_date" class="form-label">
                            <x-icon name="calendar" class="size-4 text-zinc-400" />
                            Tanggal Surat
                            <span class="text-rose-500" aria-hidden="true">*</span>
                        </label>

                        <input id="letter_date" name="letter_date" type="date" value="{{ $letterDateValue }}"
                            required data-date-display="outgoing-letter-date-preview"
                            @error('letter_date') aria-invalid="true" aria-describedby="letter_date-error" @enderror
                            @class(['form-input', 'form-input-error' => $errors->has('letter_date')])>

                        <p class="mt-1.5 text-xs text-zinc-400">
                            Tampil sebagai
                            <span id="outgoing-letter-date-preview"
                                class="font-semibold text-zinc-700">{{ $letterDatePreview }}</span>
                        </p>

                        <p class="mt-1 text-xs text-zinc-400">
                            Boleh diisi tanggal yang akan datang bila surat baru akan diterbitkan.
                        </p>

                        <x-form-error field="letter_date" />
                    </div>

                    <div>
                        <p class="form-label">
                            <x-icon name="building-office" class="size-4 text-zinc-400" />
                            Bidang
                            @if ($canChooseBidang)
                                <span class="text-rose-500" aria-hidden="true">*</span>
                            @endif
                        </p>

                        {{-- Administrator memilih bidang pemilik surat dari akun bidang. --}}
                        @if ($canChooseBidang)
                            <div data-outgoing-bidang-picker>
                                <select id="bidang_user_id" name="bidang_user_id" required data-outgoing-bidang-select
                                    data-searchable aria-label="Bidang pemilik surat" data-placeholder="Pilih bidang"
                                    data-search-placeholder="Cari bidang..."
                                    @error('bidang_user_id') aria-invalid="true" aria-describedby="bidang_user_id-error" @enderror
                                    @class(['form-input', 'form-input-error' => $errors->has('bidang_user_id')])>
                                    @foreach ($bidangOptions as $option)
                                        <option value="{{ $option->id }}" data-bidang-name="{{ $option->name }}"
                                            data-kode-bidang="{{ $option->kode_bidang }}"
                                            @selected($selectedBidangUserId === (string) $option->id)>
                                            {{ $option->name }} — {{ $option->kode_bidang }}
                                        </option>
                                    @endforeach
                                </select>

                                <x-form-error field="bidang_user_id" />
                            </div>
                        @endif

                        <div data-outgoing-bidang-readonly
                            @class([
                                'flex items-center gap-2.5 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700',
                                'hidden' => $canChooseBidang,
                            ])>
                            <x-icon name="lock" class="size-4 shrink-0 text-zinc-400" />
                            <span data-outgoing-bidang class="min-w-0 font-medium">{{ auth()->user()->name }}</span>
                            <span data-outgoing-kode-bidang
                                class="ml-auto shrink-0 rounded-md bg-zinc-900 px-2 py-0.5 font-mono text-[11px] font-semibold text-white">{{ auth()->user()->kode_bidang ?: '—' }}</span>
                        </div>

                        @if ($canChooseBidang)
                            <p class="mt-1.5 text-xs text-zinc-400">
                                Bidang dan kode bidang mengikuti akun bidang yang dipilih. Saat edit, bidang tidak dapat diubah.
                            </p>
                        @else
                            <p class="mt-1.5 text-xs text-zinc-400">
                                Nama dan kode bidang otomatis diambil dari akun yang membuat surat.
                            </p>

                            @if (auth()->user()->isAdmin())
                                @if ($bidangOptions->isEmpty())
                                    <p role="alert"
                                        class="mt-2 flex items-start gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">
                                        <x-icon name="x-mark" class="mt-px size-3.5 shrink-0" />
                                        Belum ada akun bidang yang memiliki kode bidang. Lengkapi kode bidang pada menu
                                        Pengguna agar bidang surat dapat dipilih.
                                    </p>
                                @endif
                            @elseif (! auth()->user()->kode_bidang)
                                <p role="alert"
                                    class="mt-2 flex items-start gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800">
                                    <x-icon name="x-mark" class="mt-px size-3.5 shrink-0" />
                                    Akun Anda belum memiliki kode bidang. Hubungi administrator agar nomor surat dapat disusun.
                                </p>
                            @endif
                        @endif
                    </div>
                </div>

                <div
                    class="flex flex-col-reverse gap-2 border-t border-zinc-100 bg-zinc-50/70 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                    <button type="button" data-modal-close class="btn btn-secondary">
                        Batal
                    </button>

                    <button type="submit" data-modal-submit class="btn btn-primary">
                        <x-icon name="check-circle" class="size-4" />
                        Simpan Surat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
