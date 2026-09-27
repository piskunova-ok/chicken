@php
    $toneClasses = match ($tone) {
        'dark' => 'text-accent-light',
        default => 'text-accent',
    };
@endphp

<p {{ $attributes->merge(['class' => 'font-hand text-eyebrow leading-none '.$toneClasses]) }}>
    {{ $text }}
</p>
