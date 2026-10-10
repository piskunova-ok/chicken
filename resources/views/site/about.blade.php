{{--
    СТРАНИЦА «О КОМПАНИИ» — /about.

    Собирает на одном адресе уже утверждённый контент главной: блок
    «О компании», блок про повседневное применение продукции (Lifestyle) и
    направления работы (Для бизнеса). Ни одного нового фактического
    утверждения страница не добавляет — истории компании, дат основания,
    объёмов производства, численности сотрудников, географии поставок и
    наград у проекта нет, и на этой странице их тоже нет.

    Название компании не придумывается: оно берётся из config/content.php,
    где стоит то же env('SITE_NAME', '[НАЗВАНИЕ КОМПАНИИ]'), что и в
    config/site.php. При незаполненном SITE_NAME в абзацах останется
    понятная заглушка — ровно как на главной.

    Композиция зеркалит главную, но в обратном порядке и с другими
    пропорциями, чтобы страница не читалась копией: hero 5/7 с фото,
    затем абзацы, затем широкий Lifestyle 16/9, затем три направления
    для бизнеса, затем контактный CTA.
--}}
@php
    $about = (array) config('content.about', []);
    $lifestyle = (array) config('content.lifestyle', []);
    $business = (array) config('content.business', []);

    $breadcrumbs = [
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'О компании', 'url' => null],
    ];
@endphp

<x-layouts.app
    :title="($about['page_title'] ?? 'О компании').' — '.\App\Support\Settings::value('company_name')"
    :description="$about['meta_description'] ?? null"
>
    {{-- ============================ HERO ============================ --}}
    {{--
        Фотография здесь другая, чем на главной (05_cooking_lifestyle, а не
        hero-фото): на главной она уже занята блоком «О компании», и здесь
        та же фотография в том же боксе выглядела бы повтором с шапкой
        из страницы. Бокс 4/3 подходит под исходник 688x380 без растяжения.
    --}}
    <x-section tone="canvas" spacing="default">
        <x-breadcrumbs :items="$breadcrumbs" class="mb-10" />

        <div class="grid gap-10 lg:grid-cols-12 lg:items-center lg:gap-12">
            <div class="lg:col-span-7">
                <x-media
                    src="{{ asset('images/05_cooking_lifestyle.jpg') }}"
                    alt="Приготовление блюда из продуктов"
                    variant="lifestyle"
                    ratio="4/3"
                    priority
                />
            </div>

            <div class="lg:col-span-5">
                <x-section-heading
                    :eyebrow="$about['eyebrow'] ?? null"
                    :title="$about['page_title'] ?? 'О компании'"
                    :lead="config('content.intro.lead')"
                    level="h1"
                />
            </div>
        </div>
    </x-section>

    {{-- ==================== РАССКАЗ О КОМПАНИИ ===================== --}}
    {{--
        Та же асимметрия 50/50, что на главной, но зеркальная: текст слева,
        фото справа. Фото 04_family_lifestyle здесь, а не на главной, — на
        главной она принадлежит блоку Lifestyle, и в разделе «О компании» ей
        не место.
    --}}
    <x-section tone="surface" spacing="default">
        <div class="grid gap-10 lg:grid-cols-2 lg:items-center lg:gap-16">
            <div class="order-2 lg:order-1">
                <x-section-heading
                    :title="$about['title'] ?? null"
                />

                <div class="mt-6 space-y-4 text-body text-ink-muted">
                    @foreach ((array) ($about['paragraphs'] ?? []) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            </div>

            <div class="order-1 lg:order-2">
                <x-media
                    src="{{ asset('images/04_family_lifestyle.jpg') }}"
                    alt="Семейная трапеза"
                    variant="lifestyle"
                    ratio="4/3"
                />
            </div>
        </div>
    </x-section>

    {{-- ========================= LIFESTYLE ========================== --}}
    {{--
        Текст блока тот же, что на главной, поэтому здесь он подан как
        продолжение рассказа о компании, а не как отдельная тема. Пропорции
        16/9 — исходник 842x380 (2.22:1) только уменьшается.
    --}}
    <x-section tone="canvas" spacing="default">
        <div class="grid gap-10 lg:grid-cols-12 lg:items-center lg:gap-16">
            <div class="lg:col-span-5">
                <x-section-heading
                    :eyebrow="$lifestyle['eyebrow'] ?? null"
                    :title="$lifestyle['title'] ?? null"
                    :lead="$lifestyle['lead'] ?? null"
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
            :eyebrow="$business['eyebrow'] ?? null"
            :title="$business['title'] ?? null"
            :lead="$business['leads'][0] ?? null"
        />

        <p class="mt-4 text-lead text-ink-muted">{{ $business['leads'][1] ?? null }}</p>

        <ul class="mt-10 grid gap-6 md:grid-cols-3">
            @foreach ((array) ($business['items'] ?? []) as $item)
                <li class="flex h-full flex-col rounded-card border border-line bg-canvas p-6 shadow-soft lg:p-7">
                    <h2 class="text-h3 text-ink">{{ $item['title'] }}</h2>
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
