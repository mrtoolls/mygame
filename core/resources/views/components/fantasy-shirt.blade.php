@props(['primary' => '#1d4ed8', 'secondary' => '#ffffff', 'pattern' => 'solid', 'size' => 40])
@php
    // Drawn entirely from the club's own two colors - never a real kit image. 'solid' is the base
    // shirt shape; stripes/hoops/halves overlay a second-color pattern on top of it.
@endphp
<svg class="ff-shirt" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 40 40" aria-hidden="true">
    <defs>
        <clipPath id="ff-shirt-clip-{{ $pattern }}-{{ md5($primary . $secondary) }}">
            <path d="M12 3 L4 9 L8 15 L11 13 L11 36 L29 36 L29 13 L32 15 L36 9 L28 3 L24 5 Q20 8 16 5 Z" />
        </clipPath>
    </defs>
    <g clip-path="url(#ff-shirt-clip-{{ $pattern }}-{{ md5($primary . $secondary) }})">
        <rect x="0" y="0" width="40" height="40" fill="{{ $primary }}" />
        @if ($pattern === 'stripes')
            @for ($i = 0; $i < 5; $i++)
                <rect x="{{ $i * 8 }}" y="0" width="4" height="40" fill="{{ $secondary }}" />
            @endfor
        @elseif ($pattern === 'hoops')
            @for ($i = 0; $i < 5; $i++)
                <rect x="0" y="{{ $i * 8 }}" width="40" height="4" fill="{{ $secondary }}" />
            @endfor
        @elseif ($pattern === 'halves')
            <rect x="20" y="0" width="20" height="40" fill="{{ $secondary }}" />
        @endif
    </g>
    <path d="M12 3 L4 9 L8 15 L11 13 L11 36 L29 36 L29 13 L32 15 L36 9 L28 3 L24 5 Q20 8 16 5 Z" fill="none" stroke="rgba(0,0,0,.25)" stroke-width="1" />
</svg>
