<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Тесты страницы категории, собранной из данных базы.
 *
 * Ключевая проверка файла — test_a_product_renamed_in_the_database_appears_on_the_page_while_config_stays_untouched:
 * она меняет название товара прямо в базе и убеждается, что страница
 * показывает новое название, хотя config/catalog.php об этом не знает.
 * Без неё остальные проверки «200 OK» и «7 карточек» были бы совместимы и
 * со старым чтением из конфига.
 *
 * RefreshDatabase работает на SQLite :memory: из phpunit.xml, поэтому тесты
 * не трогают рабочую базу database/database.sqlite.
 */
final class ProductCategoryPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Порядок товаров мяса кур: он задан полем sort_order.
     *
     * @var list<string>
     */
    private const CHICKEN_ORDER = [
        'Тушка курицы',
        'Окорочка',
        'Куриные бёдра',
        'Куриные сердца',
        'Куриная печень',
        'Суповые наборы',
        'Другие продукты',
    ];

    /**
     * Временные позиции яиц в порядке sort_order.
     *
     * @var list<string>
     */
    private const EGGS_ORDER = [
        'Вариант продукции 01',
        'Вариант продукции 02',
        'Вариант продукции 03',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductCatalogSeeder::class);
    }

    // ------------------------------------------------------------------
    // 1—2. Страницы отвечают 200
    // ------------------------------------------------------------------

    public function test_the_eggs_page_responds_with_200(): void
    {
        $this->get('/products/eggs')->assertOk();
    }

    public function test_the_chicken_page_responds_with_200(): void
    {
        $this->get('/products/chicken')->assertOk();
    }

    // ------------------------------------------------------------------
    // 3—5. Состав и порядок карточек
    // ------------------------------------------------------------------

    public function test_the_chicken_page_shows_seven_active_products(): void
    {
        $response = $this->get('/products/chicken')->assertOk();

        $this->assertSame(7, $this->cardCount($response->getContent()));
        $response->assertSeeInOrder(self::CHICKEN_ORDER);
    }

    public function test_the_eggs_page_shows_three_products(): void
    {
        $response = $this->get('/products/eggs')->assertOk();

        $this->assertSame(3, $this->cardCount($response->getContent()));
        $response->assertSeeInOrder(self::EGGS_ORDER);
    }

    public function test_cards_follow_the_sort_order_column(): void
    {
        // Порядок задаём в базе вразнобой: страница обязана вернуть его по
        // sort_order, а не в порядке вставки и не в порядке id.
        foreach (self::CHICKEN_ORDER as $index => $name) {
            Product::where('name', $name)->update(['sort_order' => $index + 1]);
        }

        $this->get('/products/chicken')
            ->assertOk()
            ->assertSeeInOrder(self::CHICKEN_ORDER);

        $descending = array_reverse(self::CHICKEN_ORDER);

        foreach ($descending as $index => $name) {
            Product::where('name', $name)->update(['sort_order' => $index + 1]);
        }

        $this->get('/products/chicken')
            ->assertOk()
            ->assertSeeInOrder($descending);
    }

    public function test_equal_sort_orders_are_ordered_by_id(): void
    {
        // Совпадающий sort_order не должен делать порядок случайным:
        // при равных значениях строки идут по id.
        $chickenId = ProductCategory::where('slug', 'chicken')->value('id');
        Product::query()->update(['sort_order' => 1]);

        $expected = Product::where('product_category_id', $chickenId)
            ->orderBy('id')
            ->pluck('name')
            ->all();

        $this->get('/products/chicken')->assertOk()->assertSeeInOrder($expected);
    }

    // ------------------------------------------------------------------
    // 6—7. Отбор: активность и принадлежность категории
    // ------------------------------------------------------------------

    public function test_an_inactive_product_is_not_shown(): void
    {
        Product::where('name', 'Окорочка')->update(['is_active' => false]);

        $response = $this->get('/products/chicken')->assertOk();

        $this->assertSame(6, $this->cardCount($response->getContent()));
        $response->assertDontSee('Окорочка');
        $this->assertDatabaseMissing('products', ['name' => 'Окорочка', 'is_active' => true]);
    }

    public function test_a_product_of_another_category_never_reaches_the_page(): void
    {
        // Товар переносим из eggs в chicken, но сохраняем активным: если бы
        // отбор шёл только по is_active, он попал бы на страницу мяса.
        $chickenId = ProductCategory::where('slug', 'chicken')->value('id');
        $moved = Product::where('name', 'Вариант продукции 01')->update([
            'product_category_id' => $chickenId,
            'sort_order' => 8,
        ]);

        $this->assertSame(1, $moved);

        $chicken = $this->get('/products/chicken')->assertOk();
        $eggs = $this->get('/products/eggs')->assertOk();

        // Перенесённый товар уехал на страницу своей новой категории.
        $chicken->assertSee('Вариант продукции 01');
        $this->assertSame(8, $this->cardCount($chicken->getContent()));

        // На странице яиц остались только два «своих» товара.
        $eggs->assertDontSee('Вариант продукции 01');
        $this->assertSame(2, $this->cardCount($eggs->getContent()));
    }

    public function test_a_category_with_no_active_products_still_renders_its_page(): void
    {
        Product::query()->update(['is_active' => false]);

        $response = $this->get('/products/chicken')->assertOk();

        // Сама категория активна, поэтому страница существует, но пустая
        // сетка лучше 404: раздел открыт, ассортимент временно скрыт.
        $this->assertSame(0, $this->cardCount($response->getContent()));
        $response->assertSee('Наш ассортимент', false);
    }

    // ------------------------------------------------------------------
    // 8—9. Категория отсутствует или отключена
    // ------------------------------------------------------------------

    public function test_an_inactive_category_returns_404(): void
    {
        ProductCategory::where('slug', 'chicken')->update(['is_active' => false]);

        $this->get('/products/chicken')->assertNotFound();
    }

    public function test_a_missing_category_returns_404(): void
    {
        // Адрес остаётся зарегистрированным (маршрут строится из
        // config/site.php), но самой категории в базе нет.
        //
        // Товары удаляются первыми: внешний ключ объявлен с restrictOnDelete,
        // поэтому удалить категорию вместе с товарами база не даст — и это
        // правильное поведение, а не препятствие тесту.
        Product::where('product_category_id', $this->categoryId('eggs'))->delete();
        $this->assertSame(1, ProductCategory::where('slug', 'eggs')->delete());

        $this->get('/products/eggs')->assertNotFound();
    }

    public function test_an_unknown_slug_returns_404(): void
    {
        $this->get('/products/unknown-slug')->assertNotFound();
    }

    public function test_a_missing_category_does_not_fall_back_to_config_data(): void
    {
        Product::query()->delete();
        ProductCategory::query()->delete();

        // Никаких заимствованных из конфига заголовков и текстов: страница
        // не существует, и подставлять вместо неё данные было бы враньём.
        $this->get('/products/chicken')
            ->assertNotFound()
            ->assertDontSee('Мясо кур')
            ->assertDontSee('Наш ассортимент');
    }

    // ------------------------------------------------------------------
    // 10. Доказательство, что источник — база
    // ------------------------------------------------------------------

    public function test_a_product_renamed_in_the_database_appears_on_the_page_while_config_stays_untouched(): void
    {
        $configName = config('site.products.chicken.name');

        $this->get('/products/chicken')->assertOk()->assertSee('Окорочка');

        Product::where('name', 'Окорочка')->update(['name' => 'Окорочка бройлера']);

        $this->get('/products/chicken')
            ->assertOk()
            ->assertSee('Окорочка бройлера')
            ->assertDontSee('Окорочка</h3>');

        // Название категории config/catalog.php к этому отношения не имеет:
        // оно продолжает совпадать, потому что мы его не трогали.
        $this->assertSame($configName, 'Мясо кур');
    }

    public function test_a_product_removed_from_the_database_disappears_from_the_page(): void
    {
        Product::where('name', 'Другие продукты')->delete();

        $response = $this->get('/products/chicken')->assertOk();

        $this->assertSame(6, $this->cardCount($response->getContent()));
        $response->assertDontSee('Другие продукты');
    }

    public function test_the_category_name_comes_from_the_database(): void
    {
        ProductCategory::where('slug', 'chicken')->update(['name' => 'Мясо кур — тест']);

        $this->get('/products/chicken')
            ->assertOk()
            ->assertSee('Мясо кур — тест');
    }

    public function test_config_no_longer_holds_the_product_list(): void
    {
        // Список товаров живёт в базе. Пока в config/catalog.php остаётся
        // второй перечень, любая правка в нём молча ничего не меняла бы на
        // странице, и разработчик потерял бы источник истины.
        $this->assertArrayNotHasKey(
            'items',
            (array) config('catalog.chicken.catalog'),
            'Массив товаров в config/catalog.chicken.catalog должен быть удалён.',
        );
        $this->assertArrayNotHasKey(
            'items',
            (array) config('catalog.eggs.catalog'),
            'Массив товаров в config/catalog.eggs.catalog должен быть удалён.',
        );
    }

    // ------------------------------------------------------------------
    // 11—13. NULL-поля не ломают карточку
    // ------------------------------------------------------------------

    public function test_null_fields_do_not_produce_empty_rows(): void
    {
        $response = $this->get('/products/chicken')->assertOk();
        $html = $response->getContent();

        // Подписи характеристик не должны появляться, пока значений нет.
        foreach (['Вес', 'Упаковка', 'Срок годности', 'Условия хранения'] as $label) {
            $this->assertStringNotContainsString($label, $html);
        }

        // Блок характеристик целиком не выводится.
        $this->assertStringNotContainsString('<dl', $html);
        $this->assertStringNotContainsString('<dt', $html);
        $this->assertStringNotContainsString('<dd', $html);
    }

    public function test_the_placeholder_is_used_when_image_is_null(): void
    {
        $this->get('/products/chicken')
            ->assertOk()
            ->assertSee('aria-hidden="true"', false);

        // Пустой src был бы хуже отсутствия: браузер запросил бы текущую
        // страницу как картинку.
        $this->assertNull(Product::where('name', 'Тушка курицы')->value('image'));
    }

    public function test_a_real_image_path_is_turned_into_an_absolute_url(): void
    {
        Product::where('name', 'Тушка курицы')->update(['image' => 'images/tushka.jpg']);

        $this->get('/products/chicken')
            ->assertOk()
            ->assertSee('src="'.asset('images/tushka.jpg').'"', false);
    }

    public function test_the_details_button_is_absent_without_a_details_url(): void
    {
        $response = $this->get('/products/chicken')->assertOk();
        $html = $response->getContent();

        // Страниц товара нет, поэтому неактивная кнопка «Подробнее»
        // выглядела бы как неработающая ссылка.
        $this->assertStringNotContainsString('Подробнее', $html);
        $this->assertStringNotContainsString('data-card-details', $html);
    }

    public function test_a_short_description_from_the_database_is_rendered(): void
    {
        Product::where('name', 'Окорочка')->update([
            'short_description' => 'Описание из базы под тест.',
        ]);

        $this->get('/products/chicken')->assertOk()->assertSee('Описание из базы под тест.');
    }

    // ------------------------------------------------------------------
    // Смешанные проверки страницы
    // ------------------------------------------------------------------

    public function test_the_page_renders_exactly_one_h1(): void
    {
        $this->get('/products/chicken')->assertOk();

        $this->assertSame(1, substr_count($this->get('/products/chicken')->getContent(), '<h1'));
    }

    public function test_the_page_uses_two_queries_and_does_not_lazily_load_relations(): void
    {
        // Одна на категорию, одна на её товары. Обращение шаблона к
        // $product->category дало бы столько же запросов, сколько товаров.
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->get('/products/chicken')->assertOk();

        $this->assertSame(2, $queries, 'Страница должна выполнять ровно два запроса: категория и товары.');
    }

    public function test_page_level_content_still_comes_from_config(): void
    {
        $this->get('/products/chicken')
            ->assertOk()
            ->assertSee(config('catalog.chicken.intro'), false)
            ->assertSee(config('catalog.chicken.catalog.heading'), false)
            ->assertSee(config('catalog.chicken.catalog.notice'), false)
            ->assertSee(config('catalog.cta.title'), false)
            ->assertSee('images/'.basename((string) config('catalog.chicken.image')), false);
    }

    public function test_the_meta_description_comes_from_config(): void
    {
        $this->get('/products/chicken')
            ->assertOk()
            ->assertSee(config('catalog.chicken.meta_description'), false);
    }

    /**
     * Число карточек товара на странице.
     *
     * На странице категории тег article используется только карточкой
     * товара, поэтому подсчёт точен.
     */
    private function cardCount(string $html): int
    {
        return substr_count($html, '<article');
    }

    /**
     * Идентификатор категории по slug.
     */
    private function categoryId(string $slug): int
    {
        return (int) ProductCategory::where('slug', $slug)->value('id');
    }
}
