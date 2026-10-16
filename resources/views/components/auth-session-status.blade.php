@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'banner']) }}>
        {{ $status }}
    </div>
@endif
