{{--
    Карточка товара каталога.

    Компонент покрывает текущие и будущие поля позиции. Обязательны только
    название и изображение; всё остальное выводится, если значение есть:

    - short_description — краткое описание;
    - specs             — характеристики (категория, упаковка, количество, вес,
                          срок годности, условия хранения, пищевая ценность);
    - additional_info   — дополнительная информация;
    - details_url       — ссылка на страницу товара.

    Пока характеристики не подтверждены, в config/catalog.php их нет, и
    соответствующие строки просто не появляются. Никаких пустых строк и
    [УТОЧНИТЬ] посетителю не показывается.

    Если details_url не задан, кнопка неактивна: страницы товара на этом этапе
    нет, и ссылка повела бы на 404.
--}}
<article {{ $attributes->merge(['class' => 'flex h-full flex-col overflow-hidden rounded-card border border-line bg-canvas shadow-soft']) }}>
    <x-media
        :src="$image"
        :alt="$imageAlt"
        :ratio="$ratio"
        variant="card"
        quiet
    />

    <div class="flex flex-1 flex-col p-6">
        <h3 class="text-h3 text-ink">{{ $name }}</h3>

        @if ($hasDescription())
            <p class="mt-3 text-body text-ink-muted">{{ $shortDescription }}</p>
        @endif

        @if ($hasSpecs())
            {{--
                Характеристики выводятся списком, а не таблицей: значения
                короткие, таблица на мобильном разъезжается и даёт лишнюю
                визуальную сетку ради двух-трёх строк.
            --}}
            <dl class="mt-5 space-y-2 border-t border-line pt-5">
                @foreach ($visibleSpecs() as [$specLabel, $specValue])
                    <div class="flex items-baseline justify-between gap-4 text-small">
                        <dt class="text-ink-muted">{{ $specLabel }}</dt>
                        <dd class="text-right font-medium text-ink">{{ $specValue }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if ($hasAdditionalInfo())
            <p class="mt-5 text-small text-ink-muted">{{ $additionalInfo }}</p>
        @endif

        {{--
            Кнопка «Подробнее» появляется только когда у позиции есть
            details_url. Пока страницы товара нет, показывать неактивную
            кнопку нельзя: она выглядит как неработающая ссылка и вводит
            посетителя в заблуждение. Лучше не показывать ничего — карточка
            остаётся информационной.

            Обёртка с mt-auto тоже исчезает вместе с кнопкой, иначе карточка
            получила бы пустой отступ снизу.

            Поддержка details_url сохранена: как только появятся страницы
            товаров, ссылка заработает без правок этой разметки.
        --}}
        @if ($hasDetailsUrl())
            <div class="mt-auto pt-6">
                <x-button
                    :label="$detailsLabel"
                    :href="$detailsUrl"
                    variant="secondary"
                    data-card-details
                />
            </div>
        @endif
    </div>
</article>
