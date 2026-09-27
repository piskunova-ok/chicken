{{--
    RouteButton: ссылка-кнопка, которая никогда не ведёт на 404.

    Маршрут создан  -> обычная кнопка-ссылка.
    Маршрут не создан -> неактивная кнопка с подсказкой «Раздел готовится».
--}}
@if ($isPending())
    <x-button
        {{ $attributes->merge(['class' => 'cursor-not-allowed']) }}
        :label="$label"
        :variant="$variant"
        :tone="$tone"
        disabled
        data-nav-pending
        :title="$pendingTitle"
    />
@else
    <x-button
        {{ $attributes }}
        :label="$label"
        :href="$resolvedHref()"
        :variant="$variant"
        :tone="$tone"
    />
@endif
