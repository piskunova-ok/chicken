@php
    $levels = ['h1' => 'text-display', 'h2' => 'text-h2', 'h3' => 'text-h3'];
    $titleSize = $levels[$level] ?? $levels['h2'];

    $alignClasses = match ($align) {
        'center' => 'mx-auto max-w-3xl text-center',
        default => 'max-w-3xl',
    };

    $toneClasses = match ($tone) {
        'dark' => [
            'title' => 'text-ink-inverse',
            'lead' => 'text-ink-inverse/75',
        ],
        default => [
            'title' => 'text-ink',
            'lead' => 'text-ink-muted',
        ],
    };
@endphp

<div {{ $attributes->merge(['class' => $alignClasses]) }}>
    @if ($eyebrow !== null)
        <x-eyebrow :text="$eyebrow" :tone="$tone" class="mb-3" />
    @endif

    <{{ $level }} class="{{ $titleSize }} {{ $toneClasses['title'] }}">{{ $title }}</{{ $level }}>

    @if ($lead !== null)
        <p class="mt-5 text-lead {{ $toneClasses['lead'] }}">{{ $lead }}</p>
    @endif
</div>
