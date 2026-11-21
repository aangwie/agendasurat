@php
    $isSelf = auth()->id() === $user->id;
@endphp

<button type="button" data-user-edit="{{ $user->id }}"
    data-user-payload="{{ json_encode([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'phone' => $user->phone,
        'update_url' => route('pengguna.update', $user),
    ]) }}"
    title="Edit pengguna" aria-label="Edit pengguna {{ $user->name }}"
    class="grid size-8 place-items-center rounded-lg text-zinc-500 transition hover:bg-amber-500 hover:text-white">
    <x-icon name="pencil-square" class="size-4" />
</button>

@if ($isSelf)
    <span title="Akun yang sedang dipakai tidak dapat dihapus"
        class="grid size-8 cursor-not-allowed place-items-center rounded-lg text-zinc-300">
        <x-icon name="trash" class="size-4" />
    </span>
@else
    <form method="POST" action="{{ route('pengguna.destroy', $user) }}" data-confirm-delete
        data-confirm-title="Hapus pengguna?"
        data-confirm-text="Akun {{ $user->name }} ({{ $user->email }}) akan dihapus permanen."
        class="inline-flex">
        @csrf
        @method('DELETE')

        <button type="submit" title="Hapus pengguna" aria-label="Hapus pengguna {{ $user->name }}"
            class="grid size-8 place-items-center rounded-lg text-zinc-500 transition hover:bg-rose-600 hover:text-white">
            <x-icon name="trash" class="size-4" />
        </button>
    </form>
@endif
