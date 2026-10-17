@props(['name', 'class' => ''])
@php
    $map = [
        'mail' => 'envelope',
        'lock' => 'lock',
        'eye' => 'eye',
        'eye-off' => 'eye-closed',
        'gift' => 'gift',
        'shield' => 'shield-check',
        'sign-in' => 'sign-in',
        'user-plus' => 'user-plus',
    ];
    $phName = $map[$name] ?? $name;
@endphp
<i class="ph-bold ph-{{ $phName }} nocturne-icon {{ $class }}" aria-hidden="true"></i>
