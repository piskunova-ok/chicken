<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Разрешает элементы навигации из config/site.php в готовые ссылки.
 *
 * Пункт, для которого маршрут ещё не создан, возвращается с 'url' => null.
 * Шаблоны в этом случае выводят его неактивным элементом без ссылки —
 * без «битых» ссылок на несуществующие страницы и без затрагивания
 * разметки, когда маршрут появится.
 */
final class SiteLinks
{
    /**
     * @param  array<int, array{label: string, route?: string|null, url?: string|null}>  $items
     * @return array<int, array{label: string, url: string|null}>
     */
    public static function resolve(array $items): array
    {
        return array_map(static function (array $item): array {
            $route = $item['route'] ?? null;

            return [
                'label' => $item['label'],
                'url' => is_string($route) && Route::has($route) ? route($route) : null,
            ];
        }, $items);
    }

    /**
     * @param  array<int, array{label: string, url?: string|null}>  $items
     * @return array<int, array{label: string, url: string|null}>
     */
    public static function resolveExternal(array $items): array
    {
        return array_map(static fn (array $item): array => [
            'label' => $item['label'],
            'url' => $item['url'] ?? null,
        ], $items);
    }
}
