@props(['href', 'icon', 'active' => false, 'badge' => null, 'label' => null])

@php
    // Used for the tooltip / accessible name when the sidebar is minimized.
    $linkLabel = $label ?? trim(strip_tags((string) $slot));
@endphp

<a href="{{ $href }}" data-nav-item data-label="{{ $linkLabel }}" @if ($active) aria-current="page" @endif
    @class([
        'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition duration-150',
        'bg-white/10 text-white shadow-xs ring-1 ring-white/10' => $active,
        'text-zinc-400 hover:bg-white/5 hover:text-zinc-100' => !$active,
    ])>
    @if ($active)
        <span class="absolute top-1/2 left-0 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-rose-500"
            aria-hidden="true"></span>
    @endif

    <x-icon :name="$icon"
        class="size-[18px] shrink-0 transition {{ $active ? 'text-rose-300' : 'text-zinc-500 group-hover:text-zinc-300' }}" />

    <span data-sidebar-label class="flex-1 truncate">{{ $slot }}</span>

    @if ($badge)
        <span data-sidebar-badge
            class="rounded-full bg-rose-500/15 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-rose-300">{{ $badge }}</span>
    @endif
</a>
