@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-control px-6 py-3.5 '
        .'text-body font-semibold no-underline transition-colors duration-200 ease-soft '
        .'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';

    $variants = [
        'primary' => 'bg-primary text-ink-inverse hover:bg-primary-dark focus-visible:outline-primary-dark',
        'secondary' => 'border border-line-strong bg-transparent text-primary hover:border-primary hover:bg-primary hover:text-ink-inverse focus-visible:outline-primary',
    ];

    // Неактивное состояние: не прячем элемент через opacity-50, иначе подпись
    // теряет контраст и исчезает подсказка. Вместо этого — приглушённый фон,
    // тёмный текст и пунктирная рамка, которые читаются как «пока недоступно».
    $disabledVariants = [
        'primary' => 'bg-primary/20 text-ink hover:bg-primary/20',
        'secondary' => 'border-dashed border-line-strong bg-transparent text-ink-muted hover:border-line-strong hover:bg-transparent hover:text-ink-muted',
    ];

    // Тёмная секция: цвета разворачиваются, иначе тёмный текст неактивной
    // кнопки проваливается в фон primary-dark.
    if ($tone === 'dark') {
        $variants = [
            'primary' => 'bg-ink-inverse text-primary-dark hover:bg-surface focus-visible:outline-ink-inverse',
            'secondary' => 'border border-ink-inverse/40 bg-transparent text-ink-inverse hover:border-ink-inverse hover:bg-ink-inverse hover:text-primary-dark focus-visible:outline-ink-inverse',
        ];

        $disabledVariants = [
            'primary' => 'bg-ink-inverse/15 text-ink-inverse hover:bg-ink-inverse/15',
            'secondary' => 'border-dashed border-ink-inverse/40 bg-transparent text-ink-inverse/80 hover:border-ink-inverse/55 hover:bg-transparent hover:text-ink-inverse/80',
        ];
    }

    $variantKey = array_key_exists($variant, $variants) ? $variant : 'primary';

    // В неактивном состоянии берём только базу и disabled-вариант. Если
    // оставить рядом обычный вариант, в разметке окажутся два конфликтующих
    // text-цвета (например text-primary и text-ink-muted), и победит не
    // порядок в атрибуте, а порядок этих правил в собранном CSS.
    $classes = trim($base.' '.$variants[$variantKey]);
    $disabledClasses = trim($base.' '.$disabledVariants[$variantKey])
        .' cursor-not-allowed focus-visible:outline-none';
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
        {{ $attributes->merge(['class' => $disabled ? $disabledClasses : $classes]) }}
    >{{ $label }}{{ $slot }}</button>
@endif
