@props(['value'])

<label {{ $attributes->merge(['class' => 'small muted', 'style' => 'font-weight:500']) }}>
    {{ $value ?? $slot }}
</label>
