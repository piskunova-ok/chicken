@php
    $navigation = $navigation();
    $legalLinks = $legalLinks();
    $partners = $partners();
    $contacts = config('site.contacts');
    $phoneUrl = $phoneUrl();
    $emailUrl = $emailUrl();
@endphp

<footer class="mt-auto bg-primary-dark text-ink-inverse">
    <x-container>
        <div class="grid gap-12 py-16 md:grid-cols-2 lg:grid-cols-4 lg:gap-10">
            {{-- Компания --}}
            <div>
                <p class="text-body font-bold tracking-tight">{{ config('site.name') }}</p>
                <p class="mt-4 max-w-xs text-small text-ink-inverse/70">
                    {{ config('site.short_description') }}
                </p>
            </div>

            {{-- Разделы --}}
            <nav aria-label="Навигация в подвале">
                <h2 class="text-small font-semibold uppercase tracking-widest text-ink-inverse/60">
                    Разделы
                </h2>
                <ul class="mt-5 flex flex-col gap-3">
                    @foreach ($navigation as $item)
                        <li>
                            @if ($item['url'] !== null)
                                <a
                                    href="{{ $item['url'] }}"
                                    class="text-small text-ink-inverse/80 no-underline transition-colors duration-200 hover:text-ink-inverse"
                                >{{ $item['label'] }}</a>
                            @else
                                <span data-nav-pending class="text-small text-ink-inverse/60">
                                    {{ $item['label'] }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>

            {{-- Контакты --}}
            <div>
                <h2 class="text-small font-semibold uppercase tracking-widest text-ink-inverse/60">
                    Контакты
                </h2>
                <ul class="mt-5 flex flex-col gap-3 text-small">
                    <li>
                        @if ($phoneUrl !== null)
                            <a href="{{ $phoneUrl }}" class="text-ink-inverse/80 no-underline transition-colors duration-200 hover:text-ink-inverse">
                                {{ $contacts['phone'] }}
                            </a>
                        @else
                            {{-- Значение ещё не подтверждено — выводим текстом, без ссылки. --}}
                            <span data-placeholder class="text-ink-inverse/80">{{ $contacts['phone'] }}</span>
                        @endif
                    </li>
                    <li>
                        @if ($emailUrl !== null)
                            <a href="{{ $emailUrl }}" class="text-ink-inverse/80 no-underline transition-colors duration-200 hover:text-ink-inverse">
                                {{ $contacts['email'] }}
                            </a>
                        @else
                            <span data-placeholder class="text-ink-inverse/80">{{ $contacts['email'] }}</span>
                        @endif
                    </li>
                    <li class="text-ink-inverse/80">{{ $contacts['address'] }}</li>
                    <li class="text-ink-inverse/80">{{ $contacts['schedule'] }}</li>
                </ul>
            </div>

            {{-- Документы и партнёры --}}
            <div>
                <h2 class="text-small font-semibold uppercase tracking-widest text-ink-inverse/60">
                    Документы
                </h2>
                <ul class="mt-5 flex flex-col gap-3">
                    @foreach ($legalLinks as $item)
                        <li>
                            @if ($item['url'] !== null)
                                <a
                                    href="{{ $item['url'] }}"
                                    class="text-small text-ink-inverse/80 no-underline transition-colors duration-200 hover:text-ink-inverse"
                                >{{ $item['label'] }}</a>
                            @else
                                <span data-nav-pending class="text-small text-ink-inverse/60">
                                    {{ $item['label'] }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if ($partners !== [])
                    <h2 class="mt-8 text-small font-semibold uppercase tracking-widest text-ink-inverse/60">
                        Партнёры
                    </h2>
                    <ul class="mt-5 flex flex-col gap-3">
                        @foreach ($partners as $partner)
                            <li>
                                @if ($partner['url'] !== null)
                                    <a
                                        href="{{ $partner['url'] }}"
                                        rel="noopener noreferrer"
                                        target="_blank"
                                        class="text-small text-ink-inverse/80 no-underline transition-colors duration-200 hover:text-ink-inverse"
                                    >{{ $partner['label'] }}</a>
                                @else
                                    {{-- URL площадки ещё не задан: пункт зарезервирован, но неактивен. --}}
                                    <span data-partner-pending
                                          title="Ссылка будет добавлена позже"
                                          class="text-small text-ink-inverse/60">
                                        {{ $partner['label'] }}
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="flex flex-col gap-2 border-t border-white/10 py-6 text-small text-ink-inverse/60 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} {{ config('site.name') }}</p>
            <p>Все права защищены.</p>
        </div>
    </x-container>
</footer>
