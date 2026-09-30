{{--
    СТРАНИЦА «КОНТАКТЫ» — /contacts.

    Контакты собраны в одном месте: телефон, почта, адрес и режим работы.
    Значения берутся из config/site.php и выводятся ровно так же, как в
    подвале, — через App\Support\ContactLinks.

    Рядом с контактами стоит форма обратной связи (x-contact-form). Она
    проверяет данные на сервере и возвращает посетителя на эту же
    страницу. Отправки пока нет: письма не формируются, заявка не пишется
    в базу — это промежуточный технический этап, о котором честно
    говорит и подпись кнопки, и сообщение после заполнения.

    Ничего не выдумано. Пока SITE_PHONE, SITE_EMAIL, SITE_ADDRESS и
    SITE_SCHEDULE не заданы, в конфиге лежат значения-заглушки
    ([ТЕЛЕФОН] / [EMAIL] / [АДРЕС] / [РЕЖИМ РАБОТЫ]). Компонент <x-contacts />
    и блок ниже показывают такие значения текстом, без ссылки: из
    [ТЕЛЕФОН] получился бы нерабочий tel:, а из [EMAIL] — нерабочий
    mailto:. Это осознанное поведение проекта, а не заглушка на скорую руку.

    Ссылка mailto:/tel: появится сама, как только владелец подставит
    реальные значения в .env — правки в этом шаблоне для этого не нужны.
--}}
@php
    use App\Support\ContactLinks;

    $contacts = (array) config('site.contacts', []);
    $business = (array) config('content.business', []);
    $address = ContactLinks::value('address');
    $schedule = ContactLinks::value('schedule');

    $breadcrumbs = [
        ['label' => 'Главная', 'url' => route('home')],
        ['label' => 'Контакты', 'url' => null],
    ];

    /*
     | Адрес и режим работы выводятся только если их значение присутствует.
     | При незаполненном конфиге строка целиком исчезает, а не остаётся
     | пустым отступом или надписью «Адрес уточняется»: такая надпись
     | была бы новым текстом, которого заказчик не утверждал.
     */
    $details = array_filter([
        ['label' => 'Адрес', 'value' => $address],
        ['label' => 'Режим работы', 'value' => $schedule],
    ], static fn (array $item): bool => filled($item['value']));
@endphp

<x-layouts.app
    :title="(config('content.contacts.page_title') ?? 'Контакты').' — '.config('site.name')"
    :description="config('content.contacts.meta_description')"
>
    {{-- ============================ HERO ============================ --}}
    {{--
        Контактный раздел начинается с крупной фотографии: она даёт странице
        тот же масштаб, что и остальные разделы, и не даёт выглядеть
        одиноким списком строк. Фото 04_family_lifestyle, пропорции 16/9.
    --}}
    <x-section tone="canvas" spacing="default">
        <x-breadcrumbs :items="$breadcrumbs" class="mb-10" />

        <div class="grid gap-10 lg:grid-cols-12 lg:items-center lg:gap-12">
            <div class="lg:col-span-5">
                <x-section-heading
                    :eyebrow="config('content.contacts.eyebrow')"
                    :title="config('content.contacts.page_title')"
                    :lead="config('content.contacts.lead')"
                    level="h1"
                />
            </div>

            <div class="lg:col-span-7">
                <x-media
                    src="{{ asset('images/04_family_lifestyle.jpg') }}"
                    alt="Семейная трапеза"
                    variant="lifestyle"
                    ratio="16/9"
                    priority
                />
            </div>
        </div>
    </x-section>

    {{-- ========================== КОНТАКТЫ ========================== --}}
    {{--
        Компонент x-contacts печатает телефон и почту: подтверждённые
        значения становятся ссылками tel:/mailto:, неподтверждённые
        остаются текстом с data-placeholder. Логика одна и та же для
        подвала, главной и этой страницы, потому что она живёт в
        App\Support\ContactLinks, а не в разметке.

        Раскладка двухколоночная: слева контакты и, если они заданы, адрес
        с режимом работы, справа форма. Раньше адрес занимал вторую
        колонку сам, и при незаполненных SITE_ADDRESS и SITE_SCHEDULE
        колонка просто исчезала; теперь её место занимает форма, которая
        нужна всегда. Заголовок формы — h2, поэтому на странице остаётся
        ровно один h1 из hero.
    --}}
    <x-section tone="surface" spacing="default">
        <div class="grid gap-10 lg:grid-cols-2 lg:items-start lg:gap-16">
            <div>
                <x-section-heading
                    eyebrow="Как с нами связаться"
                    title="Телефон и почта"
                    level="h2"
                />

                <div class="mt-8">
                    <x-contacts />
                </div>

                @if ($details !== [])
                    <div class="mt-12">
                        <x-section-heading
                            eyebrow="Где мы находимся"
                            title="Адрес и режим работы"
                            level="h2"
                        />

                        <dl class="mt-6 space-y-2 text-body">
                            @foreach ($details as $detail)
                                <div class="flex items-baseline justify-between gap-4 border-b border-line pb-4">
                                    <dt class="text-ink-muted">{{ $detail['label'] }}</dt>
                                    <dd
                                        @if (ContactLinks::isPlaceholder($detail['value'])) data-placeholder @endif
                                        class="text-right font-medium text-ink"
                                    >{{ $detail['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif
            </div>

            <x-contact-form />
        </div>
    </x-section>

    {{-- ======================== ДЛЯ БИЗНЕСА ========================= --}}
    {{--
        Заявка с этой страницы чаще всего приходит от организации, поэтому
        направления работы перечислены здесь же. Условия сотрудничества,
        цены и объёмы не заявлены: заказчик их не подтверждал.
    --}}
    <x-section tone="canvas" spacing="default">
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
    </x-section>

    {{-- ============================== CTA ============================ --}}
    {{--
        Ссылка на категорию, а не на эту же страницу: посетитель пришёл
        за контактами, но почти всегда интересуется и ассортиментом.
        Кнопка ведёт в каталог, где видно, о чём вообще можно спросить.
    --}}
    <x-section tone="surface" spacing="default">
        <div class="mx-auto max-w-3xl text-center">
            <x-section-heading
                eyebrow="Продукция"
                title="Посмотрите ассортимент"
                lead="Категории продукции, по которым можно задать вопрос."
                align="center"
            />

            <div class="mt-8 flex justify-center">
                <x-route-button
                    label="Перейти в каталог"
                    route="products.index"
                />
            </div>
        </div>
    </x-section>
</x-layouts.app>
