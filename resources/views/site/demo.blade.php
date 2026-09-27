{{--
    ВРЕМЕННАЯ DEMO-СТРАНИЦА ЭТАПА 2.

    Служит только для визуальной проверки базовой дизайн-системы:
    палитры, типографики, компонентов, отступов и состояний.
    Это не главная страница сайта и не его реальный раздел —
    страница будет удалена вместе с подтверждением этапа.
--}}
@php
    $palette = [
        ['token' => 'canvas', 'hex' => '#F5F1E8', 'swatch' => 'bg-canvas', 'usage' => 'Основной фон страницы'],
        ['token' => 'surface', 'hex' => '#FAF8F2', 'swatch' => 'bg-surface', 'usage' => 'Карточки, поля, панели'],
        ['token' => 'primary', 'hex' => '#52634A', 'swatch' => 'bg-primary', 'usage' => 'Кнопки, активные ссылки'],
        ['token' => 'primary-dark', 'hex' => '#354438', 'swatch' => 'bg-primary-dark', 'usage' => 'Ховер кнопки, подвал'],
        ['token' => 'accent', 'hex' => '#8A6548', 'swatch' => 'bg-accent', 'usage' => 'Eyebrow, мелкие акценты'],
        ['token' => 'accent-light', 'hex' => '#D2B694', 'swatch' => 'bg-accent-light', 'usage' => 'Eyebrow на тёмном фоне'],
        ['token' => 'ink', 'hex' => '#292D29', 'swatch' => 'bg-ink', 'usage' => 'Основной текст'],
        ['token' => 'ink-muted', 'hex' => '#6E6A60', 'swatch' => 'bg-ink-muted', 'usage' => 'Вторичный текст, подписи'],
        ['token' => 'line', 'hex' => '#DED7C8', 'swatch' => 'bg-line', 'usage' => 'Разделители, границы'],
    ];

    $scale = [
        ['token' => 'display', 'class' => 'text-display', 'size' => '34–56px', 'note' => 'Заголовок главной страницы'],
        ['token' => 'h2', 'class' => 'text-h2', 'size' => '28–40px', 'note' => 'Заголовки секций'],
        ['token' => 'h3', 'class' => 'text-h3', 'size' => '20–24px', 'note' => 'Подзаголовки, названия карточек'],
        ['token' => 'lead', 'class' => 'text-lead', 'size' => '17–19px', 'note' => 'Вводный текст секции'],
        ['token' => 'body', 'class' => 'text-body', 'size' => '16px', 'note' => 'Основной текст'],
        ['token' => 'small', 'class' => 'text-small', 'size' => '14px', 'note' => 'Подписи, сноски, навигация'],
    ];
@endphp

<x-layouts.app title="Демо дизайн-системы — [НАЗВАНИЕ КОМПАНИИ]">
    {{-- Предупреждение о временном характере страницы --}}
    <div class="border-b border-line bg-accent/10">
        <x-container>
            <div class="flex flex-col gap-1 py-4 text-small sm:flex-row sm:items-center sm:gap-3">
                <x-eyebrow text="Временная страница" class="text-eyebrow" />
                <p class="text-ink-muted">
                    Проверка базовой дизайн-системы. Разделы сайта пока не созданы —
                    пункты меню без ссылки отмечены серым.
                </p>
            </div>
        </x-container>
    </div>

    {{-- Палитра --}}
    <x-container class="py-16 lg:py-20">
        <x-section-heading
            eyebrow="Палитра"
            title="Цветовые токены"
            lead="Все цвета заданы один раз в Tailwind @theme и используются в разметке только по имени токена. Hex-значения в шаблонах не дублируются."
        />

        <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($palette as $color)
                <li class="overflow-hidden rounded-card border border-line bg-surface shadow-soft">
                    <div class="h-24 w-full {{ $color['swatch'] }}"></div>
                    <div class="p-5">
                        <p class="font-mono text-small font-semibold text-ink">{{ $color['token'] }}</p>
                        <p class="mt-1 font-mono text-small text-ink-muted">{{ $color['hex'] }}</p>
                        <p class="mt-2 text-small text-ink-muted">{{ $color['usage'] }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-container>

    {{-- Типографика --}}
    <x-container class="pb-16 lg:pb-20">
        <x-section-heading
            eyebrow="Типографика"
            title="Шрифты и размеры"
            lead="Manrope используется во всём интерфейсе. Caveat — только в коротких рукописных акцентах и никогда в основном тексте."
        />

        <div class="mt-10 flex flex-col divide-y divide-line overflow-hidden rounded-card border border-line bg-surface">
            @foreach ($scale as $level)
                <div class="grid gap-2 p-6 sm:grid-cols-[9rem_1fr] sm:gap-6">
                    <div class="text-small text-ink-muted">
                        <p class="font-mono font-semibold text-ink">{{ $level['token'] }}</p>
                        <p>{{ $level['size'] }}</p>
                    </div>
                    <div>
                        <p class="{{ $level['class'] }} text-ink">{{ $level['note'] }}</p>
                        <p class="mt-2 text-small text-ink-muted">
                            Aa Bb Cc Dd Ee Ff Gg — 1234567890
                        </p>
                    </div>
                </div>
            @endforeach

            <div class="grid gap-2 p-6 sm:grid-cols-[9rem_1fr] sm:gap-6">
                <div class="text-small text-ink-muted">
                    <p class="font-mono font-semibold text-ink">eyebrow</p>
                    <p>20–24px</p>
                </div>
                <div>
                    <x-eyebrow text="Свежие яйца каждый день" />
                    <p class="mt-2 text-small text-ink-muted">
                        Caveat, только акцент — 20–24px, цвет accent.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-6 rounded-card border border-line bg-surface p-6">
            <p class="text-small font-semibold text-ink">Проверка кириллицы</p>
            <p class="mt-2 text-body text-ink">
                Фывапролджэъщыёукенгщцйхъёфывапролджэъщыёу — «Ёлка», ёжик, объявление, подъезд, съёмка.
            </p>
            <p class="mt-1 text-small text-ink-muted">
                Если подпись выше отображается Manrope, кириллический сабсет подключён корректно.
            </p>
        </div>
    </x-container>

    {{-- Компоненты --}}
    <x-container class="pb-16 lg:pb-20">
        <x-section-heading
            eyebrow="Компоненты"
            title="Переиспользуемые элементы"
            lead="Четыре базовых компонента покрывают задачи вёрстки всех будущих разделов сайта."
        />

        <div class="mt-10 grid gap-6 lg:grid-cols-2">
            <div class="rounded-card border border-line bg-surface p-8">
                <x-eyebrow text="Eyebrow" class="mb-4" />
                <h3 class="text-h3 text-ink">Короткий акцент перед заголовком</h3>
                <p class="mt-4 text-body text-ink-muted">
                    Плейсхолдер содержимого. Показывает, как выглядит лид-абзац в паре с рукописным
                    акцентом и заголовком третьего уровня.
                </p>
            </div>

            <div class="rounded-card border border-line bg-surface p-8">
                <x-section-heading
                    eyebrow="SectionHeading"
                    title="Заголовок секции"
                    lead="Компонент принимает уровень, выравнивание и тон, чтобы не дублировать разметку на разных страницах."
                    align="center"
                />
            </div>

            <div class="rounded-card border border-line bg-surface p-8">
                <p class="text-small font-semibold text-ink">Button</p>
                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <x-button label="Основная" />
                    <x-button label="Контурная" variant="secondary" />
                    <x-button label="Недоступно" disabled />
                </div>
                <p class="mt-5 text-small text-ink-muted">
                    <x-button label="Ссылка" href="#button" variant="secondary" class="px-4 py-2 text-small" />
                    рендерится как &lt;a&gt; и ведёт к секции Button.
                </p>
            </div>

            <div class="rounded-card border border-line bg-surface p-8">
                <p class="text-small font-semibold text-ink">Container</p>
                <p class="mt-2 text-small text-ink-muted">
                    Горизонтальные отступы: 20px на мобильных, 32px на планшетах, 48px на широких экранах.
                    Максимальная ширина контента — 1216px.
                </p>
                <div class="mt-6 space-y-3">
                    <div class="h-2 w-full rounded-full bg-primary/25"></div>
                    <div class="h-2 w-3/4 rounded-full bg-primary/25"></div>
                    <div class="h-2 w-1/2 rounded-full bg-primary/25"></div>
                </div>
            </div>
        </div>
    </x-container>

    {{-- Тёмная секция: проверка контраста --}}
    <div class="bg-primary-dark">
        <x-container class="py-16 lg:py-20">
            <x-section-heading
                eyebrow="Тёмная секция"
                title="Проверка контраста"
                lead="На тёмно-зелёном фоне коричневый акцент не читается, поэтому используется осветлённый вариант. Основной текст — светлый."
                tone="dark"
                align="center"
            />

            <div class="mt-10 flex flex-wrap items-center justify-center gap-3">
                <x-button label="Основная" class="bg-ink-inverse text-primary-dark hover:bg-surface" />
                <x-button label="Контурная" variant="secondary" class="border-ink-inverse/40 text-ink-inverse hover:border-ink-inverse hover:bg-ink-inverse hover:text-primary-dark" />
            </div>
        </x-container>
    </div>

    {{-- Скругления, тени, отступы --}}
    <x-container class="py-16 lg:py-20">
        <x-section-heading
            eyebrow="Форма"
            title="Скругления, тени и отступы"
            lead="Умеренные скругления, одна мягкая тень и вертикальный ритм на кратных 4px."
        />

        <div class="mt-10 grid gap-6 sm:grid-cols-3">
            <div class="rounded-control border border-line bg-surface p-5">
                <p class="text-small font-semibold">control · 8px</p>
                <p class="mt-2 text-small text-ink-muted">Кнопки, поля ввода, мелкие элементы.</p>
            </div>
            <div class="rounded-card border border-line bg-surface p-5">
                <p class="text-small font-semibold">card · 14px</p>
                <p class="mt-2 text-small text-ink-muted">Карточки, панели, блоки.</p>
            </div>
            <div class="rounded-media border border-line bg-surface p-5">
                <p class="text-small font-semibold">media · 18px</p>
                <p class="mt-2 text-small text-ink-muted">Изображения и видео.</p>
            </div>
        </div>

        <div class="mt-6 rounded-card border border-line bg-surface p-5">
            <p class="text-small font-semibold">shadow-soft</p>
            <p class="mt-2 text-small text-ink-muted">
                Единственная тень в системе — для лёгкого отделения карточки от фона.
            </p>
        </div>
    </x-container>
</x-layouts.app>
