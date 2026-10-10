<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/**
 * Карта сайта для поисковых систем: /sitemap.xml
 *
 * ГРАНИЦА ОТВЕТСТВЕННОСТИ
 *
 * В карту попадают только те адреса, которые посетитель действительно может
 * открыть и которые предназначены для индексации. Список собирается из
 * двух уже существующих источников истины, а не из нового перечня страниц:
 *
 * 1. Постоянные страницы — из config/site.php (navigation и
 *    footer_navigation). Это ровно те адреса, на которые ведут ссылки в
 *    шапке и подвале. Отдельный список страниц здесь означал бы вторую
 *    правду о сайте: добавили пункт в меню, а в карту он не попал.
 *
 * 2. Страницы категорий — из config/site.php (products) и из базы. В карту
 *    попадают только адреса активных категорий: известных (eggs, chicken),
 *    чьи маршруты живут в конфиге, и добавленных в админке, живущих по
 *    общему маршруту products.category. Правило ровно то же, что на /products
 *    и в ProductCategoryController: неактивная категория для посетителя не
 *    существует, её страница отдаёт 404, и адрес такой страницы в карте был
 *    бы обещанием, которое сайт не выполняет.
 *
 * ЧТО В КАРТУ НЕ ПОПАДАЕТ И ПОЧЕМУ
 *
 * - /admin и любые служебные адреса Filament: закрыты в robots.txt, в
 *   индексации им не место. В карту они не попадают потому, что в неё
 *   попадают только пункты навигации и активные категории — ни один из
 *   этих источников про админку не знает.
 * - POST /contacts: адрес формы не является страницей, у него нет GET-версии.
 * - Ошибки 404 и любые несуществующие адреса: карта описывает существующие
 *   страницы, а не все возможные.
 * - Юридические страницы (legal.*): маршрутов для них пока нет, поэтому
 *   проверка Route::has() отбрасывает их молча. Появятся маршруты — пункты
 *   появятся в карте сами.
 *
 * АДРЕСА В КАРТЕ
 *
 * Собираются из config('app.url'), а не из текущего запроса. Разница
 * принципиальна: карту читают роботы, и запрос может прийти на localhost,
 * на IP-адрес или на служебный поддомен. URL, собранный из запроса, попал бы
 * в карту вместе с таким хостом, и поисковик увидел бы несуществующие
 * адреса. APP_URL — единственное место, где домен сайта назван один раз, и
 * на сервере он обязан быть настоящим.
 *
 * lastmod не выводится намеренно: дата изменения каждой из этих страниц не
 * подтверждена, а дата, придуманная из времени коммита или из updated_at
 * категории, ввела бы в заблуждение и перезапускала бы переобход без
 * причины.
 */
final class SitemapController extends Controller
{
    /**
     * Карта сайта.
     *
     * Тип ответа — Response, а не View: карта обязана уйти с заголовком
     * Content-Type application/xml, а обычный view-ответ отдал бы text/html,
     * и часть роботов отклонила бы документ.
     */
    public function index(): Response
    {
        return response()
            ->view('sitemap', [
                'urls' => $this->urls(),
            ], 200, [
                'Content-Type' => 'application/xml; charset=UTF-8',
            ]);
    }

    /**
     * Абсолютные адреса индексируемых страниц.
     *
     * @return array<int, string>
     */
    private function urls(): array
    {
        $root = rtrim((string) config('app.url'), '/');

        $paths = [];

        /*
         * Ключом служит путь страницы (а не имя маршрута), поэтому один и
         * тот же адрес, попавший в оба источника, добавляется один раз.
         * Порядок при этом сохраняется первый встреченный: home, /products,
         * /quality, /about, /contacts, затем страницы категорий.
         */
        foreach ($this->staticPages() as $routeName) {
            $path = route($routeName, [], false);

            $paths[$path] = $path;
        }

        foreach ($this->activeCategoryPages() as $path) {
            $paths[$path] = $path;
        }

        return array_map(
            static fn (string $path): string => $root.$path,
            array_values($paths),
        );
    }

    /**
     * Имена маршрутов постоянных страниц из навигации сайта.
     *
     * @return array<int, string>
     */
    private function staticPages(): array
    {
        $items = array_merge(
            (array) config('site.navigation', []),
            (array) config('site.footer_navigation', []),
        );

        $names = [];

        foreach ($items as $item) {
            $routeName = is_array($item) ? ($item['route'] ?? null) : null;

            // Пункт без маршрута или с ещё не созданным маршрутом в карту не
            // попадает: адрес был бы либо пустым, либо ведущим на 404.
            if (! is_string($routeName) || ! Route::has($routeName)) {
                continue;
            }

            $names[] = $routeName;
        }

        return array_values(array_unique($names));
    }

    /**
     * Пути страниц активных категорий.
     *
     * @return array<int, string>
     */
    private function activeCategoryPages(): array
    {
        /*
         * Активные категории одним запросом, в том же порядке, что и на
         * /products: выдача страницы и выдача карты не должны расходиться.
         */
        $active = ProductCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $configProducts = (array) config('site.products', []);

        $paths = [];

        foreach ($active as $category) {
            $config = $configProducts[$category->slug] ?? null;

            if (is_array($config)) {
                /*
                 * Известная категория: адрес из имени маршрута в конфиге.
                 */
                $routeName = $config['route'] ?? null;

                if (! is_string($routeName) || ! Route::has($routeName)) {
                    continue;
                }

                $paths[] = route($routeName, [], false);

                continue;
            }

            /*
             * Категория из админки: общий маршрут products.category.
             */
            $paths[] = route('products.category', ['categorySlug' => $category->slug], false);
        }

        return array_values(array_unique($paths));
    }
}
