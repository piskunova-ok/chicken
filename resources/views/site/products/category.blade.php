{{--
    СТРАНИЦА КАТЕГОРИИ ПРОДУКЦИИ.

    Общая страница для всех категорий: /products/eggs и /products/chicken.
    Никаких данных не придумывается в разметке — всё приходит из
    config/catalog.php через маршрут, а Blade только выводит переданный
    массив в цикле.

    Структура страницы:
        1. Хлебные крошки
        2. Intro / hero категории: eyebrow, h1, краткое описание, фото
        3. Каталог: h2, лид, пометка, сетка карточек
        4. Небольшой CTA

    Чего здесь сознательно нет: цен, веса, фасовки, типа упаковки, срока
    годности, условий хранения, пищевой ценности и наличия позиций. Эти
    сведения не подтверждены заказчиком, поэтому их нет и в конфиге, и
    карточки их не выводят. Страница не создаёт впечатления, что сайт
    продаёт, а информирует о разделе, который готовится.

    Заголовок h1 и тексты взяты из config/site.php и config/catalog.php.
    Страница товара на этом этапе не создаётся, поэтому «Подробнее» в
    карточках неактивно и не ведёт на 404.
--}}

@php
    /*
     | Хлебные крошки. «Продукция» намеренно остаётся текстом без ссылки:
     | отдельной страницы /products на этом этапе нет, и ссылка на неё
     | привела бы к 404.
     */
    $breadcrumbs = [
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Продукция', 'url' => null],
        ['label' => $product['name'], 'url' => null],
    ];

    $catalog = $catalog ?? [];
    $catalogBlock = $catalog['catalog'] ?? [];
    $items = (array) ($catalogBlock['items'] ?? []);

    /*
     | Мета-описание приходит из конфига, а заголовок собирается из названия
     | категории и названия компании: оба значения уже есть в проекте, и
     | дублировать их в catalog.php не нужно.
     */
    $pageTitle = $product['name'].' — '.config('site.name');
    $pageDescription = $catalog['meta_description'] ?? $product['description'];
@endphp

<x-layouts.app :title="$pageTitle" :description="$pageDescription">
    {{-- ============ ХЛЕБНЫЕ КРОШКИ + INTRO / HERO КАТЕГОРИИ ========= --}}
    {{--
        Крошки и hero живут в одной секции: отдельная секция сразу после
        предыдущей потребовала бы обнулить её верхний отступ, а
        переопределение py-* через pt-0 не работает предсказуемо — при
        равной специфичности побеждает порядок правил в собранном CSS, а не
        порядок классов в атрибуте.

        Геометрия hero та же, что на главной: текст 5/12 слева, фотография
        7/12 справа. Пропорции 16/9 подобраны под исходники категорий
        (768x300 и 764x300 около 2.55:1) — в этом боксе картинка
        растягивается минимально.
    --}}
    <x-section tone="canvas" spacing="default">
        <x-breadcrumbs :items="$breadcrumbs" class="mb-10" />

        <div class="grid gap-10 lg:grid-cols-12 lg:items-center lg:gap-12">
            <div class="lg:col-span-5">
                <x-section-heading
                    :eyebrow="$catalog['eyebrow'] ?? 'Продукция'"
                    :title="$product['name']"
                    :lead="$catalog['intro'] ?? null"
                    level="h1"
                />
            </div>

            <div class="lg:col-span-7">
                {{--
                    asset() обязателен: в конфиге (а позже и в БД) путь хранится
                    относительным — «images/02_eggs.jpg». На главной, где URL
                    равен «/», такой путь случайно работает, но на
                    /products/eggs браузер запросил бы /products/images/… и
                    получил 404. Ссылка должна строиться от корня сайта.
                --}}
                <x-media
                    :src="filled($catalog['image'] ?? null) ? asset($catalog['image']) : null"
                    :alt="$catalog['image_alt'] ?? $product['name']"
                    variant="hero"
                    ratio="16/9"
                    priority
                />
            </div>
        </div>
    </x-section>

    {{-- ============================ КАТАЛОГ ========================== --}}
    <x-section tone="surface" spacing="default">
        <x-section-heading
            :title="$catalogBlock['heading'] ?? 'Ассортимент'"
            :lead="$catalogBlock['lead'] ?? null"
        />

        @if (filled($catalogBlock['notice'] ?? null))
            {{--
                Пометка о демонстрационном характере карточек. Она же
                объясняет посетителю, почему у карточек нет характеристик.
            --}}
            <p class="mt-6 max-w-2xl text-body text-ink-muted">
                {{ $catalogBlock['notice'] }}
            </p>
        @endif

        {{--
            Сетка каталога: 1 карточка на мобильном, 2 на планшете, 3 на
            desktop. Одинаковая высота карточек обеспечивается h-full и
            mt-auto у кнопки, поэтому описания и кнопки выравниваются по
            нижнему краю независимо от длины текста.
        --}}
        <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $item)
                <li class="flex">
                    {{--
                        Путь к фото (сейчас у всех позиций null — показывается
                        локальная заглушка) оборачивается в asset() уже сейчас,
                        чтобы при появлении реальных файлов в конфиге или БД
                        не понадобилась правка разметки. null превращается в
                        null, а не в «/».
                    --}}
                    <x-product-card
                        class="w-full"
                        :name="$item['name']"
                        :image="filled($item['photo'] ?? null) ? asset($item['photo']) : null"
                        :image-alt="$item['photo_alt'] ?? null"
                        :short-description="$item['short_description'] ?? null"
                        :specs="$item['specs'] ?? []"
                        :additional-info="$item['additional_info'] ?? null"
                        :details-url="$item['details_url'] ?? null"
                    />
                </li>
            @endforeach
        </ul>
    </x-section>

    {{-- ============================== CTA ============================ --}}
    {{--
        Кнопка ведёт на route('contacts'), которого ещё нет: x-route-button
        в этом случае выводит неактивное состояние. Ссылка на 404 не
        создаётся.
    --}}
    <x-section tone="canvas" spacing="default">
        <div class="mx-auto max-w-3xl text-center">
            <x-section-heading
                :title="$cta['title'] ?? 'Не нашли нужную информацию?'"
                :lead="$cta['text'] ?? null"
                align="center"
            />

            <div class="mt-8 flex justify-center">
                <x-route-button
                    :label="$cta['button'] ?? 'Связаться с нами'"
                    :route="$cta['route'] ?? 'contacts'"
                />
            </div>
        </div>
    </x-section>
</x-layouts.app>
