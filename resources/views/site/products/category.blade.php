{{--
    СТРАНИЦА КАТЕГОРИИ ПРОДУКЦИИ.

    Общая страница для всех категорий: /products/eggs и /products/chicken.

    Источники данных разделены:
        SQLite + Eloquent -> $category и $products (заголовок h1, хлебные
                             крошки и сетка карточек);
        config/catalog.php -> оформление и тексты страницы (eyebrow, hero,
                             intro, meta_description, заголовок и пометка
                             блока каталога, CTA).

    Разделение временное: заказчиком подтверждены только категории и
    товары, поэтому переносить в базу оформление нельзя — для него ещё
    нет ни колонок, ни данных.

    Структура страницы:
        1. Хлебные крошки
        2. Intro / hero категории: eyebrow, h1, краткое описание, фото
        3. Каталог: h2, лид, пометка, сетка карточек
        4. Небольшой CTA

    Чего здесь сознательно нет: цен, веса, фасовки, типа упаковки, срока
    годности, условий хранения, пищевой ценности и наличия позиций. Эти
    сведения не подтверждены заказчиком, поэтому в базе они NULL, и
    карточки их не выводят. Страница не создаёт впечатления, что сайт
    продаёт, а информирует о разделе, который готовится.

    Название категории для h1 и крошек берётся из базы, а тексты остаются
    в config/catalog.php и config/site.php. Страница товара на этом этапе
    не создаётся, поэтому «Подробнее» в карточках не выводится.
--}}

@php
    /*
     | Хлебные крошки. «Продукция» ведёт на обзорную страницу /products:
     | маршрут products.index создан, поэтому пункт стал ссылкой. До его
     | появления ссылка была бы 404, и элемент выводился текстом.
     |
     | Название последнего элемента — из базы: категория пришла из Eloquent.
     */
    $breadcrumbs = [
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Продукция', 'url' => route('products.index')],
        ['label' => $category->name, 'url' => null],
    ];

    $catalog = $catalog ?? [];
    $catalogBlock = $catalog['catalog'] ?? [];

    /*
     | Мета-описание приходит из конфига, а заголовок собирается из названия
     | категории (из базы) и названия компании: оба значения уже есть в
     | проекте, и дублировать их в catalog.php не нужно.
     |
     | $page — запись категории из config/site.php. Используется только как
     | запасной источник описания: список товаров отсюда больше не берётся.
     */
    $pageTitle = $category->name.' — '.config('site.name');
    $pageDescription = $catalog['meta_description'] ?? ($page['description'] ?? null);
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
                    :title="$category->name"
                    :lead="$catalog['intro'] ?? null"
                    level="h1"
                />
            </div>

            <div class="lg:col-span-7">
                {{--
                    asset() обязателен: в конфиге путь хранится относительным —
                    «images/02_eggs.jpg». На главной, где URL равен «/», такой
                    путь случайно работает, но на /products/eggs браузер
                    запросил бы /products/images/… и получил 404. Ссылка
                    должна строиться от корня сайта.
                --}}
                <x-media
                    :src="filled($catalog['image'] ?? null) ? asset($catalog['image']) : null"
                    :alt="$catalog['image_alt'] ?? $category->name"
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

            $products — активные товары этой категории из базы, уже в
            порядке sort_order, а массив товаров из config/catalog.php
            страница больше не читает.
        --}}
        <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($products as $product)
                <li class="flex">
                    {{--
                        Путь к фото загружается через админку и хранится
                        относительным к публичному диску — «products/<ulid>.jpg»,
                        поэтому превращается в абсолютный URL публичного диска
                        через Storage::disk('public')->url(). У всех позиций
                        поле image пока NULL, и url() не вызывается вовсе: так
                        на месте фотографии остаётся локальная заглушка.
                        null превращается в null, а не в «/».

                        Характеристики приходят из cardSpecs() модели: сейчас
                        все значения NULL, и компонент не выводит ни одной
                        строки. Подтверждённые значения появятся сами.

                        image-alt не передаётся: в таблице products такой
                        колонки нет, а подставлять выдуманный alt значило бы
                        описать несуществующую фотографию.

                        details-url не передаётся: страниц отдельных товаров
                        ещё нет, поэтому кнопка «Подробнее» не выводится и
                        ссылка на 404 невозможна. Когда страницы появятся,
                        колонка и одно пропс добавятся здесь.
                    --}}
                    <x-product-card
                        class="w-full"
                        :name="$product->name"
                        :image="filled($product->image) ? Illuminate\Support\Facades\Storage::disk('public')->url($product->image) : null"
                        :short-description="$product->short_description"
                        :specs="$product->cardSpecs()"
                        :additional-info="$product->additional_info"
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
