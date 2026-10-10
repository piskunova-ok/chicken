@php
    use App\Support\Settings;

    $navigation = $navigation();
    $cta = $cta();
@endphp

<header class="sticky top-0 z-50 border-b border-line bg-canvas/90 backdrop-blur-sm">
    <x-container>
        <div class="flex min-h-20 items-center justify-between gap-6 py-4">
            <a
                href="{{ $homeUrl() }}"
                class="text-body font-bold tracking-tight text-ink no-underline transition-colors duration-200 hover:text-primary"
            >
                {{ Settings::value('company_name') }}
            </a>

            <nav class="hidden lg:block" aria-label="Основная навигация">
                <ul class="flex items-center gap-8">
                    @foreach ($navigation as $item)
                        <li>
                            @if ($item['url'] !== null)
                                <a
                                    href="{{ $item['url'] }}"
                                    @if ($isActive($item['url'])) aria-current="page" @endif
                                    @class([
                                        'text-small font-medium no-underline transition-colors duration-200 hover:text-primary',
                                        'text-ink' => ! $isActive($item['url']),
                                        'text-primary' => $isActive($item['url']),
                                    ])
                                    @if ($isActive($item['url'])) aria-underline-offset-4 underline @endif
                                >{{ $item['label'] }}</a>
                            @else
                                {{-- Раздел ещё не создан: показываем без ссылки, чтобы не вести в 404. --}}
                                <span
                                    data-nav-pending
                                    title="Раздел появится позже"
                                    class="cursor-default text-small font-medium text-ink-muted"
                                >{{ $item['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="hidden lg:block">
                @if ($cta !== null)
                    @if ($cta['url'] !== null)
                        <x-button :label="$cta['label']" :href="$cta['url']" class="px-5 py-3" />
                    @else
                        <x-button :label="$cta['label']" disabled />
                    @endif
                @endif
            </div>

            <button
                type="button"
                data-menu-toggle
                aria-expanded="false"
                aria-controls="site-mobile-menu"
                class="inline-flex size-11 items-center justify-center rounded-control text-ink transition-colors duration-200 hover:bg-primary/10 lg:hidden"
            >
                <span class="sr-only">Открыть меню</span>
                <svg
                    data-menu-icon="open"
                    class="size-6"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.75"
                    stroke-linecap="round"
                    aria-hidden="true"
                >
                    <path d="M4 7h16M4 12h16M4 17h16" />
                </svg>
                <svg
                    data-menu-icon="close"
                    class="hidden size-6"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.75"
                    stroke-linecap="round"
                    aria-hidden="true"
                >
                    <path d="M6 6l12 12M18 6L6 18" />
                </svg>
            </button>
        </div>
    </x-container>

    <div
        id="site-mobile-menu"
        data-menu
        hidden
        class="border-t border-line bg-canvas lg:hidden"
    >
        <x-container>
            <nav class="py-6" aria-label="Мобильная навигация">
                <ul class="flex flex-col gap-1">
                    @foreach ($navigation as $item)
                        <li>
                            @if ($item['url'] !== null)
                                <a
                                    href="{{ $item['url'] }}"
                                    @if ($isActive($item['url'])) aria-current="page" @endif
                                    @class([
                                        'block rounded-control px-4 py-3 text-body font-medium no-underline transition-colors duration-200',
                                        'text-ink hover:bg-primary/10' => ! $isActive($item['url']),
                                        'bg-primary/10 text-primary' => $isActive($item['url']),
                                    ])
                                >{{ $item['label'] }}</a>
                            @else
                                <span
                                    data-nav-pending
                                    class="block cursor-default rounded-control px-4 py-3 text-body font-medium text-ink-muted"
                                >{{ $item['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if ($cta !== null)
                    <div class="mt-6 px-4">
                        @if ($cta['url'] !== null)
                            <x-button :label="$cta['label']" :href="$cta['url']" class="w-full" />
                        @else
                            <x-button :label="$cta['label']" disabled class="w-full" />
                        @endif
                    </div>
                @endif
            </nav>
        </x-container>
    </div>
</header>
