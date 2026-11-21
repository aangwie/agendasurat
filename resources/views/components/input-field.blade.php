@props([
    'name',
    'label',
    'icon' => null,
    'type' => 'text',
    'value' => null,
    'placeholder' => null,
    'hint' => null,
    'required' => true,
])

<div>
    <label for="{{ $name }}" class="form-label">
        @if ($icon)
            <x-icon :name="$icon" class="size-4 text-zinc-400" />
        @endif

        {{ $label }}

        @if ($required)
            <span class="text-rose-500" aria-hidden="true">*</span>
        @endif
    </label>

    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}"
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($required) required @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        {{ $attributes->class(['form-input', 'form-input-error' => $errors->has($name)]) }}>

    @if ($hint)
        <p class="mt-1.5 text-xs text-zinc-400">{{ $hint }}</p>
    @endif

    <x-form-error :field="$name" />
</div>
