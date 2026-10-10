<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use App\Support\SiteLinks;
use Illuminate\Contracts\View\View;

/**
 * Контроллер страницы «Продукция» — /products.
 *
 * ГРАНИЦА ОТВЕТСТВЕННОСТИ
 *
 * Какие категории существуют — решает база: неактивная категория для
 * посетителя не существует. Откуда берутся данные карточки — зависит от
 * категории:
 *
 *  - «известная» категория (eggs, chicken) есть и в базе, и в
 *    config/site.php: данные карточки (фото, описание, подпись кнопки,
 *    имя маршрута) приходят из конфига, как и раньше;
 *  - категория, добавленная владельцем в админке, в конфиге отсутствует:
 *    карточка строится из записи базы (название, описание из колонки
 *    description), без фотографии и с подписью кнопки по умолчанию.
 *
 * Так администратор может отключить категорию — она исчезнет с обзорной
 * страницы, а маршрут /products/{slug} останется зарегистрированным и сам
 * отдаст 404. И наоборот: новая категория появляется на /products сразу
 * после создания, не дожидаясь правок конфига.
 *
 * Здесь нет ничего, кроме одного запроса и одной сборки массива. Отдельный
 * сервис поверх этого означал бы класс ради склейки: при появлении третьего
 * источника (например, страниц отдельных товаров) выносить имеет смысл.
 */
final class ProductIndexController extends Controller
{
    /**
     * Обзорная страница продукции со списком категорий.
     */
    public function index(): View
    {
        /*
         * Активные категории одним запросом, в порядке sort_order. Порядок
         * двухчастный по той же причине, что и на странице категории: id
         * стабилизирует выдачу, когда у нескольких категорий совпал
         * sort_order.
         */
        $active = ProductCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $configProducts = (array) config('site.products', []);

        $categories = [];

        foreach ($active as $category) {
            $config = $configProducts[$category->slug] ?? null;

            if (is_array($config)) {
                /*
                 * «Известная» категория: данные карточки из конфига, а URL
                 * разрешает SiteLinks — если маршрут когда-нибудь исчезнет,
                 * карточка останется, но без ссылки (без ведения в 404).
                 */
                $categories[] = $config + [
                    'slug' => $category->slug,
                    'url' => SiteLinks::resolveOne([
                        'label' => $config['name'] ?? $category->name,
                        'route' => $config['route'] ?? null,
                    ])['url'],
                ];

                continue;
            }

            /*
             * Категория из админки: фото ещё не загружено (карточка его
             * просто не показывает), адрес — общий маршрут products.category,
             * а подпись кнопки — стандартная для каталога.
             */
            $categories[] = [
                'slug' => $category->slug,
                'name' => $category->name,
                'description' => $category->description,
                'image' => null,
                'image_alt' => null,
                'cta' => 'Смотреть продукцию',
                'route' => 'products.category',
                'url' => route('products.category', ['categorySlug' => $category->slug]),
            ];
        }

        return view('site.products.index', [
            'categories' => $categories,
        ]);
    }
}
