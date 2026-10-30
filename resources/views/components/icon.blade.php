@props(['name', 'class' => 'size-5'])

{{-- Ikony liniowe SVG (dekoracyjne: zawsze obok tekstu, dlatego aria-hidden). Bez {!! !!}. --}}
<svg {{ $attributes->merge(['class' => $class.' shrink-0']) }} xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('home')
            <path d="M3 11.5 12 4l9 7.5M5.5 10v9.5h13V10M10 19.5v-5h4v5"/>
            @break
        @case('search')
            <circle cx="11" cy="11" r="6"/><path d="m16 16 5 5"/>
            @break
        @case('calendar')
            <rect x="4" y="5" width="16" height="15" rx="2"/><path d="M4 10h16M8 3v4M16 3v4"/>
            @break
        @case('inbox')
            <path d="M4 13h4l1.5 3h5L16 13h4M4 13l2-8h12l2 8v6H4z"/>
            @break
        @case('chart')
            <path d="M5 20V11M12 20V5M19 20v-8"/>
            @break
        @case('user')
            <circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/>
            @break
        @case('list')
            <path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>
            @break
        @case('flag')
            <path d="M5 21V4h11l-1.5 4L16 12H5"/>
            @break
        @case('building')
            <path d="M5 21V4h9v17M14 9h5v12M8 8h3M8 12h3M8 16h3"/>
            @break
        @case('clipboard')
            <path d="M9 4h6v3H9zM7 5.5H5.5a1 1 0 0 0-1 1V20a1 1 0 0 0 1 1h13a1 1 0 0 0 1-1V6.5a1 1 0 0 0-1-1H17M8 12h8M8 16h5"/>
            @break
        @case('shield')
            <path d="M12 3 4.5 6v5.5c0 4.5 3.2 8 7.5 9.5 4.3-1.5 7.5-5 7.5-9.5V6z"/>
            @break
        @case('lock')
            <rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>
            @break
        @case('alert')
            <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16.5h.01"/>
            @break
        @case('check')
            <circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>
            @break
        @case('info')
            <circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>
            @break
        @case('logout')
            <path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 8l-4 4 4 4M6 12h10"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            @break
        @case('arrow-left')
            <path d="M19 12H5M11 6l-6 6 6 6"/>
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6"/>
            @break
        @case('x')
            <path d="M6 6l12 12M18 6 6 18"/>
            @break
    @endswitch
</svg>
