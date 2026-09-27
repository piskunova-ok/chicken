{{--
    Media: реальное изображение или локальный placeholder.

    Чтобы поставить реальную фотографию, достаточно добавить к компоненту
    атрибуты src и alt. Пропорции, скругления и отступы сохранятся:

        <x-media src="images/products/eggs.jpg" alt="Яйца кур" ratio="4/3" />

    Файл положить в public/images/... — внешние URL не используются.
--}}
@php
    $frameClasses = 'relative w-full overflow-hidden rounded-media bg-primary/5 '.$ratioClasses();
@endphp

<div {{ $attributes->merge(['class' => $frameClasses]) }}>
    @if ($src !== null)
        <img
            src="{{ $src }}"
            @if ($alt !== null) alt="{{ $alt }}" @else alt="" @endif
            @if ($priority) fetchpriority="high" @else loading="lazy" @endif
            decoding="async"
            class="size-full object-cover"
        >
    @else
        {{-- Локальная векторная заглушка: никаких внешних изображений. --}}
        <div
            class="absolute inset-0"
            @if ($quiet) aria-hidden="true" @else role="img" aria-label="{{ $placeholderLabel() }}" @endif
        >
            <svg
                class="size-full"
                viewBox="0 0 400 300"
                preserveAspectRatio="xMidYMid slice"
                aria-hidden="true"
                focusable="false"
            >
                @if ($variant === 'hero')
                    <circle cx="288" cy="104" r="76" class="fill-accent/12" />
                    <circle cx="288" cy="104" r="124" class="fill-primary/8" />
                    <path d="M0 262C90 192 172 302 262 242 332 196 380 236 400 250V300H0Z" class="fill-primary/12" />
                    <path d="M0 300C104 250 206 320 400 270V300Z" class="fill-primary/20" />
                @elseif ($variant === 'lifestyle')
                    <circle cx="118" cy="76" r="50" class="fill-accent/12" />
                    <circle cx="118" cy="76" r="86" class="fill-primary/8" />
                    <path d="M0 206C118 152 242 232 400 182V300H0Z" class="fill-primary/12" />
                    <path d="M0 300V252C104 222 202 282 400 232V300Z" class="fill-primary/20" />
                @else
                    <ellipse cx="200" cy="168" rx="60" ry="76" class="fill-primary/15" />
                    <ellipse cx="200" cy="168" rx="94" ry="114" class="fill-primary/8" />
                    <path d="M0 250C82 210 152 270 242 240 312 218 362 240 400 252V300H0Z" class="fill-accent/10" />
                @endif
            </svg>

            @unless ($quiet)
                <p class="absolute inset-0 flex items-center justify-center px-6 text-center text-small text-ink-muted/70">
                    {{ $placeholderLabel() }}
                </p>
            @endunless
        </div>
    @endif
</div>
