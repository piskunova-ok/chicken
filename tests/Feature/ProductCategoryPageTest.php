<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $response->assertSee('data-catalog-empty', false);
    }

    // ------------------------------------------------------------------
    // Активная категория без активных товаров
    // ------------------------------------------------------------------

    public function test_the_eggs_page_renders_the_empty_state_when_no_product_is_active(): void
    {
        $this->deactivateAllProducts();

        $response = $this->get('/products/eggs')->assertOk();

        // Категория активна, страница живёт — это не 404.
        $this->assertSame(0, $this->cardCount($response->getContent()));
        $this->assertStringContainsString('data-catalog-empty', $response->getContent());
        $response->assertSee(config('content.catalog.empty_text'), false);
    }

    public function test_deactivated_technical_eggs_products_disappear_from_the_page(): void
    {
        $this->deactivateAllProducts();

        $response = $this->get('/products/eggs')->assertOk();

        $this->assertSame(0, $this->cardCount($response->getContent()));

        foreach (self::EGGS_ORDER as $name) {
            $response->assertDontSee($name);
        }

        foreach (['variant-01', 'variant-02', 'variant-03'] as $slug) {
            $response->assertDontSee('/products/'.$slug, false);
        }

        // Записи остались в базе — их выключили, а не удалили.
        $this->assertSame(3, Product::where('product_category_id', $this->categoryId('eggs'))->count());
        $this->assertSame(0, Product::where('product_category_id', $this->categoryId('eggs'))->where('is_active', true)->count());
    }

    public function test_the_empty_state_makes_no_claim_about_stock(): void
    {
        $this->deactivateAllProducts();

        $response = $this->get('/products/eggs')->assertOk();
        $html = $response->getContent();

        // Фактического наличия продукции проект не знает, поэтому ни сам
        // блок, ни страница не имеют права утверждать что-либо о нём.
        // Проверяем именно блок, а не всю страницу: на других категориях
        // в текстах есть нейтральные фразы вроде «актуальное наличие», и
        // запрещать их здесь означало бы запрещать чужие формулировки.
        $this->assertStringNotContainsStringIgnoringCase('наличи', $this->emptyStateText($html));
        $this->assertStringNotContainsStringIgnoringCase('распродан', $this->emptyStateText($html));
        $this->assertStringNotContainsStringIgnoringCase('закончил', $this->emptyStateText($html));

        // «Нет в наличии» — прямая ложь о поставках, её на странице быть
        // не должно ни при каких обстоятельствах.
        $this->assertStringNotContainsStringIgnoringCase('нет в наличии', $this->visibleText($html));
        $this->assertStringNotContainsStringIgnoringCase('нет на складе', $this->visibleText($html));
    }

    public function test_the_catalog_heading_returns_as_soon_as_one_product_is_active(): void
    {
        $this->deactivateAllProducts();

        $empty = $this->get('/products/eggs')->assertOk();
        $this->assertStringContainsString('data-catalog-empty', $empty->getContent());

        // Возвращаем один товар: блок ассортимента и его прежний заголовок
        // обязаны вернуться сами, без правок конфига.
        Product::where('name', 'Вариант продукции 01')->update(['is_active' => true]);

        $filled = $this->get('/products/eggs')->assertOk();

        $this->assertStringNotContainsString('data-catalog-empty', $filled->getContent());
        $filled->assertDontSee(config('content.catalog.empty_text'), false);
        $filled->assertSee(config('catalog.eggs.catalog.heading'), false);
        $filled->assertSee(config('catalog.eggs.catalog.lead'), false);
        $filled->assertSee('Вариант продукции 01');
        $this->assertSame(1, $this->cardCount($filled->getContent()));
    }

    public function test_the_empty_state_works_for_any_active_category_not_only_eggs(): void
    {
        $this->deactivateAllProducts();

        foreach (['eggs', 'chicken'] as $slug) {
            $response = $this->get('/products/'.$slug)->assertOk();

            $this->assertStringContainsString(
                'data-catalog-empty',
                $response->getContent(),
                "Пустая категория {$slug} обязана показывать блок об обновлении ассортимента.",
            );
            $response->assertSee(config('content.catalog.empty_text'), false);
            $this->assertSame(0, $this->cardCount($response->getContent()));
        }
    }

    public function test_deactivating_eggs_products_leaves_the_chicken_page_untouched(): void
    {
        $before = $this->get('/products/chicken')->assertOk();
        $this->assertSame(7, $this->cardCount($before->getContent()));

        // Выключаем только яйца — ровно то изменение, которое делает этап 11
        // в рабочей базе. Страница мяса кур не должна почувствовать его ни
        // одним байтом.
        $eggsId = $this->categoryId('eggs');
        $this->assertSame(3, Product::where('product_category_id', $eggsId)->update(['is_active' => false]));

        $after = $this->get('/products/chicken')->assertOk();

        // Семь карточек мяса кур на месте, пустого блока нет.
        $this->assertSame(7, $this->cardCount($after->getContent()));
        $this->assertStringNotContainsString('data-catalog-empty', $after->getContent());
        $after->assertSeeInOrder(self::CHICKEN_ORDER);
        $after->assertSee(config('catalog.chicken.catalog.heading'), false);

        // Содержимое страницы не изменилось ни на байт.
        $this->assertSame(
            $this->visibleText($before->getContent()),
            $this->visibleText($after->getContent()),
        );
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

    /**
     * Загруженные через админку изображения (disk 'public', каталог
     * products) страница показывает URL-ом публичного диска. Раньше здесь
     * был asset(): он предполагал относительный путь внутри public/, а
     * загрузки живут в storage/app/public и отдаются через /storage.
     */
    public function test_a_real_image_path_is_turned_into_a_public_disk_url(): void
    {
        Product::where('name', 'Тушка курицы')->update(['image' => 'products/tushka.jpg']);

        $this->get('/products/chicken')
            ->assertOk()
            ->assertSee('src="'.Storage::disk('public')->url('products/tushka.jpg').'"', false);
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
     * Видимый текст блока пустого состояния каталога.
     *
     * Блок помечен атрибутом data-catalog-empty именно ради таких проверок:
     * по нему видно, что страница честно сообщила об отсутствии позиций, а
     * не просто потеряла разметку.
     */
    private function emptyStateText(string $html): string
    {
        $this->assertSame(
            1,
            preg_match('/<div[^>]*data-catalog-empty[^>]*>(.*?)<\/div>/s', $html, $match),
            'На странице без активных товаров должен быть ровно один блок пустого состояния.',
        );

        return $this->visibleText($match[1]);
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
     * Видимый текст страницы без разметки.
     *
     * Побайтовое сравнение HTML здесь не годится: Livewire подмешивает
     * служебный <style> в первую отрисовку процесса и не подмешивает его
     * во вторую, поэтому один и тот же адрес сравнивается как разные строки
     * в зависимости от порядка тестов. Сравнение видимого текста проверяет
     * именно то, что видит посетитель, и не зависит от служебной разметки.
     */
    private function visibleText(string $html): string
    {
        // Служебные <style> и <script> вырезаются вместе с содержимым:
        // Livewire подмешивает их не в каждую отрисовку, и они не являются
        // тем, что видит посетитель.
        $html = preg_replace('/<(style|script)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $text = preg_replace('/<[^>]+>/', ' ', $html) ?? $html;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Выключает все товары обеих категорий.
     *
     * Именно то состояние, которое заказчик получит на рабочей базе после
     * этапа 11: категории активны, позиции внутри них скрыты. Сценарий не
     * привязан к «Яйцам кур» — так проверяется, что пустое состояние
     * появляется у любой активной категории.
     */
    private function deactivateAllProducts(): void
    {
        $this->assertGreaterThan(0, Product::query()->update(['is_active' => false]));
    }

    /**
     * Идентификатор категории по slug.
     */
    private function categoryId(string $slug): int
    {
        return (int) ProductCategory::where('slug', $slug)->value('id');
    }
}
