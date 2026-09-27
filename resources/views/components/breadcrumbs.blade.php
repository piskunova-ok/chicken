{{--
    Хлебные крошки.

    Ожидает массив элементов: ['label' => 'Главная', 'url' => '/'].
    Элемент без url выводится обычным текстом, а не ссылкой — это важно,
    потому что отдельной страницы /products пока нет, и ссылка на неё
    привела бы к 404.

    Последний элемент — текущая страница: он помечается aria-current="page"
    и ссылкой не является.

    Разметка nav > ol > li соответствует семантике хлебных крошек, а список
    озвучивается скринридером как «Хлебные крошки».
--}}
@props([
    'items' => [],
])

<nav aria-label="Хлебные крошки" class="text-small">
    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1 text-ink-muted">
        @foreach ($items as $index => $item)
            @php $isLast = $index === array_key_last($items); @endphp

            <li class="flex items-center gap-2">
                @if ($index > 0)
                    <span aria-hidden="true" class="text-ink-muted/50">/</span>
                @endif

                @if (! $isLast && filled($item['url'] ?? null))
                    <a
                        href="{{ $item['url'] }}"
                        class="rounded transition-colors hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    >{{ $item['label'] }}</a>
                @else
                    <span
                        @if ($isLast) aria-current="page" class="text-ink" @endif
                    >{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
