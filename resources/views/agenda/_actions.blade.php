<button type="button" data-agenda-edit="{{ $agenda->id }}"
    data-agenda-payload="{{ json_encode([
        'id' => $agenda->id,
        'date' => $agenda->date?->format('Y-m-d'),
        'place' => $agenda->place,
        'person_in_charge' => $agenda->person_in_charge,
        'participants' => $agenda->participants,
        'update_url' => route('agenda.update', $agenda),
    ]) }}"
    title="Edit agenda" aria-label="Edit agenda {{ $agenda->date?->format('d/m/Y') }}"
    class="grid size-8 place-items-center rounded-lg text-zinc-500 transition hover:bg-amber-500 hover:text-white">
    <x-icon name="pencil-square" class="size-4" />
</button>

<form method="POST" action="{{ route('agenda.destroy', $agenda) }}" data-confirm-delete
    data-confirm-title="Hapus agenda?"
    data-confirm-text="Agenda {{ $agenda->date?->locale('id')->translatedFormat('j F Y') }} di {{ $agenda->place }} akan dihapus permanen."
    class="inline-flex">
    @csrf
    @method('DELETE')

    <button type="submit" title="Hapus agenda" aria-label="Hapus agenda {{ $agenda->date?->format('d/m/Y') }}"
        class="grid size-8 place-items-center rounded-lg text-zinc-500 transition hover:bg-rose-600 hover:text-white">
        <x-icon name="trash" class="size-4" />
    </button>
</form>
