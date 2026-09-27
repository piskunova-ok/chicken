@props([
    // canvas | surface | dark
    'tone' => 'canvas',
    // default | tight | loose | none
    'spacing' => 'default',
    'id' => null,
])

@php
    $tones = [
        'canvas' => 'bg-canvas',
        'surface' => 'bg-surface',
        'dark' => 'bg-primary-dark',
    ];

    $spacings = [
        'tight' => 'py-12 lg:py-16',
        'default' => 'py-16 lg:py-24',
        'loose' => 'py-20 lg:py-28',
        'none' => '',
    ];
@endphp

<section
    @if ($id) id="{{ $id }}" @endif
    {{ $attributes->merge(['class' => ($tones[$tone] ?? $tones['canvas']).' '.($spacings[$spacing] ?? $spacings['default'])]) }}
>
    <x-container>
        {{ $slot }}
    </x-container>
</section>
