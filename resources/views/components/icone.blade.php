@props(['nome'])
@php
    $caminhos = [
        'home' => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/>',
        'wallet' => '<rect x="3" y="6" width="18" height="14" rx="3"/><path d="M3 10h18"/><circle cx="16.5" cy="15" r="1.2"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/>',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
        'growth' => '<path d="M3 20h18"/><path d="M5 16l5-5 3 3 6-7"/>',
        'house' => '<path d="M4 21V10l8-6 8 6v11"/><path d="M10 21v-6h4v6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="M4 20h4L19 9l-4-4L4 16z"/>',
        'trash' => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'left' => '<path d="M15 6l-6 6 6 6"/>',
        'right' => '<path d="M9 6l6 6-6 6"/>',
        'check' => '<path d="M5 12l5 5 9-10"/>',
        'alert' => '<path d="M12 3l10 18H2z"/><path d="M12 10v4M12 17.5v.5"/>',
        'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'list' => '<path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
        'logout' => '<path d="M15 4h4v16h-4"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
        'theme' => '<circle cx="12" cy="12" r="8"/><path d="M12 4a8 8 0 0 0 0 16z" fill="currentColor"/>',
        'flag' => '<path d="M5 21V4"/><path d="M5 4h11l-2 4 2 4H5"/>',
        'upload' => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v4h16v-4"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14.9-3.5M4 4v4h4"/><path d="M4 13a8 8 0 0 0 14.9 3.5M20 20v-4h-4"/>',
        'logo' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
    ];
@endphp
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>{!! $caminhos[$nome] ?? '' !!}</svg>
