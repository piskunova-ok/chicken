<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\ProductCategories\Pages\EditProductCategory;
use App\Filament\Resources\ProductCategories\Pages\ListProductCategories;
use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ProductCategoryResource: управление существующими категориями в режиме
 * READ + UPDATE.
 *
 * ЧТО ЗДЕСЬ ПРОВЕРЯЕТСЯ
 *
 * Три разных вещи, которые легко спутать:
 *
 * 1. Кто может попасть в раздел. Проверяется настоящим HTTP-запросом, потому
 *    что доступ закрывает middleware панели, а не сам Resource.
 * 2. Что именно раздел позволяет. Проверяется через API Filament/Livewire:
 *    набор зарегистрированных страниц, политика can*() и наличие действий.
 *    Привязка к HTML здесь была бы хрупкой — вёрстка Filament меняется от
 *    версии к версии, а поведениеResource нет.
 * 3. Что правка действительно попадает в базу. Проверяется перечитыванием
 *    модели из БД: правка, которая живёт только в интерфейсе, не является
 *    правкой.
 *
 * ОТДЕЛЬНО ПРО ПУБЛИЧНЫЙ САЙТ
 *
 * Выключение is_active через админку уводит страницу категории в 404 — это
 * ожидаемое поведение ProductCategoryController, а не поломка. Проверяется
 * явно, в обе стороны, чтобы изменение флага нельзя было принять за ошибку,
 * а возвращение флага — за «сайт сломался сам».
 *
 * Тесты работают на sqlite :memory: (phpunit.xml) и не касаются рабочей
 * базы: RefreshDatabase пересоздаёт схему в памяти на каждый тест.
 */
class ProductCategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Адрес, которому вход в панель открыт.
     */
    private const ALLOWED_EMAIL = 'admin@local.test';

    /**
     * Адрес, которому вход закрыт, хотя аутентификация пройдена.
     */
    private const DENIED_EMAIL = 'outsider@local.test';

    /**
     * Название категории яиц в том виде, в каком его заводит seeder.
     */
    private const EGGS_NAME = 'Яйца кур';

    /**
     * Название категории мяса в том виде, в каком его заводит seeder.
     */
    private const CHICKEN_NAME = 'Мясо кур';

    protected function setUp(): void
    {
        parent::setUp();

        // Список доступа задаётся здесь, а не читается из .env: тест не
        // должен зависеть от того, что настроено на машине.
        config()->set('admin.panel_access_emails', [self::ALLOWED_EMAIL]);

        // Панель нужна Livewire-компонентам ресурса: вне запроса к панели
        // «текущей» панели нет, и getPanel() вернул бы null.
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->seed(ProductCatalogSeeder::class);
    }

    /**
     * Разрешённый администратор, под которым выполняются проверки разделов.
     */
    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['email' => self::ALLOWED_EMAIL]);

        $this->actingAs($admin);

        return $admin;
    }

    private function eggs(): ProductCategory
    {
        return ProductCategory::query()->where('slug', 'eggs')->firstOrFail();
    }

    private function chicken(): ProductCategory
    {
        return ProductCategory::query()->where('slug', 'chicken')->firstOrFail();
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Доступ к разделу
    |--------------------------------------------------------------------------
    */

    public function test_a_guest_cannot_open_the_category_resource(): void
    {
        $this->get('/admin/product-categories')
            ->assertStatus(302)
            ->assertRedirectContains('/admin/login');
    }

    public function test_a_guest_cannot_open_the_category_edit_page(): void
    {
        $this->get('/admin/product-categories/'.$this->eggs()->getKey().'/edit')
            ->assertStatus(302)
            ->assertRedirectContains('/admin/login');
    }

    public function test_a_user_outside_the_allow_list_cannot_open_the_category_resource(): void
    {
        $user = User::factory()->create(['email' => self::DENIED_EMAIL]);

        $this->actingAs($user)->get('/admin/product-categories')->assertForbidden();
    }

    public function test_an_allowed_admin_can_open_the_category_resource(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/product-categories')->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Список категорий
    |--------------------------------------------------------------------------
    */

    public function test_the_list_shows_both_categories(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListProductCategories::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$this->eggs(), $this->chicken()])
            ->assertSee(self::EGGS_NAME)
            ->assertSee(self::CHICKEN_NAME);
    }

    public function test_the_list_shows_the_slug_of_each_category(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListProductCategories::class)
            ->assertSuccessful()
            ->assertCanRenderTableColumn('slug')
            ->assertSee('eggs')
            ->assertSee('chicken');
    }

    public function test_the_list_orders_categories_by_sort_order(): void
    {
        $this->actingAsAdmin();

        // Порядок проверяется по порядку отрисовки строк, а не по набору:
        // сортировка sort_order — это ровно то, ради чего существует колонка.
        // eggs идёт первым (sort_order 1), chicken вторым (sort_order 2).
        Livewire::test(ListProductCategories::class)
            ->assertSuccessful()
            ->assertSeeInOrder([self::EGGS_NAME, self::CHICKEN_NAME]);
    }

    public function test_the_default_sort_survives_equal_sort_orders(): void
    {
        $this->actingAsAdmin();

        // Совпадающий sort_order — обычное следствие ручной правки. Второй
        // ключ (id) обязан удерживать выдачу стабильной, иначе одна и та же
        // страница выглядела бы по-разному при простом обновлении.
        $this->eggs()->update(['sort_order' => 1]);
        $this->chicken()->update(['sort_order' => 1]);

        Livewire::test(ListProductCategories::class)
            ->assertSuccessful()
            ->assertSeeInOrder([self::EGGS_NAME, self::CHICKEN_NAME]);
    }

    public function test_the_list_reports_the_number_of_products_per_category(): void
    {
        $this->actingAsAdmin();

        // 3 позиции у яиц и 7 у мяса — числа из ProductCatalogSeeder.
        Livewire::test(ListProductCategories::class)
            ->assertSuccessful()
            ->assertTableColumnStateSet('products_count', 3, $this->eggs())
            ->assertTableColumnStateSet('products_count', 7, $this->chicken());
    }

    public function test_the_products_count_does_not_cost_one_query_per_row(): void
    {
        $this->actingAsAdmin();

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(ListProductCategories::class)->assertSuccessful();

        /*
         * Ключевая проверка. products_count обязан приходить подзапросом
         * внутри SELECT категорий, а не отдельным запросом на строку.
         * Признак N+1 здесь один: самостоятельный SELECT из products,
         * не вложенный в подзапрос. Счётчик подзапроса в лог не попадает,
         * поэтому ненулевой результат означал бы ровно то, что вернулось
         * ленивое обращение к связи.
         */
        $standaloneProductQueries = array_filter(
            DB::getQueryLog(),
            fn (array $query): bool => str_starts_with(
                strtolower(trim($query['query'])),
                'select * from "products"',
            ),
        );

        DB::disableQueryLog();

        $this->assertSame(
            [],
            $standaloneProductQueries,
            'Таблица обращается к товарам отдельным запросом на строку (N+1) — счётчик должен приходить из withCount.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Правка категории
    |--------------------------------------------------------------------------
    */

    public function test_an_admin_can_open_a_category_for_editing(): void
    {
        $this->actingAsAdmin();

        Livewire::test(EditProductCategory::class, ['record' => $this->eggs()->getKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('name')
            ->assertFormFieldExists('is_active')
            ->assertFormFieldExists('sort_order');
    }

    public function test_an_admin_can_rename_a_category_and_the_change_reaches_the_database(): void
    {
        $this->actingAsAdmin();

        Livewire::test(EditProductCategory::class, ['record' => $this->eggs()->getKey()])
            ->fillForm(['name' => 'Яйца кур домашние'])
            ->call('save')
            ->assertHasNoFormErrors();

        // Перечитывание из БД, а не проверка формы: правка, которая осталась
        // только в интерфейсе, не считается правкой.
        $this->assertDatabaseHas('product_categories', [
            'id' => $this->eggs()->getKey(),
            'name' => 'Яйца кур домашние',
        ]);

        $this->assertSame('Яйца кур домашние', $this->eggs()->fresh()->name);
    }

    public function test_an_admin_can_change_the_sort_order_and_the_change_reaches_the_database(): void
    {
        $this->actingAsAdmin();

        Livewire::test(EditProductCategory::class, ['record' => $this->chicken()->getKey()])
            ->fillForm(['sort_order' => 42])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('product_categories', [
            'id' => $this->chicken()->getKey(),
            'sort_order' => 42,
        ]);
    }

    public function test_the_sort_order_refuses_a_negative_value(): void
    {
        $this->actingAsAdmin();

        // Колонка объявлена unsignedInteger, поэтому отрицательное значение
        // отсекается формой, а не ошибкой базы.
        Livewire::test(EditProductCategory::class, ['record' => $this->eggs()->getKey()])
            ->fillForm(['sort_order' => -1])
            ->call('save')
            ->assertHasFormErrors(['sort_order']);

        $this->assertSame(1, $this->eggs()->fresh()->sort_order);
    }

    public function test_the_sort_order_accepts_zero(): void
    {
        $this->actingAsAdmin();

        // Ноль допустим: так объявлено значение по умолчанию в миграции.
        Livewire::test(EditProductCategory::class, ['record' => $this->eggs()->getKey()])
            ->fillForm(['sort_order' => 0])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(0, $this->eggs()->fresh()->sort_order);
    }

    public function test_the_name_is_required(): void
    {
        $this->actingAsAdmin();

        Livewire::test(EditProductCategory::class, ['record' => $this->eggs()->getKey()])
            ->fillForm(['name' => ''])
            ->call('save')
            ->assertHasFormErrors(['name']);
    }

    public function test_a_required_error_is_shown_in_russian_not_as_a_key(): void
    {
        $this->actingAsAdmin();

        $component = Livewire::test(EditProductCategory::class, ['record' => $this->eggs()->getKey()])
            ->fillForm(['name' => ''])
            ->call('save')
            ->assertHasFormErrors(['name' => 'required']);

        $this->assertSame(
            ['Поле «название» обязательно для заполнения.'],
            $component->errors()->get('data.name'),
            'Ошибка required у категории должна звучать по-русски, а не ключом validation.required.',
        );
    }

    public function test_a_too_long_category_name_is_rejected_with_a_russian_message(): void
    {
        $this->actingAsAdmin();

        $component = Livewire::test(EditProductCategory::class, ['record' => $this->eggs()->getKey()])
            ->fillForm(['name' => str_repeat('а', 256)])
            ->call('save')
            ->assertHasFormErrors(['name' => 'max']);

        $message = $component->errors()->get('data.name')[0] ?? '';

        $this->assertMatchesRegularExpression('/[а-яё]/iu', $message, 'Ошибка max должна быть на русском.');
        $this->assertStringNotContainsString(
            'validation.',
            $message,
            'Внутри ошибки не должно быть сырых ключей перевода.',
        );
        $this->assertStringContainsString('255', $message, 'В сообщении должна быть видна граница длины.');

        $this->assertSame('Яйца кур', $this->eggs()->fresh()->name, 'Запись не должна измениться при отказе.');
    }

    public function test_an_admin_can_deactivate_a_category_from_the_form(): void
    {
        $this->actingAsAdmin();

        Livewire::test(EditProductCategory::class, ['record' => $this->eggs()->getKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($this->eggs()->fresh()->is_active);
    }

    public function test_an_admin_can_toggle_the_active_flag_right_in_the_list(): void
    {
        $this->actingAsAdmin();

        /*
         * ToggleColumn в Filament 5 сохраняет значение не через действие, а
         * прямым вызовом updateTableColumnState() — тем же методом, который
         * дёргает Alpine-компонент переключателя в браузере. Проверка идёт
         * через него, а не через click(): клик по toggle в тесте означал бы
         * обращение к разметке, которая меняется от версии к версии.
         */
        Livewire::test(ListProductCategories::class)
            ->assertTableColumnStateSet('is_active', true, $this->eggs())
            ->call('updateTableColumnState', 'is_active', (string) $this->eggs()->getKey(), false)
            ->assertHasNoActionErrors();

        $this->assertFalse($this->eggs()->fresh()->is_active);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Slug неизменен
    |--------------------------------------------------------------------------
    */

    public function test_the_slug_is_shown_but_cannot_be_edited(): void
    {
        $this->actingAsAdmin();

        Livewire::test(EditProductCategory::class, ['record' => $this->eggs()->getKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('slug')
            ->assertFormFieldDisabled('slug');
    }

    public function test_a_submitted_slug_does_not_change_the_record(): void
    {
        $this->actingAsAdmin();

        /*
         * Ключевая проверка. slug отправляется в форме вместе с остальными
         * данными, но dehydrated(false) не даёт ему попасть в обновление.
         * Если бы защита держалась только на disabled(), значение записалось
         * бы, и адрес /products/{slug} разошёлся бы с ключом в
         * config/catalog.php.
         */
        Livewire::test(EditProductCategory::class, ['record' => $this->eggs()->getKey()])
            ->fillForm([
                'name' => self::EGGS_NAME,
                'slug' => 'podmenennyi-slug',
                'sort_order' => 1,
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('product_categories', [
            'id' => $this->eggs()->getKey(),
            'slug' => 'eggs',
        ]);

        $this->assertNotSame('podmenennyi-slug', $this->eggs()->fresh()->slug);
    }

    public function test_editing_a_category_does_not_rewrite_its_slug(): void
    {
        $this->actingAsAdmin();

        // Обычная правка: slug обязан остаться прежним даже без попытки
        // его подменить — то есть в форме нет и неявного обнуления поля.
        Livewire::test(EditProductCategory::class, ['record' => $this->chicken()->getKey()])
            ->fillForm(['name' => 'Мясо кур охлаждённое', 'sort_order' => 2])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('product_categories', [
            'id' => $this->chicken()->getKey(),
            'slug' => 'chicken',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Создание закрыто
    |--------------------------------------------------------------------------
    */

    public function test_creating_a_category_is_not_allowed_by_policy(): void
    {
        $this->assertFalse(ProductCategoryResource::canCreate());
    }

    public function test_the_create_page_is_not_registered(): void
    {
        // Страницы создания нет в getPages(), поэтому нет и маршрута: не
        // «страница есть и отказывает», а адреса не существует вовсе.
        $this->assertNull(
            Route::getRoutes()->getByName('filament.admin.resources.product-categories.create'),
        );
    }

    public function test_the_create_address_returns_not_found(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/product-categories/create')->assertNotFound();
    }

    public function test_the_list_has_no_create_action(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListProductCategories::class)
            ->assertSuccessful()
            ->assertActionDoesNotExist(CreateAction::class);
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Удаление закрыто
    |--------------------------------------------------------------------------
    */

    public function test_deleting_a_category_is_not_allowed_by_policy(): void
    {
        $this->assertFalse(ProductCategoryResource::canDelete($this->eggs()));
    }

    public function test_bulk_deleting_is_not_allowed_by_policy(): void
    {
        $this->assertFalse(ProductCategoryResource::canDeleteAny());
    }

    public function test_the_edit_page_has_no_delete_action(): void
    {
        $this->actingAsAdmin();

        Livewire::test(EditProductCategory::class, ['record' => $this->eggs()->getKey()])
            ->assertSuccessful()
            ->assertActionDoesNotExist(DeleteAction::class);
    }

    public function test_the_list_has_no_bulk_delete_action(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListProductCategories::class)
            ->assertSuccessful()
            ->assertTableBulkActionDoesNotExist(DeleteBulkAction::class);
    }

    public function test_the_categories_survive_a_full_visit_to_the_resource(): void
    {
        $this->actingAsAdmin();

        // Обе категории и все товары переживают и просмотр списка, и
        // редактирование: ни одно действие раздела не способно убрать запись.
        Livewire::test(ListProductCategories::class)->assertSuccessful();
        Livewire::test(EditProductCategory::class, ['record' => $this->eggs()->getKey()])
            ->fillForm(['name' => self::EGGS_NAME])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(2, ProductCategory::count());
        $this->assertSame(10, Product::count());
        $this->assertDatabaseHas('product_categories', ['slug' => 'eggs']);
        $this->assertDatabaseHas('product_categories', ['slug' => 'chicken']);
    }

    /*
    |--------------------------------------------------------------------------
    | 7. Связь с публичным сайтом
    |--------------------------------------------------------------------------
    */

    public function test_deactivating_a_category_makes_its_public_page_return_not_found(): void
    {
        $this->actingAsAdmin();

        $this->get('/products/eggs')->assertOk();

        Livewire::test(EditProductCategory::class, ['record' => $this->eggs()->getKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        // 404 здесь — ожидаемое поведение ProductCategoryController, а не
        // поломка: для посетителя неактивной категории не существует.
        $this->get('/products/eggs')->assertNotFound();
    }

    public function test_reactivating_a_category_brings_its_public_page_back(): void
    {
        $this->actingAsAdmin();

        $this->eggs()->update(['is_active' => false]);
        $this->get('/products/eggs')->assertNotFound();

        Livewire::test(EditProductCategory::class, ['record' => $this->eggs()->getKey()])
            ->fillForm(['is_active' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->get('/products/eggs')->assertOk();
    }

    public function test_deactivating_one_category_leaves_the_other_reachable(): void
    {
        $this->actingAsAdmin();

        $this->eggs()->update(['is_active' => false]);

        $this->get('/products/eggs')->assertNotFound();
        $this->get('/products/chicken')->assertOk();
        $this->get('/')->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | 8. Раздел не трогает остальное
    |--------------------------------------------------------------------------
    */

    public function test_the_resource_ships_no_relations(): void
    {
        // Просмотр товаров — задача Этапа 9.3 вместе с ProductResource.
        $this->assertSame([], ProductCategoryResource::getRelations());
    }

    public function test_the_public_catalog_is_untouched_by_the_resource(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListProductCategories::class)->assertSuccessful();
        Livewire::test(EditProductCategory::class, ['record' => $this->chicken()->getKey()])
            ->assertSuccessful();

        $this->assertSame(2, ProductCategory::count());
        $this->assertSame(10, Product::count());

        $this->get('/')->assertOk();
        $this->get('/products/eggs')->assertOk();
        $this->get('/products/chicken')->assertOk();
    }
}
