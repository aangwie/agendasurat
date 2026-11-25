<a href="{{ route('surat-masuk.cetak', $letter) }}" target="_blank" rel="noopener"
    title="Cetak surat" aria-label="Cetak surat {{ $letter->agenda_number }}"
    class="grid size-8 place-items-center rounded-lg text-zinc-500 transition hover:bg-zinc-900 hover:text-white">
    <x-icon name="printer" class="size-4" />
</a>

@if ($canManage)
    <button type="button" data-letter-disposisi="{{ $letter->id }}"
        data-disposisi-url="{{ route('surat-masuk.disposisi', $letter) }}"
        title="Disposisi surat" aria-label="Disposisi surat {{ $letter->agenda_number }}"
        class="grid size-8 place-items-center rounded-lg text-zinc-500 transition hover:bg-emerald-600 hover:text-white">
        <x-icon name="share" class="size-4" />
    </button>

    <button type="button" data-letter-edit="{{ $letter->id }}"
        data-letter-payload="{{ json_encode([
            'id' => $letter->id,
            'agenda_number' => $letter->agenda_number,
            'received_date' => $letter->received_date?->format('Y-m-d'),
            'letter_date' => $letter->letter_date?->format('Y-m-d'),
            'letter_number' => $letter->letter_number,
            'sender' => $letter->sender,
            'subject' => $letter->subject,
            'sifat' => $letter->sifat ?? [],
            'update_url' => route('surat-masuk.update', $letter),
        ]) }}"
        title="Edit surat" aria-label="Edit surat {{ $letter->agenda_number }}"
        class="grid size-8 place-items-center rounded-lg text-zinc-500 transition hover:bg-amber-500 hover:text-white">
        <x-icon name="pencil-square" class="size-4" />
    </button>

    <form method="POST" action="{{ route('surat-masuk.destroy', $letter) }}" data-confirm-delete
        data-confirm-title="Hapus surat masuk?"
        data-confirm-text="Surat {{ $letter->agenda_number }} dari {{ $letter->sender }} akan dihapus permanen dan tidak dapat dikembalikan."
        class="inline-flex">
        @csrf
        @method('DELETE')

        <button type="submit" title="Hapus surat" aria-label="Hapus surat {{ $letter->agenda_number }}"
            class="grid size-8 place-items-center rounded-lg text-zinc-500 transition hover:bg-rose-600 hover:text-white">
            <x-icon name="trash" class="size-4" />
        </button>
    </form>
@else
    <span title="Hanya Subbagian Umum dan Kepegawaian atau admin yang dapat mengubah surat masuk"
        class="grid size-8 cursor-not-allowed place-items-center rounded-lg text-zinc-300">
        <x-icon name="lock" class="size-4" />
    </span>
@endif
