{{--
    СТРАНИЦА «ПРОДУКЦИЯ» — /products.

    Обзорный раздел: список категорий продукции, по одной крупной
    карточке на категорию. Каждая карточка ведёт на страницу категории
    /products/eggs или /products/chicken.

    Источники данных разделены так же, как на странице категории:
        база            -> какие категории активны (App\Http\Controllers\
                          ProductIndexController);
        config/site.php -> название, описание, фотография, alt, подпись
                          кнопки и имя маршрута;
        config/content.php -> вступление и тексты блоков страницы.

    Третьих категорий здесь быть не может: список строится из
    config/site.php, а не из таблицы, поэтому категория, добавленная в база
    без записи в конфиге, на эту страницу не попадёт. Категория, отключённая
    в базе, исчезнет, но её адрес останется рабочим правилом и по-прежнему
    отдаст 404 — как и до появления этой страницы.

    Композиция повторяет блок «Продукция» главной (две карточки 50/50, на
    мобильных друг под другом), только карточки здесь крупнее и ведут в
    каталог. Заголовок блока на главной и заголовки здесь — разные
    строки, а не один и тот же текст, поэтому дублирования не видно.
--}}
@php
    $intro = (array) config('content.intro', []);

    $breadcrumbs = [
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Продукция', 'url' => null],
    ];
@endphp

<x-layouts.app
    :title="'Продукция — '.config('site.name')"
    :description="$intro['lead'] ?? config('site.meta.description')"
>
    {{-- ============================ HERO ============================ --}}
    {{--
        Геометрия та же, что у остальных страниц: текст 5/12, фотография
        7/12. Здесь в 7/12 стоит та же hero-фотография, что и на главной, но
        в пропорциях 16/9: исходник 1145x1374 (портрет) в широком боксе
        кадрируется по высоте, поэтому важный кадр остаётся в кадре.
    --}}
    <x-section tone="canvas" spacing="default">
        <x-breadcrumbs :items="$breadcrumbs" class="mb-10" />

        <div class="grid gap-10 lg:grid-cols-12 lg:items-center lg:gap-12">
            <div class="lg:col-span-5">
                <x-section-heading
                    eyebrow="Продукция"
                    title="Яйца и мясо кур"
                    lead="Основные направления нашей продукции. Выберите категорию, чтобы посмотреть ассортимент."
                    level="h1"
                />
            </div>

            <div class="lg:col-span-7">
                <x-media
                    src="{{ asset('images/01_hero_roast_chicken.jpg') }}"
                    alt="Жареная курица на столе"
                    variant="hero"
                    ratio="16/9"
                    priority
                />
            </div>
        </div>
    </x-section>

    {{-- ========================== КАТЕГОРИИ ======================== --}}
    {{--
        Вся карточка кликабельна: у ссылки в заголовке есть псевдоэлемент
        after:inset-0, который накрывает карточку. Ссылка остаётся внутри
        h2, поэтому у карточки ровно одно имя для скринридера, а сама
        кнопка вынесена на z-10 и этим накрытием не перехватывается.
    --}}
    <x-section tone="surface" spacing="default">
        <x-section-heading
            eyebrow="Для вашего стола"
            title="Категории продукции"
            lead="В каждой категории — подробный ассортимент с фотографиями и описаниями."
        />

        @if ($categories !== [])
            <div class="mt-10 grid gap-6 md:grid-cols-2">
                @foreach ($categories as $category)
                    <article class="relative flex h-full flex-col overflow-hidden rounded-card border border-line bg-canvas shadow-soft hover:border-primary/40">
                        <x-media
                            :src="asset($category['image'])"
                            :alt="$category['image_alt']"
                            variant="card"
                            ratio="16/9"
                        />

                        <div class="flex flex-1 flex-col p-6 lg:p-7">
                            <h2 class="text-h2 text-ink">
                                @if ($category['url'] !== null)
                                    <a
                                        href="{{ $category['url'] }}"
                                        class="after:absolute after:inset-0 after:content-['']"
                                    >{{ $category['name'] }}</a>
                                @else
                                    {{ $category['name'] }}
                                @endif
                            </h2>

                            <p class="mt-3 text-body text-ink-muted">
                                {{ $category['description'] }}
                            </p>

                            <x-route-button
                                class="relative z-10 mt-auto self-start pt-6"
                                :label="$category['cta']"
                                :route="$category['route']"
                            />
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            {{--
                Пустое состояние на случай, если все категории отключены в
                базе. Ссылок на каталог здесь не даём: переходить некуда, и
                ссылка была бы обещанием, которое страница не сдержит.
            --}}
            <p class="mt-10 max-w-2xl text-body text-ink-muted">
                Ассортимент продукции скоро появится.
            </p>
        @endif
    </x-section>

    {{-- ============================== CTA ============================ --}}
    <x-section tone="canvas" spacing="default">
        <div class="mx-auto max-w-3xl text-center">
            <x-section-heading
                eyebrow="Мы всегда на связи"
                :title="config('content.contacts.title')"
                :lead="config('content.contacts.lead')"
                align="center"
            />

            <div class="mt-8 flex justify-center">
                <x-route-button
                    label="Связаться с нами"
                    route="contacts"
                />
            </div>
        </div>
    </x-section>
</x-layouts.app>
