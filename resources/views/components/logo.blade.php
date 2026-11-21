@props(['tone' => 'light', 'subtitle' => true, 'institution' => null])

@php
    // Kop & logo bersumber dari menu Profil Instansi.
    $institution = $institution ?? \App\Models\InstitutionProfile::query()->oldest('id')->first();
    $logoUrl = $institution?->logo_url;
    $title = $institution?->display_name ?? config('app.name', 'Agenda Surat');
    $subtitleText = $institution?->display_government ?? 'Tata Usaha Digital';
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    @if ($logoUrl)
        <span
            class="grid size-10 shrink-0 place-items-center overflow-hidden rounded-xl bg-white p-1 shadow-lg shadow-zinc-900/20 ring-1 ring-white/20">
            <img src="{{ $logoUrl }}" alt="Logo {{ $title }}" class="size-full object-contain">
        </span>
    @else
        <span
            class="grid size-10 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-rose-500 to-rose-700 text-white shadow-lg shadow-rose-600/25">
            <x-icon name="mail" class="size-5" />
        </span>
    @endif

    <span data-logo-text class="flex min-w-0 flex-col leading-tight">
        <span @class([
            'truncate text-[15px] font-bold tracking-tight',
            'text-white' => $tone === 'light',
            'text-zinc-900' => $tone !== 'light',
        ]) title="{{ $title }}">{{ $title }}</span>

        @if ($subtitle)
            <span @class([
                'truncate text-[10px] font-semibold tracking-[0.18em] uppercase',
                'text-zinc-500' => $tone === 'light',
                'text-zinc-400' => $tone !== 'light',
            ]) title="{{ $subtitleText }}">{{ $subtitleText }}</span>
        @endif
    </span>
</div>
