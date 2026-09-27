<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Проверка структуры каталога в базе: миграции, ограничения и связи.
 *
 * База тестов — SQLite в памяти (phpunit.xml), поэтому рабочая
 * database/database.sqlite этими данными не затрагивается. Значения в тестах
 * выдуманы намеренно: это проверка механики, а не клиентские данные.
 */
final class ProductCatalogSchemaTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // 1. Миграции создают таблицы и поля
    // ------------------------------------------------------------------

    public function test_it_creates_the_product_categories_table_with_the_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('product_categories'));

        $this->assertTrue(Schema::hasColumns('product_categories', [
            'id', 'name', 'slug', 'is_active', 'sort_order', 'created_at', 'updated_at',
        ]));
    }

    public function test_it_creates_the_products_table_with_the_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('products'));

        $this->assertTrue(Schema::hasColumns('products', [
            'id', 'product_category_id', 'name', 'slug', 'short_description', 'image',
            'weight', 'packaging', 'storage', 'shelf_life', 'additional_info',
            'is_active', 'sort_order', 'created_at', 'updated_at',
        ]));
    }

    public function test_it_does_not_add_unconfirmed_fields_to_product_categories(): void
    {
        // SEO, hero, изображения и описания заказчиком не подтверждены,
        // поэтому таких колонок в схеме быть не должно.
        $unconfirmed = [
            'meta_title', 'meta_description', 'hero_image', 'image', 'image_alt',
            'description', 'intro', 'eyebrow',
        ];

        foreach ($unconfirmed as $column) {
            $this->assertFalse(
                Schema::hasColumn('product_categories', $column),
                "Колонка {$column} не должна существовать на этом этапе.",
            );
        }
    }

    public function test_it_applies_the_expected_defaults_for_flags(): void
    {
        $category = ProductCategory::create(['name' => 'Категория', 'slug' => 'cat-defaults']);
        $product = Product::create([
            'product_category_id' => $category->id,
            'name' => 'Товар',
            'slug' => 'item-defaults',
        ]);

        $this->assertTrue($category->fresh()->is_active);
        $this->assertSame(0, $category->fresh()->sort_order);
        $this->assertTrue($product->fresh()->is_active);
        $this->assertSame(0, $product->fresh()->sort_order);
    }

    public function test_it_allows_null_for_every_optional_product_field(): void
    {
        $category = ProductCategory::create(['name' => 'Категория', 'slug' => 'cat-nulls']);

        $product = Product::create([
            'product_category_id' => $category->id,
            'name' => 'Товар без характеристик',
            'slug' => 'item-nulls',
        ]);

        $product = $product->fresh();

        foreach (['short_description', 'image', 'weight', 'packaging', 'storage', 'shelf_life', 'additional_info'] as $field) {
            $this->assertNull($product->{$field}, "Поле {$field} должно допускать null.");
        }
    }

    // ------------------------------------------------------------------
    // 2. ProductCategory hasMany Product
    // ------------------------------------------------------------------

    public function test_a_category_can_have_several_products(): void
    {
        $category = ProductCategory::create(['name' => 'Мясо кур', 'slug' => 'chicken']);

        Product::create(['product_category_id' => $category->id, 'name' => 'Тушка', 'slug' => 'tushka']);
        Product::create(['product_category_id' => $category->id, 'name' => 'Окорочка', 'slug' => 'okorochka']);
        Product::create(['product_category_id' => $category->id, 'name' => 'Печень', 'slug' => 'pechen']);

        $this->assertInstanceOf(ProductCategory::class, $category);
        $this->assertCount(3, $category->products);

        // Порядок не сравниваем: отношение его не задаёт, порядок выдачи
        // зависит от СУБД. Проверяем состав, а не последовательность.
        $names = $category->products->pluck('name')->sort()->values()->all();

        $this->assertSame(['Окорочка', 'Печень', 'Тушка'], $names);
    }

    public function test_a_freshly_created_category_starts_with_no_products(): void
    {
        $category = ProductCategory::create(['name' => 'Яйца кур', 'slug' => 'eggs']);

        $this->assertCount(0, $category->products);
    }

    public function test_the_relationship_uses_the_expected_foreign_key(): void
    {
        $category = ProductCategory::create(['name' => 'Мясо кур', 'slug' => 'chicken']);

        // hasMany выводит внешний ключ из имени родительской модели, поэтому
        // проверяем, что запрос идёт по product_category_id, а не по
        // несуществующей category_id.
        $this->assertSame('product_category_id', $category->products()->getForeignKeyName());

        $product = Product::create([
            'product_category_id' => $category->id,
            'name' => 'Тушка курицы',
            'slug' => 'tushka',
        ]);

        // У belongsTo Laravel выводит ключ из ИМЕНИ МЕТОДА category(), то
        // есть category_id. Явное указание в модели обязательно.
        $this->assertSame('product_category_id', $product->category()->getForeignKeyName());
    }

    // ------------------------------------------------------------------
    // 3. Product belongsTo ProductCategory
    // ------------------------------------------------------------------

    public function test_a_product_belongs_to_its_category(): void
    {
        $category = ProductCategory::create(['name' => 'Мясо кур', 'slug' => 'chicken']);
        $product = Product::create([
            'product_category_id' => $category->id,
            'name' => 'Тушка курицы',
            'slug' => 'tushka',
        ]);

        $related = $product->fresh()->category;

        $this->assertInstanceOf(ProductCategory::class, $related);
        $this->assertSame($category->id, $related->id);
        $this->assertSame('Мясо кур', $related->name);
    }

    public function test_the_relationship_works_in_both_directions(): void
    {
        $category = ProductCategory::create(['name' => 'Яйца кур', 'slug' => 'eggs']);
        $product = Product::create([
            'product_category_id' => $category->id,
            'name' => 'Вариант',
            'slug' => 'variant',
        ]);

        // Связь читается и через родителя, и через ребёнка: обе стороны
        // указывают на одну и ту же категорию.
        $this->assertTrue($product->category->is($category));
        $this->assertTrue($category->products->first()->category->is($category));
    }

    // ------------------------------------------------------------------
    // 4. Casts
    // ------------------------------------------------------------------

    public function test_it_casts_boolean_and_integer_fields(): void
    {
        $category = ProductCategory::create([
            'name' => 'Категория',
            'slug' => 'cat-casts',
            'is_active' => false,
            'sort_order' => 5,
        ]);

        $category = $category->fresh();

        $this->assertIsBool($category->is_active);
        $this->assertFalse($category->is_active);
        $this->assertIsInt($category->sort_order);
        $this->assertSame(5, $category->sort_order);

        $product = Product::create([
            'product_category_id' => $category->id,
            'name' => 'Товар',
            'slug' => 'item-casts',
            'is_active' => false,
            'sort_order' => 3,
        ]);

        $product = $product->fresh();

        $this->assertIsBool($product->is_active);
        $this->assertFalse($product->is_active);
        $this->assertIsInt($product->sort_order);
        $this->assertSame(3, $product->sort_order);
    }

    public function test_is_active_is_stored_as_a_boolean_in_the_database(): void
    {
        $category = ProductCategory::create([
            'name' => 'Категория',
            'slug' => 'cat-bool-storage',
            'is_active' => true,
        ]);

        // SQLite не различает boolean и integer: проверяем сырое значение,
        // чтобы убедиться, что в базу попала 1, а не строка 'true'.
        $raw = $this->connection()->table('product_categories')
            ->where('id', $category->id)
            ->value('is_active');

        $this->assertSame(1, (int) $raw);
    }

    // ------------------------------------------------------------------
    // 5. Ограничения slug
    // ------------------------------------------------------------------

    public function test_a_category_slug_is_globally_unique(): void
    {
        ProductCategory::create(['name' => 'Первая', 'slug' => 'eggs']);

        $this->expectException(QueryException::class);

        ProductCategory::create(['name' => 'Вторая', 'slug' => 'eggs']);
    }

    public function test_the_same_product_slug_is_allowed_in_different_categories(): void
    {
        $eggs = ProductCategory::create(['name' => 'Яйца кур', 'slug' => 'eggs']);
        $chicken = ProductCategory::create(['name' => 'Мясо кур', 'slug' => 'chicken']);

        // Адрес /products/{категория}/{товар} различается категорией,
        // поэтому одинаковый slug в разных категориях не конфликтует.
        Product::create(['product_category_id' => $eggs->id, 'name' => 'Другое', 'slug' => 'other']);
        Product::create(['product_category_id' => $chicken->id, 'name' => 'Другое', 'slug' => 'other']);

        $this->assertSame(1, $eggs->products()->where('slug', 'other')->count());
        $this->assertSame(1, $chicken->products()->where('slug', 'other')->count());
    }

    public function test_the_same_product_slug_is_rejected_within_one_category(): void
    {
        $category = ProductCategory::create(['name' => 'Мясо кур', 'slug' => 'chicken']);

        Product::create(['product_category_id' => $category->id, 'name' => 'Тушка', 'slug' => 'mix']);

        $this->expectException(QueryException::class);

        Product::create(['product_category_id' => $category->id, 'name' => 'Смесь', 'slug' => 'mix']);
    }

    public function test_the_same_product_slug_can_be_reused_after_the_category_changes(): void
    {
        $first = ProductCategory::create(['name' => 'Первая', 'slug' => 'first']);
        $second = ProductCategory::create(['name' => 'Вторая', 'slug' => 'second']);

        $product = Product::create([
            'product_category_id' => $first->id,
            'name' => 'Товар',
            'slug' => 'mix',
        ]);

        $product->update(['product_category_id' => $second->id]);

        $this->assertSame($second->id, $product->fresh()->product_category_id);
    }

    // ------------------------------------------------------------------
    // 6. Целостность внешнего ключа
    // ------------------------------------------------------------------

    public function test_it_rejects_a_product_that_points_to_a_missing_category(): void
    {
        $this->expectException(QueryException::class);

        Product::create([
            'product_category_id' => 9999,
            'name' => 'Товар',
            'slug' => 'orphan',
        ]);
    }

    public function test_it_rejects_deleting_a_category_that_still_has_products(): void
    {
        $category = ProductCategory::create(['name' => 'Мясо кур', 'slug' => 'chicken']);
        Product::create([
            'product_category_id' => $category->id,
            'name' => 'Тушка курицы',
            'slug' => 'tushka',
        ]);

        // restrictOnDelete: товары не исчезают молча вместе с категорией.
        $this->expectException(QueryException::class);

        $category->delete();
    }

    public function test_it_allows_deleting_a_category_once_its_products_are_removed(): void
    {
        $category = ProductCategory::create(['name' => 'Пустая категория', 'slug' => 'empty']);
        $id = $category->id;

        $category->delete();

        $this->assertNull(ProductCategory::find($id));
    }

    public function test_it_requires_a_category_for_every_product(): void
    {
        // product_category_id NOT NULL: товар без категории невозможен.
        $this->expectException(QueryException::class);

        Product::create(['name' => 'Товар', 'slug' => 'no-category']);
    }

    public function test_it_requires_a_name_and_a_slug_for_both_tables(): void
    {
        $this->expectException(QueryException::class);

        ProductCategory::create(['slug' => 'no-name']);
    }

    /**
     * Необработанное соединение тестовой базы.
     */
    private function connection(): \Illuminate\Database\Connection
    {
        return \Illuminate\Support\Facades\DB::connection();
    }
}
