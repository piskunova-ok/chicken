@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-control px-6 py-3.5 '
        .'text-body font-semibold no-underline transition-colors duration-200 ease-soft '
        .'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';

    $variants = [
        'primary' => 'bg-primary text-ink-inverse hover:bg-primary-dark '
            .'focus-visible:outline-primary-dark',
        'secondary' => 'border border-line-strong bg-transparent text-primary hover:border-primary hover:bg-primary hover:text-ink-inverse '
            .'focus-visible:outline-primary',
    ];

    $classes = trim($base.' '.($variants[$variant] ?? $variants['primary']));

    $disabledClasses = 'pointer-events-none opacity-50 shadow-none';
@endphp

@if ($href !== null && ! $disabled)
    <a
        href="{{ $href }}"
        @if ($external) rel="noopener noreferrer" target="_blank" @endif
        {{ $attributes->merge(['class' => $classes]) }}
    >{{ $label }}{{ $slot }}</a>
@else
    <button
        type="button"
        @disabled($disabled)
        @if ($disabled) aria-disabled="true" @endif
        {{ $attributes->merge(['class' => $classes.' '.($disabled ? $disabledClasses : '')]) }}
    >{{ $label }}{{ $slot }}</button>
@endif
