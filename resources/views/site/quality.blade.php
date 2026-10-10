{{--
    СТРАНИЦА «КАЧЕСТВО» — /quality.

    Собирает на одном адресе уже утверждённый контент главной: вступление
    о простых продуктах и требованиях к качеству, лид блока «Качество» и
    четыре пункта этого блока. Набор утверждений не расширен: страница не
    говорит об органической продукции, отсутствии антибиотиков, конкретных
    сертификатах, ГОСТ или ISO, лабораторных показателях и государственных
    наградах, потому что подтверждённых данных такого рода в проекте нет.

    Все четыре пункта описывают отношение к процессу, а не свойства
    продукции с лабораторной точностью. В этом разница между «мы
    внимательны к требованиям к пищевой продукции» и «наша продукция
    сертифицирована»: первое подтверждено заказчиком, второе появиться не
    может.

    Композиция: hero 5/7 с фото → светлый блок вступления → тёмный блок
    четырёх пунктов (он же последний тёмный блок на главной) → широкий
    Lifestyle-блок как напоминание о зачем эта продукция → контактный CTA.
--}}
@php
    $quality = (array) config('content.quality', []);
    $intro = (array) config('content.intro', []);
    $lifestyle = (array) config('content.lifestyle', []);

    // Активные сертификаты приходят из QualityController. Если представление
    // рендерится напрямую (например, в изоляции), коллекция пустая и блок
    // не выводится — страница не падает и не показывает пустой заголовок.
    $certificates = $certificates ?? collect();

    $breadcrumbs = [
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Качество', 'url' => null],
    ];
@endphp

<x-layouts.app
    :title="($quality['page_title'] ?? 'Качество').' — '.\App\Support\Settings::value('company_name')"
    :description="$quality['meta_description'] ?? null"
>
    {{-- ============================ HERO ============================ --}}
    {{--
        Та же hero-фотография, что на главной и на /products, но здесь в
        квадратном боксе 1/1 — как на главной. На /products она уже стоит в
        16/9, поэтому три страницы подряд не показывают один и тот же кадр
        трижды. Исходник 1145x1374 (5:6) в 1/1 запрашивает около 8% высоты.
    --}}
    <x-section tone="canvas" spacing="default">
        <x-breadcrumbs :items="$breadcrumbs" class="mb-10" />

        <div class="grid gap-10 lg:grid-cols-12 lg:items-center lg:gap-12">
            <div class="lg:col-span-5">
                <x-section-heading
                    :eyebrow="$quality['eyebrow'] ?? null"
                    :title="$quality['page_title'] ?? 'Качество'"
                    :lead="$quality['lead'] ?? null"
                    level="h1"
                />
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

    {{-- ========================= ВСТУПЛЕНИЕ ========================= --}}
    {{--
        Тот же текст, что открывает главную. Повтор осмысленный: на главной
        он работает как вступление ко всему сразу, здесь — как объяснение,
        почему вообще есть такой раздел.
    --}}
    <x-section tone="surface" spacing="default">
        <x-section-heading
            :title="$intro['title'] ?? null"
            :lead="$intro['lead'] ?? null"
            align="center"
        />
    </x-section>

    {{-- =========================== ПУНКТЫ =========================== --}}
    {{--
        Тёмный блок с нумерацией 01—04. Разметка и классы совпадают с
        блоком «Качество» на главной: этот раздел — его полная версия, и
        оформлять его иначе значило бы завести на сайте два разных блока
        с одинаковым названием.
    --}}
    <x-section tone="dark" spacing="default">
        <x-section-heading
            eyebrow="Внимание к деталям"
            :title="$quality['title'] ?? null"
            :lead="$quality['lead'] ?? null"
            tone="dark"
        />

        <ol class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ((array) ($quality['items'] ?? []) as $item)
                <li class="flex h-full flex-col rounded-card border border-ink-inverse/15 bg-ink-inverse/5 p-6">
                    <p class="font-hand text-eyebrow leading-none text-accent-light">
                        {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                    </p>
                    <h2 class="mt-3 text-h3 text-ink-inverse">{{ $item['title'] }}</h2>
                    <p class="mt-3 text-small text-ink-inverse/75">{{ $item['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </x-section>

    {{-- ======================== СЕРТИФИКАТЫ ======================== --}}
    {{--
        Документы о качестве, загруженные владельцем в админке. Блок
        выводится, только когда есть хотя бы один активный документ: пустого
        заголовка «Сертификаты» на странице быть не должно, потому что до
        загрузки документов их у компании нет и утверждать обратное нельзя.

        Изображение (скан) показывается картинкой, PDF — плиткой со ссылкой:
        PDF браузер не отрисует как картинку, а открыть его посетитель должен
        одним кликом.
    --}}
    @if ($certificates->isNotEmpty())
        <x-section tone="surface" spacing="default">
            <x-section-heading
                eyebrow="Подтверждено документами"
                title="Сертификаты и документы"
                lead="Скан-копии документов, подтверждающих соответствие продукции."
                align="center"
            />

            <ul class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($certificates as $certificate)
                    <li class="flex h-full flex-col overflow-hidden rounded-card border border-line bg-canvas">
                        <a
                            href="{{ $certificate->fileUrl() }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="group flex h-full flex-col no-underline"
                        >
                            @if ($certificate->isPdf())
                                <span class="flex aspect-[3/4] w-full items-center justify-center bg-surface font-hand text-h2 text-primary">
                                    PDF
                                </span>
                            @else
                                <img
                                    src="{{ $certificate->fileUrl() }}"
                                    alt="{{ $certificate->title }}"
                                    loading="lazy"
                                    class="aspect-[3/4] w-full object-cover"
                                >
                            @endif

                            <span class="flex flex-1 flex-col p-5">
                                <span class="text-body font-semibold text-ink">{{ $certificate->title }}</span>
                                <span class="mt-2 text-small text-primary transition-colors duration-200 group-hover:text-primary-dark">
                                    Открыть документ
                                </span>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-section>
    @endif

    {{-- ========================= LIFESTYLE ========================== --}}
    {{--
        Светлый блок между тёмным и CTA: он возвращает разговор из
        «контроля и требований» в плоскость повседневного применения.
        Текст общий с главной, фотография здесь не повторяет hero.
    --}}
    <x-section tone="canvas" spacing="default">
        <div class="grid gap-10 lg:grid-cols-12 lg:items-center lg:gap-16">
            <div class="lg:col-span-7">
                <x-media
                    src="{{ asset('images/05_cooking_lifestyle.jpg') }}"
                    alt="Приготовление блюда из продуктов"
                    variant="lifestyle"
                    ratio="16/9"
                />
            </div>

            <div class="lg:col-span-5">
                <x-section-heading
                    :eyebrow="$lifestyle['eyebrow'] ?? null"
                    :title="$lifestyle['title'] ?? null"
                    :lead="$lifestyle['lead'] ?? null"
                />
            </div>
        </div>
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
