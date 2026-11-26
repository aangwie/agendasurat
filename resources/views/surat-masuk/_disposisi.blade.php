{{-- Modal disposisi surat masuk: ceklist bidang + lampiran surat --}}
<div data-disposisi-modal class="fixed inset-0 z-[75] hidden" aria-hidden="true">
    <div data-disposisi-backdrop class="absolute inset-0 bg-zinc-950/60 backdrop-blur-sm"></div>

    <div class="relative flex min-h-full items-end justify-center p-4 sm:items-center">
        <div data-disposisi-panel role="dialog" aria-modal="true" aria-labelledby="disposisi-modal-title"
            class="max-h-[calc(100dvh-2rem)] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl shadow-zinc-950/25">
            <div class="flex items-start justify-between gap-3 border-b border-zinc-100 px-5 py-4 sm:px-6">
                <div class="flex items-start gap-3">
                    <span
                        class="grid size-10 shrink-0 place-items-center rounded-xl bg-rose-50 text-rose-600 ring-1 ring-rose-100">
                        <x-icon name="share" class="size-5" />
                    </span>

                    <div>
                        <h2 id="disposisi-modal-title" class="text-base font-bold text-zinc-900">
                            Disposisi Surat Masuk
                        </h2>
                        <p data-disposisi-subtitle class="mt-0.5 text-xs text-zinc-500">
                            Pilih bidang yang akan menerima disposisi.
                        </p>
                    </div>
                </div>

                <button type="button" data-disposisi-close aria-label="Tutup disposisi"
                    class="grid size-9 shrink-0 place-items-center rounded-lg text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700">
                    <x-icon name="x-mark" class="size-5" />
                </button>
            </div>

            <form data-disposisi-form>
                <div class="space-y-4 px-5 py-5 sm:px-6">
                    {{-- Ringkasan surat --}}
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 px-4 py-3">
                        <dl class="grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                            <div>
                                <dt class="text-zinc-500">Nomor Agenda</dt>
                                <dd data-disposisi-agenda class="font-mono font-semibold text-zinc-900">&mdash;</dd>
                            </div>

                            <div>
                                <dt class="text-zinc-500">Nomor Surat</dt>
                                <dd data-disposisi-number class="font-mono font-semibold text-zinc-900">&mdash;</dd>
                            </div>

                            <div class="sm:col-span-2">
                                <dt class="text-zinc-500">Perihal</dt>
                                <dd data-disposisi-subject class="font-semibold text-zinc-900">&mdash;</dd>
                            </div>

                            <div>
                                <dt class="text-zinc-500">Asal Surat</dt>
                                <dd data-disposisi-sender class="text-zinc-700">&mdash;</dd>
                            </div>

                            <div>
                                <dt class="text-zinc-500">Tanggal Surat</dt>
                                <dd data-disposisi-date class="text-zinc-700">&mdash;</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Lampiran surat: diunggah di sini, bukan pada form surat --}}
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-[10px] font-semibold tracking-wide text-zinc-400 uppercase">Lampiran Surat</p>
                            <span class="text-[11px] text-zinc-400">PDF / gambar, maks. 10 MB</span>
                        </div>

                        <div data-disposisi-attachment
                            class="mt-2 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 px-3 py-2.5">
                            <span class="flex min-w-0 items-center gap-2.5">
                                <span
                                    class="grid size-9 shrink-0 place-items-center rounded-lg bg-zinc-900 text-white">
                                    <x-icon name="paper-clip" class="size-4" />
                                </span>

                                <span class="min-w-0">
                                    <span data-disposisi-attachment-name
                                        class="block truncate text-xs font-semibold text-zinc-700">
                                        Belum ada lampiran
                                    </span>
                                    <span data-disposisi-attachment-meta class="block text-[11px] text-zinc-400">
                                        Unggah lampiran di bawah ini agar penerima disposisi dapat mengunduhnya.
                                    </span>
                                </span>
                            </span>

                            <span class="flex shrink-0 items-center gap-1">
                                <a data-disposisi-attachment-link href="#" target="_blank" rel="noopener"
                                    class="btn btn-secondary hidden py-1.5 text-xs">
                                    <x-icon name="arrow-down-tray" class="size-3.5" />
                                    Unduh
                                </a>

                                <button type="button" data-disposisi-attachment-delete
                                    class="btn hidden border border-rose-200 bg-white py-1.5 text-xs text-rose-700 hover:bg-rose-50"
                                    title="Hapus lampiran">
                                    <x-icon name="trash" class="size-3.5" />
                                </button>
                            </span>
                        </div>

                        <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                            <input type="file" data-disposisi-attachment-input
                                accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"
                                class="form-input py-2 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">

                            <button type="button" data-disposisi-attachment-upload
                                class="btn btn-secondary shrink-0 py-2 text-xs">
                                <x-icon name="arrow-up-tray" class="size-3.5" />
                                Unggah Lampiran
                            </button>
                        </div>

                        {{-- Pratinjau lampiran: gambar tampil langsung, PDF lewat iframe --}}
                        <div data-disposisi-attachment-preview
                            class="mt-2 hidden overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50">
                            <div class="flex items-center justify-between gap-2 border-b border-zinc-200 bg-white px-3 py-1.5">
                                <p class="text-[10px] font-semibold tracking-wide text-zinc-400 uppercase">
                                    Pratinjau Lampiran
                                </p>

                                <a data-disposisi-attachment-open href="#" target="_blank" rel="noopener"
                                    class="hidden text-[11px] font-semibold text-rose-600 underline underline-offset-2 hover:text-rose-700">
                                    Buka di tab baru
                                </a>
                            </div>

                            <img data-disposisi-attachment-image alt="Pratinjau lampiran surat"
                                class="hidden max-h-80 w-full bg-white object-contain">

                            <iframe data-disposisi-attachment-frame title="Pratinjau lampiran PDF"
                                class="hidden h-80 w-full bg-white"></iframe>

                            <p data-disposisi-attachment-fallback
                                class="hidden px-3 py-3 text-[11px] text-zinc-500">
                                Pratinjau tidak tersedia untuk jenis berkas ini. Gunakan tombol Unduh.
                            </p>
                        </div>
                    </div>

                    {{-- Ceklist bidang --}}
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-[10px] font-semibold tracking-wide text-zinc-400 uppercase">
                                Bidang Tujuan Disposisi
                            </p>

                            <div class="flex items-center gap-2 text-[11px]">
                                <button type="button" data-disposisi-select-all
                                    class="font-semibold text-rose-600 underline underline-offset-2 hover:text-rose-700">
                                    Pilih semua
                                </button>
                                <span class="text-zinc-300" aria-hidden="true">|</span>
                                <button type="button" data-disposisi-clear-all
                                    class="font-semibold text-zinc-500 underline underline-offset-2 hover:text-zinc-700">
                                    Kosongkan
                                </button>
                            </div>
                        </div>

                        <div data-disposisi-list class="scrollbar-slim mt-2 max-h-64 space-y-1.5 overflow-y-auto pr-1">
                            <p class="rounded-xl bg-zinc-50 px-3 py-2.5 text-xs text-zinc-500">
                                Memuat daftar bidang...
                            </p>
                        </div>

                        <p class="mt-2 text-[11px] leading-relaxed text-zinc-400">
                            Bidang yang dicentang akan menerima notifikasi WhatsApp berisi ringkasan surat dan
                            tautan unduhannya. Bidang tanpa nomor WhatsApp otomatis dilewati.
                        </p>
                    </div>

                    {{-- Batalkan disposisi yang salah tujuan --}}
                    <div data-disposisi-cancel-section
                        class="hidden rounded-xl border border-rose-200 bg-rose-50/50 px-3.5 py-3">
                        <div class="flex items-start gap-2.5">
                            <span
                                class="grid size-8 shrink-0 place-items-center rounded-lg bg-white text-rose-600 ring-1 ring-rose-200">
                                <x-icon name="x-mark" class="size-4" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-bold text-rose-800">Batalkan Disposisi yang Salah</p>
                                <p class="mt-0.5 text-[11px] leading-relaxed text-rose-700/80">
                                    Salah kirim bidang? Batalkan disposisi di bawah, lalu disposisikan ulang ke
                                    bidang yang benar.
                                </p>
                            </div>
                        </div>

                        <div data-disposisi-cancel-list class="mt-2.5 space-y-1.5"></div>

                        <div class="mt-2.5 space-y-2">
                            <input type="text" data-disposisi-cancel-reason maxlength="255"
                                placeholder="Alasan pembatalan (opsional), mis. salah bidang tujuan"
                                class="form-input py-2 text-xs">

                            <label class="flex cursor-pointer items-start gap-2 text-[11px] leading-relaxed text-rose-800">
                                <input type="checkbox" data-disposisi-cancel-notify checked
                                    class="mt-0.5 size-3.5 shrink-0 rounded border-rose-300 accent-rose-600">
                                <span>Kirim pemberitahuan pembatalan via WhatsApp ke bidang tersebut.</span>
                            </label>
                        </div>
                    </div>

                    {{-- Hasil pengiriman --}}
                    <div data-disposisi-result class="hidden rounded-xl border px-4 py-3" role="status"
                        aria-live="polite">
                        <p data-disposisi-result-title class="flex items-center gap-2 text-sm font-semibold"></p>
                        <ul data-disposisi-result-list class="mt-1.5 space-y-1 text-[11px]"></ul>
                    </div>
                </div>

                <div
                    class="flex flex-col-reverse gap-2 border-t border-zinc-100 bg-zinc-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <span data-disposisi-count class="text-[11px] font-medium text-zinc-500">
                        Belum ada bidang dipilih
                    </span>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row">
                        <button type="button" data-disposisi-close class="btn btn-secondary">
                            Tutup
                        </button>

                        <button type="submit" data-disposisi-submit class="btn btn-primary">
                            <x-icon name="whatsapp" class="size-4" />
                            Kirim Disposisi
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
