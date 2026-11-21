@props(['field'])

@error($field)
    <p id="{{ $field }}-error" role="alert"
        class="mt-2 flex items-start gap-1.5 text-xs font-medium text-rose-600">
        <x-icon name="x-mark" class="mt-px size-3.5 shrink-0" />
        {{ $message }}
    </p>
@enderror
