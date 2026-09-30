{{--
    ГЛАВНАЯ СТРАНИЦА.

    Порядок блоков: Hero -> смысловой блок -> О компании -> Продукция ->
    Качество -> Lifestyle -> Для бизнеса -> контактный CTA.

    Правила, которым подчинена страница:
    - один h1 (Hero), дальше h2 на секции и h3 на карточки и пункты;
    - фотографии лежат локально в public/images и отдаются в оптимизированном
      виде (.jpg). Исходники .png остаются в репозитории нетронутыми. Пропорции
      бокса подобраны под пропорции исходника, поэтому кадрирование по минимуму,
      а апскейл не используется;
    - ссылки на разделы идут через x-route-button, а продуктовые карточки —
      через SiteLinks::resolveOne: пока маршрута нет, пункт выводится
      неактивным, поэтому страница не ведёт в 404 ни при каком состоянии
      проекта;
    - тексты секций лежат в config/content.php и выводятся отсюда же, что
      и на /about, /quality и /contacts;
    - никаких неподтверждённых цифр, сроков, сертификатов и объёмов.
--}}
@php
    /*
     | Тексты секций. Утверждены владельцем проекта и лежат в
     | config/content.php — оттуда же их читают /about, /quality и
     | /contacts. Здесь только чтение, без второй копии текстов.
     |
     | Плейсхолдеры [НАЗВАНИЕ КОМПАНИИ], [ТЕЛЕФОН] и [EMAIL] намеренно
     | оставлены как есть: реальные значения должен подставить владелец.
     */
    $intro = (array) config('content.intro', []);

    $aboutParagraphs = (array) config('content.about.paragraphs', []);

    $qualityLead = config('content.quality.lead');
    $qualityItems = (array) config('content.quality.items', []);

    $lifestyleLead = config('content.lifestyle.lead');

    $businessLeads = (array) config('content.business.leads', []);
    $businessItems = (array) config('content.business.items', []);

    $contactsLead = config('content.contacts.lead');

    /*
     | Карточки продукции. Маршрут разрешается один раз здесь, чтобы в разметке
     | не дублировать проверку Route::has() и не вести карточку в 404, если
     | категория когда-нибудь исчезнет из routes/web.php.
     */
    $products = array_map(
        static fn (array $product): array => $product + [
            'url' => \App\Support\SiteLinks::resolveOne([
                'label' => $product['name'],
                'route' => $product['route'],
            ])['url'],
        ],
        (array) config('site.products', []),
    );
@endphp

<x-layouts.app
    :title="config('site.meta.title')"
    :description="config('site.meta.description')"
>
    {{-- ============================ HERO ============================ --}}
    {{--
        Первый экран: на desktop две колонки 5/7, текст слева, фотография
        справа. На мобильных сетка складывается в одну колонку, текст идёт
        первым и остаётся выше фотографии.

        Исходник 1145x1374 (портрет 5:6). Бокс 1/1 запрашивает у картинки
        около 8% высоты, тогда как 3/2 съедал бы почти половину кадра.
    --}}
    <x-section tone="canvas" spacing="loose">
        <div class="grid gap-10 lg:grid-cols-12 lg:items-center lg:gap-12">
            <div class="lg:col-span-5">
                <x-section-heading
                    eyebrow="Натуральный вкус каждый день"
                    title="Свежесть, которую выбирают для своего стола"
                    lead="Яйца и мясо кур — продукты для домашних блюд, торговли и профессиональной кухни."
                    level="h1"
                />

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <x-button label="Наша продукция" href="#products" />
                    <x-route-button
                        label="Связаться с нами"
                        route="contacts"
                        variant="secondary"
                    />
                </div>
            </div>

            <div class="lg:col-span-7">
                <x-media
                    src="{{ asset('images/01_hero_roast_chicken.jpg') }}"
                    alt="Жареная курица на столе"
                    variant="hero"
                    ratio="1/1"
                    priority
                />
            </div>
        </div>
    </x-section>

    {{-- ======================= СМЫСЛОВОЙ БЛОК ======================= --}}
    <x-section tone="surface" spacing="default">
        <x-section-heading
            :title="$intro['title'] ?? null"
            :lead="$intro['lead'] ?? null"
            align="center"
        />
    </x-section>

    {{-- ========================= О КОМПАНИИ ========================= --}}
    {{--
        Асимметричная композиция 50/50: фотография слева, текст справа.
        Исходник 688x380 (1.81:1) в боксе 4/3 умещается почти без потерь.
    --}}
    <x-section tone="canvas" spacing="default">
        <div class="grid gap-10 lg:grid-cols-2 lg:items-center lg:gap-16">
            <x-media
                class="order-2 lg:order-1"
                src="{{ asset('images/05_cooking_lifestyle.jpg') }}"
                alt="Приготовление блюда из продуктов"
                variant="lifestyle"
                ratio="4/3"
            />

            <div class="order-1 lg:order-2">
                <x-section-heading
                    eyebrow="Знакомьтесь с нами"
                    title="Продукты, которым хочется доверять"
                />

                <div class="mt-6 space-y-4 text-body text-ink-muted">
                    @foreach ($aboutParagraphs as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>

                <x-route-button
                    label="Подробнее о компании"
                    route="about"
                    variant="secondary"
                    class="mt-8"
                />
            </div>
        </div>
    </x-section>

    {{-- ========================== ПРОДУКЦИЯ ========================== --}}
    {{--
        Две равноправные карточки: 50/50 на desktop, друг под другом на mobile.

        Исходники 768x300 и 764x300 (около 2.55:1). В боксе 4/3 картинку
        пришлось бы растянуть в 1.37x, поэтому берём 16/9: масштаб около
        1.03x, вся ширина кадра используется, мышь в кадре не режется.

        Вся карточка кликабельна: у ссылки в заголовке есть псевдоэлемент
        after:inset-0, который накрывает карточку. Ссылка при этом остаётся
        внутри h3, поэтому у карточки ровно одно имя для скринридера, а сама
        кнопка вынесена на z-10 и не перехватывается этим накрытием.
    --}}
    <x-section tone="surface" id="products" spacing="default">
        <x-section-heading
            eyebrow="Для вашего стола"
            title="Продукты на каждый день"
            lead="Яйца и мясо кур — основа множества привычных блюд. Продукция предназначена для домашней кухни, розничной торговли и профессионального использования."
        />

        <div class="mt-10 grid gap-6 md:grid-cols-2">
            @foreach ($products as $product)
                <article class="relative flex h-full flex-col overflow-hidden rounded-card border border-line bg-canvas shadow-soft hover:border-primary/40">
                    <x-media
                        :src="asset($product['image'])"
                        :alt="$product['image_alt']"
                        variant="card"
                        ratio="16/9"
                    />

                    <div class="flex flex-1 flex-col p-6 lg:p-7">
                        <h3 class="text-h3 text-ink">
                            @if ($product['url'] !== null)
                                <a
                                    href="{{ $product['url'] }}"
                                    class="after:absolute after:inset-0 after:content-['']"
                                >{{ $product['name'] }}</a>
                            @else
                                {{ $product['name'] }}
                            @endif
                        </h3>
                        <p class="mt-3 text-body text-ink-muted">{{ $product['description'] }}</p>

                        <x-route-button
                            class="relative z-10 mt-auto self-start pt-6"
                            :label="$product['cta']"
                            :route="$product['route']"
                        />
                    </div>
                </article>
            @endforeach
        </div>
    </x-section>

    {{-- =========================== КАЧЕСТВО ========================== --}}
    <x-section tone="dark" spacing="default">
        <x-section-heading
            eyebrow="Внимание к деталям"
            title="Качество начинается задолго до того, как продукт окажется на столе"
            :lead="$qualityLead"
            tone="dark"
        />

        <ol class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($qualityItems as $item)
                <li class="flex h-full flex-col rounded-card border border-ink-inverse/15 bg-ink-inverse/5 p-6">
                    <p class="font-hand text-eyebrow leading-none text-accent-light">
                        {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                    </p>
                    <h3 class="mt-3 text-h3 text-ink-inverse">{{ $item['title'] }}</h3>
                    <p class="mt-3 text-small text-ink-inverse/75">{{ $item['text'] }}</p>
                </li>
            @endforeach
        </ol>

        <x-route-button
            tone="dark"
            label="Подробнее о качестве"
            route="quality"
            variant="secondary"
            class="mt-10"
        />
    </x-section>

    {{-- ========================== LIFESTYLE ========================== --}}
    {{--
        Намеренно не повторяет «О компании». Там симметрия 50/50, фотография
        слева в пропорциях 4/3 и текст с кнопкой. Здесь зеркальная расстановка
        (текст слева, фото справа), асимметрия 5/7 и широкое фото 16/9 —
        блок читается как полоса, а не как вторая копия соседней секции.
        CTA здесь намеренно нет.

        Исходник 842x380 (2.22:1) в боксе 16/9 только уменьшается (0.94x).
    --}}
    <x-section tone="canvas" spacing="default">
        <div class="grid gap-10 lg:grid-cols-12 lg:items-center lg:gap-16">
            <div class="lg:col-span-5">
                <x-section-heading
                    eyebrow="Вкус начинается с хороших продуктов"
                    title="То, что собирает нас за одним столом"
                    :lead="$lifestyleLead"
                />
            </div>

            <div class="lg:col-span-7">
                <x-media
                    src="{{ asset('images/04_family_lifestyle.jpg') }}"
                    alt="Семейная трапеза"
                    variant="lifestyle"
                    ratio="16/9"
                />
            </div>
        </div>
    </x-section>

    {{-- ======================== ДЛЯ БИЗНЕСА ========================= --}}
    <x-section tone="surface" spacing="default">
        <x-section-heading
            eyebrow="Для партнёров"
            title="Продукция для вашего бизнеса"
            :lead="$businessLeads[0]"
        />

        <p class="mt-4 text-lead text-ink-muted">{{ $businessLeads[1] }}</p>

        <ul class="mt-10 grid gap-6 md:grid-cols-3">
            @foreach ($businessItems as $item)
                <li class="flex h-full flex-col rounded-card border border-line bg-canvas p-6 shadow-soft lg:p-7">
                    <h3 class="text-h3 text-ink">{{ $item['title'] }}</h3>
                    <p class="mt-3 text-body text-ink-muted">{{ $item['text'] }}</p>
                </li>
            @endforeach
        </ul>

        <x-route-button
            label="Обсудить сотрудничество"
            route="contacts"
            class="mt-10"
        />
    </x-section>

    {{-- ========================== CTA ========================== --}}
    <x-section tone="canvas" spacing="loose">
        <div class="mx-auto max-w-3xl text-center">
            <x-section-heading
                eyebrow="Мы всегда на связи"
                title="Давайте знакомиться"
                :lead="$contactsLead"
                align="center"
            />

            <div class="mt-8 flex justify-center">
                <x-contacts class="items-center" />
            </div>

            <x-route-button
                label="Связаться с нами"
                route="contacts"
                class="mt-10"
            />
        </div>
    </x-section>
</x-layouts.app>
