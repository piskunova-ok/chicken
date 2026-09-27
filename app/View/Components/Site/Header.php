<?php

declare(strict_types=1);

namespace App\View\Components\Site;

use App\Support\SiteLinks;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Component;

/**
 * Шапка сайта: название компании, основная навигация, CTA и мобильное меню.
 *
 * Пункты, чей маршрут ещё не создан, выводятся неактивными (без ссылки) —
 * см. App\Support\SiteLinks.
 */
final class Header extends Component
{
    /**
     * @return array<int, array{label: string, url: string|null}>
     */
    public function navigation(): array
    {
        return SiteLinks::resolve(config('site.navigation'));
    }

    /**
     * @return array{label: string, url: string|null}|null
     */
    public function cta(): ?array
    {
        $cta = config('site.cta');

        if (! is_array($cta)) {
            return null;
        }

        return SiteLinks::resolve([$cta])[0];
    }

    public function render(): View
    {
        return view('components.site.header');
    }

    /**
     * Адрес главной страницы. Резервный вариант нужен на случай, если
     * маршрут home ещё не объявлен.
     */
    public function homeUrl(): string
    {
        return Route::has('home') ? route('home') : url('/');
    }

    /** Подсветка текущего раздела в навигации. */
    public function isActive(?string $url): bool
    {
        return $url !== null && $url === request()->url();
    }
}
