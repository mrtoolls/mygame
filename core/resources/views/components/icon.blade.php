@props(['name', 'class' => ''])
@php
    $attrs = 'class="nocturne-icon ' . $class . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
@endphp
@switch($name)
    @case('mail')
        <svg xmlns="http://www.w3.org/2000/svg" {!! $attrs !!}>
            <rect x="2.5" y="5" width="19" height="14" rx="3"/>
            <path d="M3.5 7l7.65 6.12a1.5 1.5 0 0 0 1.8 0L20.5 7"/>
        </svg>
        @break
    @case('lock')
        <svg xmlns="http://www.w3.org/2000/svg" {!! $attrs !!}>
            <rect x="4" y="11" width="16" height="10" rx="2.5"/>
            <path d="M7.5 11V7.5a4.5 4.5 0 0 1 9 0V11"/>
            <circle cx="12" cy="15.75" r="1.4" fill="currentColor" stroke="none"/>
        </svg>
        @break
    @case('eye')
        <svg xmlns="http://www.w3.org/2000/svg" {!! $attrs !!}>
            <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/>
            <circle cx="12" cy="12" r="3.25"/>
        </svg>
        @break
    @case('eye-off')
        <svg xmlns="http://www.w3.org/2000/svg" {!! $attrs !!}>
            <path d="M3.5 3.5l17 17"/>
            <path d="M10.6 5.3A10.6 10.6 0 0 1 12 5c6.4 0 10 7 10 7a16.3 16.3 0 0 1-3.6 4.5M6.6 6.6C4 8.3 2 12 2 12s3.6 7 10 7a9.8 9.8 0 0 0 4-.85"/>
            <path d="M9.9 10.1a3.25 3.25 0 0 0 4.1 4.1"/>
        </svg>
        @break
    @case('gift')
        <svg xmlns="http://www.w3.org/2000/svg" {!! $attrs !!}>
            <rect x="3" y="9.5" width="18" height="4" rx="1"/>
            <rect x="4.5" y="13.5" width="15" height="7" rx="1.5"/>
            <path d="M12 9.5V21"/>
            <path d="M12 9.5C9.8 9.5 8.2 8.6 8.2 6.9S9.5 4 11 4.5c1.4.45 1 3.1 1 5"/>
            <path d="M12 9.5c2.2 0 3.8-.9 3.8-2.6S14.5 4 13 4.5c-1.4.45-1 3.1-1 5"/>
        </svg>
        @break
    @case('shield')
        <svg xmlns="http://www.w3.org/2000/svg" {!! $attrs !!}>
            <path d="M12 3.5l7 2.6v5.2c0 4.7-3 7.8-7 9.2-4-1.4-7-4.5-7-9.2V6.1l7-2.6Z"/>
            <path d="M8.75 12.25l2.1 2.1 4.4-4.5"/>
        </svg>
        @break
    @case('sign-in')
        <svg xmlns="http://www.w3.org/2000/svg" {!! $attrs !!}>
            <path d="M13 4h4.5A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5H13"/>
            <path d="M3 12h12.5"/>
            <path d="M11.5 7.5 16 12l-4.5 4.5"/>
        </svg>
        @break
@endswitch
