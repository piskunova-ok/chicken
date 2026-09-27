<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Support\SiteLinks;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Кнопка-ссылка, которая не ведёт на 404.
 *
 * Если маршрут ещё не создан, кнопка выводится в неактивном состоянии
 * (data-nav-pending) вместо ссылки. Как только маршрут появится в
 * routes/web.php, кнопка станет обычной ссылкой без изменения вёрстки.
 *
 * Для обычных переходов (например, на якорь секции) используйте $href.
 */
final class RouteButton extends Component
{
    public function __construct(
        public readonly string $label = '',
        public readonly ?string $route = null,
        public readonly ?string $href = null,
        public readonly string $variant = 'primary',
        public readonly string $pendingTitle = 'Раздел готовится',
        /** light | dark — пробрасывается в Button для тёмных секций. */
        public readonly string $tone = 'light',
    ) {}

    public function resolvedHref(): ?string
    {
        if ($this->href !== null) {
            return $this->href;
        }

        if ($this->route === null) {
            return null;
        }

        return SiteLinks::resolveOne(['label' => $this->label, 'route' => $this->route])['url'];
    }

    public function isPending(): bool
    {
        return $this->resolvedHref() === null;
    }

    public function render(): View
    {
        return view('components.route-button');
    }
}
