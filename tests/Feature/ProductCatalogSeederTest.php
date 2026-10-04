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

        // Яйца кур — подтверждённые категории C0, C1 и C2, sort_order с 1 по 3.
        'egg-c0' => ['eggs', 'Яйцо куриное C0', 1],
        'egg-c1' => ['eggs', 'Яйцо куриное C1', 2],
        'egg-c2' => ['eggs', 'Яйцо куриное C2', 3],
    ];

    /**
     * Поля, которые заказчиком не подтверждены и обязаны остаться NULL.
     *
     * image здесь нет: фотографии подтверждены и лежат в public/images/products,
     * поэтому путь в image задаёт seeder (см. EXPECTED_IMAGES).
     *
     * @var list<string>
     */
    private const EXPECTED_NULL_FIELDS = [
        'weight',
        'packaging',
        'storage',
        'shelf_life',
        'additional_info',
    ];

    /**
     * Подтверждённые фотографии: slug => путь относительно public/.
     *
     * Файлы лежат в public/images/products и отслеживаются Git, поэтому seeder
     * пишет именно такой путь, а страница строит ссылку через asset(). Путь
     * хранится относительным, а не абсолютным и не URL: он переживает смену
     * домена и не врёт о способе отдачи файла.
     *
     * Товар без фотографии в списке отсутствует — его image обязан быть NULL.
     *
     * @var array<string, string>
     */
    private const EXPECTED_IMAGES = [
        'tushka-kuritsy' => 'images/products/tushka-kuritsy.jpg',
        'okorochka' => 'images/products/okorochka.jpg',
        'kurinye-bedra' => 'images/products/kurinye-bedra.jpg',
        'kurinye-serdtsa' => 'images/products/kurinye-serdtsa.jpg',
        'kurinaya-pechen' => 'images/products/kurinaya-pechen.jpg',
        'supovye-nabory' => 'images/products/supovye-nabory.jpg',
        'egg-c0' => 'images/products/egg-c0.jpg',
        'egg-c1' => 'images/products/egg-c1.jpg',
        'egg-c2' => 'images/products/egg-c2.jpg',
    ];

    /**
     * Утверждённый заказчиком ассортимент мяса кур: название => описание.
     *
     * Это снимок подтверждённого текста, а не источник данных: страницы
     * берут товары из базы, а массивы товаров удалены из config/catalog.php,
     * чтобы список жил в одном месте. Снимок нужен, чтобы случайная правка
     * формулировки в seeder ломала тест, а не проходила незаметно.
     *
     * @var array<string, string>
     */
    private const CONFIRMED_CHICKEN = [
        'Тушка курицы' => 'Целая тушка — универсальный вариант для запекания, приготовления бульонов, первых и вторых блюд.',
        'Окорочка' => 'Для запекания, тушения, жарки и приготовления на гриле.',
        'Куриные бёдра' => 'Части курицы для горячих блюд, запекания и тушения.',
        'Куриные сердца' => 'Субпродукт для горячих блюд, тушения, салатов и закусок.',
        'Куриная печень' => 'Продукт для паштетов, горячих блюд, закусок и домашней кухни.',
        'Суповые наборы' => 'Вариант для приготовления бульонов, супов и других первых блюд.',
        'Другие продукты' => 'Ассортимент куриной продукции дополняется другими позициями. Актуальное наличие и характеристики можно уточнить у наших специалистов.',
    ];

    /**
     * Позиции яиц: подтверждённые категории C0, C1 и C2.
     *
     * Коротких описаний у них нет — ни в конфиге, ни у заказчика, — поэтому
     * seeder не имеет права их сочинять.
     *
     * @var list<array{name: string, slug: string}>
     */
    private const CONFIRMED_EGGS = [
        ['name' => 'Яйцо куриное C0', 'slug' => 'egg-c0'],
        ['name' => 'Яйцо куриное C1', 'slug' => 'egg-c1'],
        ['name' => 'Яйцо куриное C2', 'slug' => 'egg-c2'],
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

    public function test_eggs_contains_exactly_three_categorised_products(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $eggs = $this->category('eggs');

        $this->assertSame(3, $eggs->products()->count());

        $this->assertSame(
            array_column(self::CONFIRMED_EGGS, 'name'),
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

    public function test_eggs_have_no_description(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        // Утверждённых описаний для категорий яиц нет, поэтому переносить
        // нечего: поле остаётся пустым.
        foreach ($this->category('eggs')->products as $product) {
            $this->assertNull($product->short_description);
        }
    }

    // ------------------------------------------------------------------
    // 4. Фотографии
    // ------------------------------------------------------------------

    public function test_every_product_with_a_confirmed_photo_gets_the_expected_path(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        foreach (self::EXPECTED_IMAGES as $slug => $expectedImage) {
            $this->assertSame(
                $expectedImage,
                Product::where('slug', $slug)->value('image'),
                "Путь к фотографии товара {$slug} не совпадает с ожидаемым.",
            );
        }
    }

    public function test_image_paths_are_relative_and_live_inside_public(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        foreach (Product::whereNotNull('image')->get() as $product) {
            $image = $product->image;

            // Ни абсолютного пути, ни URL, ни пути на диск Filament: файл
            // каталога лежит в public/ и отслеживается Git.
            $this->assertStringStartsWith('images/products/', $image);
            $this->assertStringNotContainsString('storage/app', $image);
            $this->assertStringNotContainsString('/storage/', $image);
            $this->assertStringNotContainsString('://', $image);
            $this->assertStringNotContainsString('\\', $image);
            $this->assertStringEndsWith('.jpg', $image);
        }
    }

    public function test_a_product_without_a_confirmed_photo_keeps_image_null(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        // У обобщённой позиции «Другие продукты» фотографии нет, и подставить
        // чужую картинку было бы выдумкой.
        $this->assertNull(
            Product::where('slug', 'drugie-produkty')->value('image'),
        );

        $this->assertSame(
            9,
            Product::whereNotNull('image')->count(),
            'Фотографии подтверждены у девяти товаров из десяти.',
        );
    }

    /**
     * Точный набор товаров с фотографией. Проверяет и количество, и то, что
     * лишнего фото не появилось: девять из десяти, а не «сколько вышло».
     */
    public function test_the_set_of_products_with_images_is_exact(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $slugs = Product::whereNotNull('image')
            ->orderBy('slug')
            ->pluck('slug')
            ->all();

        $expected = array_keys(self::EXPECTED_IMAGES);
        sort($expected);

        $this->assertSame($expected, $slugs);
    }

    public function test_every_declared_photo_file_exists_in_public(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        // Seeder не должен ссылаться на файл, которого нет в репозитории:
        // каталог и его фотографии обязаны воспроизводиться из Git целиком.
        foreach (self::EXPECTED_IMAGES as $slug => $relativePath) {
            $this->assertFileExists(
                public_path($relativePath),
                "Файл фотографии товара {$slug} не найден в public/.",
            );
        }
    }

    // ------------------------------------------------------------------
    // 5. Slug
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
    // 6. Повторный запуск
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
    // 7. Совпадение с подтверждённым текстом
    // ------------------------------------------------------------------

    public function test_chicken_descriptions_match_the_confirmed_text_verbatim(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $seeded = $this->category('chicken')->products()
            ->orderBy('sort_order')
            ->get()
            ->keyBy('name');

        $this->assertCount(
            count(self::CONFIRMED_CHICKEN),
            $seeded,
            'Список товаров мяса кур в базе не совпадает с подтверждённым.',
        );

        foreach (self::CONFIRMED_CHICKEN as $name => $description) {
            $this->assertTrue(
                $seeded->has($name),
                "Товара «{$name}» нет среди подтверждённых: перенесён лишний товар.",
            );

            $this->assertSame(
                $description,
                $seeded[$name]->short_description,
                "Описание товара «{$name}» изменено: оно должно совпадать с утверждённым текстом дословно.",
            );
        }
    }

    public function test_chicken_names_and_order_match_the_confirmed_text(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $this->assertSame(
            array_keys(self::CONFIRMED_CHICKEN),
            $this->category('chicken')->products()->orderBy('sort_order')->pluck('name')->all(),
            'Названия и порядок товаров мяса кур должны совпадать с утверждённым ассортиментом.',
        );
    }

    public function test_eggs_names_match_the_confirmed_text(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        $this->assertSame(
            array_column(self::CONFIRMED_EGGS, 'name'),
            $this->category('eggs')->products()->orderBy('sort_order')->pluck('name')->all(),
        );
    }

    public function test_eggs_products_never_gain_a_description(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        // Утверждённых описаний для категорий яиц нет нигде, и seeder не
        // имеет права их сочинить.
        foreach ($this->category('eggs')->products as $product) {
            $this->assertNull($product->short_description);
        }
    }

    /**
     * Категория по slug или явное падение теста.
     */
    private function category(string $slug): ProductCategory
    {
        return ProductCategory::where('slug', $slug)->firstOrFail();
    }
}
