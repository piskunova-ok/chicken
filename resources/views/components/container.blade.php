@props([
    'width' => 'site',
])

@php
    $widths = [
        'site' => 'max-w-site',
        'narrow' => 'max-w-3xl',
        'wide' => 'max-w-90rem',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'mx-auto w-full px-5 sm:px-8 lg:px-12 '.$widths[$width]]) }}>
    {{ $slot }}
</div>
