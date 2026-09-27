<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Тесты начального наполнения каталога.
 *
 * RefreshDatabase работает на SQLite :memory: из phpunit.xml, поэтому эти
 * тесты не пишут ни одной строки в рабочую базу database/database.sqlite —
 * проверка рабочей базы выполняется отдельно, командами migrate:fresh --seed
 * и db:seed.
 */
final class ProductCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ожидаемые категории: slug => [название, sort_order].
     *
     * @var array<string, array{0: string, 1: int}>
     */
    private const EXPECTED_CATEGORIES = [
        'eggs' => ['Яйца кур', 1],
        'chicken' => ['Мясо кур', 2],
    ];

    /**
     * Ожидаемые товары: slug => [категория, название, sort_order].
     *
     * @var array<string, array{0: string, 1: string, 2: int}>
     */
    private const EXPECTED_PRODUCTS = [
        // Мясо кур — подтверждённый ассортимент, sort_order с 1 по 7.
        'tushka-kuritsy' => ['chicken', 'Тушка курицы', 1],
        'okorochka' => ['chicken', 'Окорочка', 2],
        'kurinye-bedra' => ['chicken', 'Куриные бёдра', 3],
        'kurinye-serdtsa' => ['chicken', 'Куриные сердца', 4],
        'kurinaya-pechen' => ['chicken', 'Куриная печень', 5],
        'supovye-nabory' => ['chicken', 'Суповые наборы', 6],
        'drugie-produkty' => ['chicken', 'Другие продукты', 7],

        // Яйца кур — временные демонстрационные позиции, 1…3.
        'variant-01' => ['eggs', 'Вариант продукции 01', 1],
        'variant-02' => ['eggs', 'Вариант продукции 02', 2],
        'variant-03' => ['eggs', 'Вариант продукции 03', 3],
    ];

    /**
     * Поля, которые заказчиком не подтверждены и обязаны остаться NULL.
     *
     * @var list<string>
     */
    private const EXPECTED_NULL_FIELDS = [
        'image',
        'weight',
        'packaging',
        'storage',
        'shelf_life',
        'additional_info',
    ];

    // ------------------------------------------------------------------
    // 1. Категории
    // ------------------------------------------------------------------

    public function test_it_creates_exactly_two_categories(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $this->assertSame(2, ProductCategory::count());
    }

    public function test_it_creates_the_expected_categories_in_order(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $actual = ProductCategory::orderBy('sort_order')->get()
            ->map(fn (ProductCategory $category): array => [
                $category->slug,
                $category->name,
                $category->sort_order,
                $category->is_active,
            ])
            ->all();

        $this->assertSame([
            ['eggs', 'Яйца кур', 1, true],
            ['chicken', 'Мясо кур', 2, true],
        ], $actual);
    }

    // ------------------------------------------------------------------
    // 2. Количество и принадлежность товаров
    // ------------------------------------------------------------------

    public function test_chicken_contains_exactly_seven_products(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $this->assertSame(7, $this->category('chicken')->products()->count());
    }

    public function test_eggs_contains_exactly_three_temporary_products(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $eggs = $this->category('eggs');

        $this->assertSame(3, $eggs->products()->count());

        // Позиции яиц — временные заглушки, а не ассортимент заказчика.
        $this->assertSame(
            ['Вариант продукции 01', 'Вариант продукции 02', 'Вариант продукции 03'],
            $eggs->products()->orderBy('sort_order')->pluck('name')->all(),
        );
    }

    public function test_every_product_belongs_to_the_expected_category(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        foreach (self::EXPECTED_PRODUCTS as $slug => [$expectedCategorySlug, $name, $sortOrder]) {
            $product = Product::where('slug', $slug)->firstOrFail();

            $this->assertSame(
                $name,
                $product->name,
                "Название товара {$slug} не совпадает с ожидаемым.",
            );

            $this->assertSame(
                $expectedCategorySlug,
                $product->category->slug,
                "Товар {$slug} принадлежит не той категории.",
            );

            $this->assertSame(
                $sortOrder,
                $product->sort_order,
                "sort_order товара {$slug} не совпадает с ожидаемым.",
            );
        }
    }

    public function test_all_products_are_active_and_categories_are_linked(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $this->assertSame(0, Product::where('is_active', false)->count());
        $this->assertSame(10, Product::count());

        // Связь читается в обе стороны: у каждого товара есть категория,
        // и каждый товар виден из своей категории.
        foreach (Product::all() as $product) {
            $this->assertInstanceOf(ProductCategory::class, $product->category);
            $this->assertTrue(
                $product->category->products()->where('id', $product->id)->exists(),
                "Товар {$product->slug} не виден из своей категории.",
            );
        }
    }

    // ------------------------------------------------------------------
    // 3. Незаполненные характеристики
    // ------------------------------------------------------------------

    public function test_unconfirmed_product_fields_stay_null(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        foreach (Product::all() as $product) {
            foreach (self::EXPECTED_NULL_FIELDS as $field) {
                $this->assertNull(
                    $product->{$field},
                    "Поле {$field} товара {$product->slug} не должно быть заполнено без подтверждённых данных.",
                );
            }
        }
    }

    public function test_eggs_temporary_products_have_no_description(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        // В config/catalog.php у временных позиций яиц нет короткого
        // описания, поэтому переносить нечего: поле остаётся пустым.
        foreach ($this->category('eggs')->products as $product) {
            $this->assertNull($product->short_description);
        }
    }

    // ------------------------------------------------------------------
    // 4. Slug
    // ------------------------------------------------------------------

    public function test_slugs_match_the_expected_set(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $this->assertSame(
            array_keys(self::EXPECTED_PRODUCTS),
            Product::orderBy('id')->pluck('slug')->all(),
        );
    }

    public function test_category_slugs_match_the_expected_set(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $this->assertSame(
            ['eggs', 'chicken'],
            ProductCategory::orderBy('id')->pluck('slug')->all(),
        );
    }

    // ------------------------------------------------------------------
    // 5. Повторный запуск
    // ------------------------------------------------------------------

    public function test_repeated_seeding_creates_no_duplicates(): void
    {
        $this->seed(ProductCatalogSeeder::class);
        $this->seed(ProductCatalogSeeder::class);
        $this->seed(ProductCatalogSeeder::class);

        $this->assertSame(2, ProductCategory::count());
        $this->assertSame(10, Product::count());
        $this->assertSame(7, $this->category('chicken')->products()->count());
        $this->assertSame(3, $this->category('eggs')->products()->count());
    }

    public function test_repeated_seeding_updates_records_instead_of_inserting(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $categoryId = $this->category('chicken')->id;
        $productId = Product::where('slug', 'tushka-kuritsy')->value('id');

        $this->seed(ProductCatalogSeeder::class);

        // updateOrCreate обновляет существующие строки: идентификаторы
        // сохраняются, а не выдаются заново.
        $this->assertSame($categoryId, $this->category('chicken')->id);
        $this->assertSame($productId, Product::where('slug', 'tushka-kuritsy')->value('id'));
    }

    public function test_seeding_brings_records_back_to_the_declared_state(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        // Портим данные так, как это сделал бы предыдущий вариант seeder.
        $this->category('chicken')->update(['name' => 'Неверное название', 'sort_order' => 99]);
        Product::where('slug', 'okorochka')->update(['name' => 'Сбитое имя']);

        $this->seed(ProductCatalogSeeder::class);

        $this->assertSame('Мясо кур', $this->category('chicken')->name);
        $this->assertSame(2, $this->category('chicken')->sort_order);
        $this->assertSame('Окорочка', Product::where('slug', 'okorochka')->value('name'));
    }

    public function test_database_seeder_runs_the_catalog_seeder(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, ProductCategory::count());
        $this->assertSame(10, Product::count());
    }

    public function test_database_seeder_can_be_run_repeatedly(): void
    {
        // Раньше повторный `php artisan db:seed` падал на уникальном
        // users.email: тестовый пользователь из заготовки Laravel вставлялся
        // заново. Проверяем, что и каталог, и DatabaseSeeder переживают
        // несколько запусков подряд.
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(2, ProductCategory::count());
        $this->assertSame(10, Product::count());
        $this->assertSame(1, User::count(), 'Тестовый пользователь не должен дублироваться.');
    }

    // ------------------------------------------------------------------
    // 6. Совпадение с источником
    // ------------------------------------------------------------------

    public function test_chicken_descriptions_match_the_config_source_verbatim(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $source = collect(config('catalog.chicken.catalog.items'))
            ->keyBy('name');

        $this->assertCount(7, $source, 'Конфиг должен содержать 7 позиций мяса кур.');

        foreach ($this->category('chicken')->products as $product) {
            $this->assertArrayHasKey(
                $product->name,
                $source->all(),
                "Товара «{$product->name}» нет в config/catalog.php: перенесён неподтверждённый товар.",
            );

            $this->assertSame(
                $source[$product->name]['short_description'] ?? null,
                $product->short_description,
                "Описание товара «{$product->name}» не совпадает с config/catalog.php дословно.",
            );
        }
    }

    public function test_chicken_names_and_order_match_the_config_source(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $expected = array_column(config('catalog.chicken.catalog.items'), 'name');

        $this->assertSame(
            $expected,
            $this->category('chicken')->products()->orderBy('sort_order')->pluck('name')->all(),
            'Названия и порядок товаров мяса кур должны совпадать с config/catalog.php.',
        );
    }

    public function test_eggs_names_match_the_config_source(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $expected = array_column(config('catalog.eggs.catalog.items'), 'name');

        $this->assertSame(
            $expected,
            $this->category('eggs')->products()->orderBy('sort_order')->pluck('name')->all(),
        );
    }

    /**
     * Категория по slug или явное падение теста.
     */
    private function category(string $slug): ProductCategory
    {
        return ProductCategory::where('slug', $slug)->firstOrFail();
    }
}
