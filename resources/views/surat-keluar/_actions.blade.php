@if ($letter->canBeManagedBy($authUser))
    <button type="button" data-outgoing-edit="{{ $letter->id }}"
        data-outgoing-payload="{{ json_encode([
            'id' => $letter->id,
            'year' => $letter->year,
            'month' => $letter->month,
            'month_label' => $letter->monthLabel(),
            'code' => $letter->code,
            'sub_code' => $letter->sub_code,
            'letter_prefix' => $letter->letter_prefix,
            'kode_bidang' => $letter->kode_bidang,
            'letter_number' => $letter->letter_number,
            'subject' => $letter->subject,
            'letter_date' => $letter->letter_date?->format('Y-m-d'),
            'bidang' => $letter->bidang,
            'update_url' => route('surat-keluar.update', $letter),
        ]) }}"
        title="Edit surat" aria-label="Edit surat {{ $letter->fullCode() }}"
        class="grid size-8 place-items-center rounded-lg text-zinc-500 transition hover:bg-amber-500 hover:text-white">
        <x-icon name="pencil-square" class="size-4" />
    </button>

    <form method="POST" action="{{ route('surat-keluar.destroy', $letter) }}" data-confirm-delete
        data-confirm-title="Hapus surat keluar?"
        data-confirm-text="Surat keluar {{ $letter->fullCode() }} milik {{ $letter->bidang }} akan dihapus permanen dan nomor tersebut dapat dipakai kembali."
        class="inline-flex">
        @csrf
        @method('DELETE')

        <button type="submit" title="Hapus surat" aria-label="Hapus surat {{ $letter->fullCode() }}"
            class="grid size-8 place-items-center rounded-lg text-zinc-500 transition hover:bg-rose-600 hover:text-white">
            <x-icon name="trash" class="size-4" />
        </button>
    </form>
@else
    <span title="Hanya admin dan pembuat surat yang dapat mengubah"
        class="grid size-8 place-items-center rounded-lg text-zinc-300">
        <x-icon name="lock" class="size-4" />
    </span>
@endif
