{{--
    СТРАНИЦА «ПРОДУКЦИЯ» — /products.

    Обзорный раздел: список категорий продукции, по одной крупной
    карточке на категорию. Каждая карточка ведёт на страницу категории
    /products/eggs или /products/chicken.

    Источники данных разделены так же, как на странице категории:
        база            -> какие категории активны (App\Http\Controllers\
                          ProductIndexController);
        config/site.php -> название, описание, фотография, alt, подпись
                          кнопки и имя маршрута (для категорий eggs/chicken);
        config/content.php -> вступление и тексты блоков страницы.

    Список строится по активным категориям из базы. Для категорий eggs и
    chicken у записи находятся данные в config/site.php (фото, описание,
    CTA); категория, добавленная в админке без записи в конфиге, тоже
    выводится — без фотографии и с описанием из своей записи. Категория,
    отключённая в базе, исчезает, а её адрес по-прежнему отдаёт 404
    (маршрут выбирает только активные).

    Композиция повторяет блок «Продукция» главной (две карточки 50/50, на
    мобильных друг под другом), только карточки здесь крупнее и ведут в
    каталог. Заголовок блока на главной и заголовки здесь — разные
    строки, а не один и тот же текст, поэтому дублирования не видно.
--}}
@php
    use App\Support\Settings;

    $intro = (array) config('content.intro', []);

    $breadcrumbs = [
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Продукция', 'url' => null],
    ];

    /*
     | Собственное фото первого экрана. Оно отличается от главной и
     | показывает ассортимент (яйца и охлаждённая куриная продукция), а не
     | готовое блюдо. Файл лежит в public/images/products-hero.png и
     | отдаётся локально, без внешних URL; если его не окажется, вместо
     | битой картинки показывается локальная заглушка.
     */
    $heroImageFile = 'images/products-hero.png';
    $heroImage = file_exists(public_path($heroImageFile)) ? asset($heroImageFile) : null;
@endphp

<x-layouts.app
    :title="'Продукция — '.Settings::value('company_name')"
    :description="$intro['lead'] ?? config('site.meta.description')"
>
    {{-- ============================ HERO ============================ --}}
    {{--
        Геометрия та же, что у остальных страниц: текст 5/12, фотография
        7/12, две колонки на desktop и одна колонка на мобильных.

        Фото здесь своё (public/images/products-hero.png, 1672x941), а не
        с главной: первый экран обзора показывает ассортимент — яйца и
        охлаждённую куриную продукцию, — а не готовое блюдо, поэтому
        страницы визуально различаются. Исходник ровно 16/9, как и бокс,
        поэтому кадр не режется: object-cover сохраняет весь кадр.

        Отступы заданы здесь, а не через spacing: секция уплотнена сверху
        и снизу, чтобы первый экран не был разреженным, а блок «Категории
        продукции» начинался заметно ближе. Высота hero меньше вьюпорта —
        полноэкранный первый экран здесь не делается.
    --}}
    <x-section tone="canvas" spacing="none" class="pt-10 pb-8 lg:pt-14 lg:pb-12">
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
                    :src="$heroImage"
                    alt="Яйца и свежая куриная продукция"
                    variant="hero"
                    ratio="16/9"
                    priority
                />
            </div>
        </div>
    </x-section>

    {{-- ========================== КАТЕГОРИИ ======================== --}}
    {{--
        Блок начинается вплотную к hero: верхний отступ уменьшен, чтобы
        между первым экраном и категориями не оставалось большого пустого
        поля. Нижний отступ оставлен прежним — ритм дальше по странице
        сохраняется.

        Вся карточка кликабельна: у ссылки в заголовке есть псевдоэлемент
        after:inset-0, который накрывает карточку. Ссылка остаётся внутри
        h2, поэтому у карточки ровно одно имя для скринридера, а сама
        кнопка вынесена на z-10 и этим накрытием не перехватывается.
    --}}
    <x-section tone="surface" spacing="none" class="pt-10 pb-16 lg:pt-12 lg:pb-24">
        <x-section-heading
            eyebrow="Для вашего стола"
            title="Категории продукции"
            lead="В каждой категории — подробный ассортимент с фотографиями и описаниями."
        />

        @if ($categories !== [])
            <div class="mt-10 grid gap-6 md:grid-cols-2">
                @foreach ($categories as $category)
                    <article class="relative flex h-full flex-col overflow-hidden rounded-card border border-line bg-canvas shadow-soft hover:border-primary/40">
                        @if (filled($category['image'] ?? null))
                            <x-media
                                :src="asset($category['image'])"
                                :alt="($category['image_alt'] ?? null) ?: $category['name']"
                                variant="card"
                                ratio="16/9"
                            />
                        @endif

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

                            @if (filled($category['description'] ?? null))
                                <p class="mt-3 text-body text-ink-muted">
                                    {{ $category['description'] }}
                                </p>
                            @endif

                            <x-route-button
                                class="relative z-10 mt-auto self-start pt-6"
                                :label="$category['cta']"
                                :href="$category['url']"
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
