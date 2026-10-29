@php
    $paths = [
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>',
        'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'arrow-up' => '<path d="M7 17 17 7M7 7h10v10"/>',
        'chevron' => '<path d="m9 6 6 6-6 6"/>',
        'spark' => '<path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3m2-16a3 3 0 0 1 0 6m4 10v-3a6 6 0 0 0-3-5"/>',
        'workout' => '<path d="m6 6 12 12M4 10l6-6m4 16 6-6M2 8l6-6m8 20 6-6"/>',
        'wallet' => '<rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 9h18m-5 5h5M5 5V3h12"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18m-14 5h3m4 0h3"/>',
        'settings' => '<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2"/><circle cx="16" cy="12" r="2"/><circle cx="8" cy="18" r="2"/>',
        'phone' => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M10 18h4M10 5h4"/>',
        'book' => '<path d="M12 6C8 3 5 3 2 4v15c4-1 7-1 10 2m0-15c4-3 7-3 10-2v15c-4-1-7-1-10 2V6Z"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
    ];
@endphp
<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$icon] ?? $paths['book'] !!}</svg>
