<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ProductResource: управление текстовыми данными товаров в режиме
 * READ + CREATE + UPDATE.
 *
 * ЧТО ЗДЕСЬ ПРОВЕРЯЕТСЯ
 *
 * Четыре вещи, каждая из которых могла быть сделана неправильно незаметно:
 *
 * 1. Права: создание открыто, удаление закрыто. Проверяются и политикой
 *    (can*()), и набором действий, и тем, что записи переживают визит в
 *    раздел.
 * 2. Составная уникальность slug. Соблазн сделать global unique(slug)
 *    велик — правило короче, — но он запретил бы одинаковый slug в разных
 *    категориях, тогда как база такого запрета не накладывает (составной
 *    индекс products(product_category_id, slug)). Проверяются обе стороны:
 *    дубль внутри категории отвергается, одинаковый slug в разных
 *    категориях проходит.
 * 3. Порядок списка: категория, затем sort_order товара, затем id.
 * 4. Связь is_active с публичным каталогом. Выключенный через админку
 *    товар обязан исчезнуть со страницы категории — это проверяется тем
 *    же ProductCategoryController, что и на Этапе 9.2, без правок
 *    контроллера.
 *
 * ПРО ЧТО ТЕСТЫ НЕ ЗАВИСЯТ ОТ РАЗМЕТКИ
 *
 * Проверки идут через Filament/Livewire API: загрузка страниц, can*(),
 * набор действий, состояние колонок и вызовы Livewire. Ни одного
 * assertSee по конкретному HTML-фрагменту админки: вёрстка Filament
 * меняется между версиями, а смысл ресурса — в правах и сохраняемых
 * значениях, не в тегах.
 *
 * ПРО ПАГИНАЦИЮ
 *
 * Таблица в бою пагинируется, и на первой странице помещается не вся
 * выборка. Тесты порядка и количества поэтому снимают пагинацию на
 * ЭКЗЕМПЛЯРЕ таблицы (getTable() возвращает тот же объект, что и
 * getTableRecords()), а не меняют конфигурацию ресурса: ради проверки
 * производственный список не переписывается.
 */
class ProductResourceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Адрес, которому вход в панель открыт.
     */
    private const ALLOWED_EMAIL = 'admin@local.test';

    /**
     * Адрес, которому вход закрыт, хотя аккаунт существует.
     */
    private const DENIED_EMAIL = 'outsider@local.test';

    private const EGGS_SLUG = 'eggs';

    private const CHICKEN_SLUG = 'chicken';

    private const EGGS_NAME = 'Яйца кур';

    private const CHICKEN_NAME = 'Мясо кур';

    /**
     * Имя и адрес товара, которого в базе нет: используются в проверках
     * создания.
     */
    private const NEW_NAME = 'Яйцо деревянное';

    private const NEW_SLUG = 'yaitso-derevyannoe';

    /**
     * Адрес, который свободен в обеих категориях: на нём проверяется, что
     * одинаковый slug в разных категориях допустим.
     */
    private const SHARED_SLUG = 'obshchiy-adres';

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

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['email' => self::ALLOWED_EMAIL]);

        $this->actingAs($admin);

        return $admin;
    }

    private function eggs(): ProductCategory
    {
        return ProductCategory::query()->where('slug', self::EGGS_SLUG)->firstOrFail();
    }

    private function chicken(): ProductCategory
    {
        return ProductCategory::query()->where('slug', self::CHICKEN_SLUG)->firstOrFail();
    }

    /**
     * Первый товар категории — запись для редактирования.
     */
    private function productIn(ProductCategory $category): Product
    {
        return $category->products()->orderBy('id')->firstOrFail();
    }

    /**
     * Строки таблицы в порядке выдачи, без пагинации.
     *
     * Возвращает тройки [product_category_id, sort_order, id] — ровно те
     * три ключа, которые задают порядок по умолчанию.
     *
     * @return list<array{int, int, int}>
     */
    private function orderedTableRows(): array
    {
        $instance = Livewire::test(ListProducts::class)->instance();

        // Пагинация снимается на том же объекте таблицы, которым пользуется
        // getTableRecords(): иначе проверка видела бы только первую
        // страницу и зависела бы от размера выборки.
        $instance->getTable()->paginated(false);

        $records = $instance->getTableRecords();

        if ($records instanceof Paginator) {
            $records = $records->getCollection();
        }

        return $records
            ->map(fn (Product $product): array => [
                (int) $product->product_category_id,
                (int) $product->sort_order,
                (int) $product->getKey(),
            ])
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Доступ к разделу
    |--------------------------------------------------------------------------
    */

    public function test_a_guest_cannot_open_the_product_resource(): void
    {
        $this->get('/admin/products')
            ->assertStatus(302)
            ->assertRedirectContains('/admin/login');
    }

    public function test_a_guest_cannot_open_the_product_create_page(): void
    {
        $this->get('/admin/products/create')
            ->assertStatus(302)
            ->assertRedirectContains('/admin/login');
    }

    public function test_a_user_outside_the_allow_list_cannot_open_the_product_resource(): void
    {
        $this->actingAs(User::factory()->create(['email' => self::DENIED_EMAIL]));

        $this->get('/admin/products')->assertForbidden();
    }

    public function test_an_allowed_admin_can_open_the_product_resource(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/products')->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Список товаров
    |--------------------------------------------------------------------------
    */

    public function test_the_list_shows_every_seeded_product(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords(Product::all());
    }

    public function test_the_list_shows_the_category_of_each_product(): void
    {
        $this->actingAsAdmin();

        /*
         * Категория выводится как связь category.name. Значение читается из
         * состояния колонки, а не из HTML: колонка обязана отдавать
         * НАЗВАНИЕ категории, а не её числовой ключ, который иначе
         * отобразился бы как «1» или «2».
         */
        Livewire::test(ListProducts::class)
            ->assertTableColumnStateSet('category.name', self::EGGS_NAME, $this->productIn($this->eggs()))
            ->assertTableColumnStateSet('category.name', self::CHICKEN_NAME, $this->productIn($this->chicken()));
    }

    public function test_the_list_shows_the_saved_sort_order_and_slug(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        Livewire::test(ListProducts::class)
            ->assertTableColumnStateSet('name', $product->name, $product)
            ->assertTableColumnStateSet('slug', $product->slug, $product)
            ->assertTableColumnStateSet('sort_order', (int) $product->sort_order, $product);
    }

    public function test_the_list_reads_the_category_without_one_query_per_row(): void
    {
        $this->actingAsAdmin();

        $component = Livewire::test(ListProducts::class);

        /*
         * Считаются ТОЛЬКО запросы подгрузки связи категории. Сортировка по
         * умолчанию тоже обращается к product_categories, но это подзапрос в
         * ORDER BY, а не выборка связи, поэтому он в счёт не входит.
         *
         * Без eager loading на десяти товарах получилось бы десять
         * одинаковых SELECT по одной категории — то есть N+1, который
         * проявляется ровно на этом объёме данных.
         */
        $categoryQueries = 0;

        DB::listen(function ($query) use (&$categoryQueries): void {
            if (preg_match('/from "product_categories" where "product_categories"\."id" in/is', $query->sql) === 1) {
                $categoryQueries++;
            }
        });

        $component->call('sortTable', 'name');

        $this->assertSame(
            1,
            $categoryQueries,
            'Категории должны загружаться одним запросом на всю страницу, а не по одному на строку.',
        );
    }

    public function test_the_default_sort_groups_products_by_category_then_sort_order_then_id(): void
    {
        $this->actingAsAdmin();

        /*
         * КОНТРОЛЬНЫЕ ЗАПИСИ, ЛОМАЮЩИЕ НАИВНУЮ РЕАЛИЗАЦИЮ.
         *
         * В категории с sort_order 1 («Яйца кур») создаётся товар с
         * sort_order 999, а в категории с sort_order 2 («Мясо кур») — с
         * sort_order 0.
         *
         * Если бы список сортировался только по sort_order товара, товары
         * «Мясо кур» встали бы первыми. Если бы — по id, порядок был бы
         * случаным относительно категорий. Правильный порядок обязан
         * учитывать категорию ПЕРВОЙ, и эти две записи делают ошибку
         * заметной.
         */
        $eggs = $this->eggs();
        $chicken = $this->chicken();

        $this->assertSame(1, (int) $eggs->sort_order);
        $this->assertSame(2, (int) $chicken->sort_order);

        $lastInEggs = Product::query()->create([
            'product_category_id' => $eggs->getKey(),
            'name' => 'Контрольный товар яиц',
            'slug' => 'kontrolnyy-yaytsa',
            'is_active' => true,
            'sort_order' => 999,
        ]);

        $firstInChicken = Product::query()->create([
            'product_category_id' => $chicken->getKey(),
            'name' => 'Контрольный товар мяса',
            'slug' => 'kontrolnyy-myaso',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $rows = $this->orderedTableRows();

        $eggsRows = array_values(array_filter(
            $rows,
            fn (array $row): bool => $row[0] === (int) $eggs->getKey(),
        ));

        $chickenRows = array_values(array_filter(
            $rows,
            fn (array $row): bool => $row[0] === (int) $chicken->getKey(),
        ));

        // Все товары первой категории идут раньше всех товаров второй.
        $this->assertSame(
            range(0, count($eggsRows) - 1),
            array_keys($eggsRows),
            'Товары «Яйца кур» должны идти подряд, в начале списка.',
        );

        $this->assertSame(
            range(0, count($chickenRows) - 1),
            array_keys($chickenRows),
            'Товары «Мясо кур» должны идти подряд, после «Яйца кур».',
        );

        // Внутри категории — sort_order по возрастанию.
        $this->assertSame(
            $eggsRows,
            $this->sortedBySortOrderThenId($eggsRows),
            'Внутри категории товары должны идти по возрастанию sort_order, затем id.',
        );

        $this->assertSame(
            $chickenRows,
            $this->sortedBySortOrderThenId($chickenRows),
            'Внутри категории товары должны идти по возрастанию sort_order, затем id.',
        );

        // Контрольные записи на своих местах.
        $this->assertSame(999, $eggsRows[array_key_last($eggsRows)][1]);
        $this->assertSame((int) $lastInEggs->getKey(), $eggsRows[array_key_last($eggsRows)][2]);
        $this->assertSame(0, $chickenRows[0][1]);
        $this->assertSame((int) $firstInChicken->getKey(), $chickenRows[0][2]);
    }

    /**
     * Ожидаемый порядок внутри одной категории: sort_order, затем id.
     *
     * @param  list<array{int, int, int}>  $rows
     * @return list<array{int, int, int}>
     */
    private function sortedBySortOrderThenId(array $rows): array
    {
        usort($rows, fn (array $a, array $b): int => [$a[1], $a[2]] <=> [$b[1], $b[2]]);

        return $rows;
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Создание
    |--------------------------------------------------------------------------
    */

    public function test_the_create_page_is_registered_and_reachable(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/products/create')->assertOk();

        $this->assertTrue(
            Route::has('filament.admin.resources.products.create'),
            'Маршрут создания товара должен существовать: в отличие от категории, товар создавать можно.',
        );
    }

    public function test_creating_a_product_is_allowed_by_policy(): void
    {
        $this->assertTrue(ProductResource::canCreate());
    }

    public function test_the_list_offers_the_create_action(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListProducts::class)
            ->assertActionExists(CreateAction::class);
    }

    public function test_an_admin_can_create_a_product(): void
    {
        $this->actingAsAdmin();

        $before = Product::query()->count();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => $this->eggs()->getKey(),
                'name' => self::NEW_NAME,
                'slug' => self::NEW_SLUG,
                'short_description' => 'Обычное яйцо в деревянной упаковке.',
                'weight' => 'от 1 кг',
                'is_active' => true,
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($before + 1, Product::query()->count());
    }

    public function test_a_created_product_keeps_the_chosen_category_and_text_fields(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => $this->chicken()->getKey(),
                'name' => self::NEW_NAME,
                'slug' => self::NEW_SLUG,
                'short_description' => 'Обычное яйцо в деревянной упаковке.',
                'weight' => 'от 1 кг',
                'shelf_life' => '21 день',
                'packaging' => 'Деревянная упаковка',
                'storage' => 'Прохладное место',
                'additional_info' => 'Хранить в коробке.',
                'is_active' => true,
                'sort_order' => 11,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        /*
         * Проверяется строка целиком, а не одно поле: главные ошибки этого
         * этапа — молчаливая подмена категории (название вместо id) и
         * запись заглушек вместо незаполненных характеристик.
         */
        $product = Product::query()->where('slug', self::NEW_SLUG)->firstOrFail();

        $this->assertSame((int) $this->chicken()->getKey(), (int) $product->product_category_id);
        $this->assertSame(self::NEW_NAME, $product->name);
        $this->assertSame('Обычное яйцо в деревянной упаковке.', $product->short_description);
        $this->assertSame('от 1 кг', $product->weight);
        $this->assertSame('21 день', $product->shelf_life);
        $this->assertSame('Деревянная упаковка', $product->packaging);
        $this->assertSame('Прохладное место', $product->storage);
        $this->assertSame('Хранить в коробке.', $product->additional_info);
        $this->assertSame(11, (int) $product->sort_order);
        $this->assertTrue((bool) $product->is_active);
    }

    public function test_the_category_field_saves_the_id_and_not_the_name(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => $this->eggs()->getKey(),
                'name' => self::NEW_NAME,
                'slug' => self::NEW_SLUG,
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('slug', self::NEW_SLUG)->firstOrFail();

        $this->assertSame((int) $this->eggs()->getKey(), (int) $product->product_category_id);
        $this->assertNotSame(self::EGGS_NAME, $product->product_category_id);
        $this->assertSame(self::EGGS_NAME, $product->category->name);
    }

    public function test_the_category_field_is_required(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => null,
                'name' => self::NEW_NAME,
                'slug' => self::NEW_SLUG,
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['product_category_id' => 'required']);
    }

    public function test_the_name_and_slug_are_required(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => $this->eggs()->getKey(),
                'name' => null,
                'slug' => null,
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'slug' => 'required',
            ]);
    }

    public function test_the_form_offers_only_active_categories_but_keeps_the_current_one(): void
    {
        $this->actingAsAdmin();

        $eggs = $this->eggs();
        $chicken = $this->chicken();

        // Прячем категорию, у которой уже есть товары.
        $chicken->update(['is_active' => false]);

        /*
         * Неактивная категория не должна предлагаться для выбора при
         * СОЗДАНИИ: товар в скрытом разделе не появится на сайте, и это
         * ошибка, заметная только на публичной странице.
         */
        // Ключи приходят целыми числами (id категории), а не строками,
        // поэтому приводятся к строке: иначе сравнение со строковым ключом
        // прошло бы вхолостую или упало вопреки смыслу.
        $createOptions = array_map(
            'strval',
            array_keys(Livewire::test(CreateProduct::class)
                ->instance()
                ->getSchema('form')
                ->getComponent('product_category_id')
                ->getOptions()),
        );

        $this->assertContains((string) $eggs->getKey(), $createOptions);
        $this->assertNotContains((string) $chicken->getKey(), $createOptions);

        /*
         * При РЕДАКТИРОВАНИИ товара из уже скрытой категории её нельзя
         * убирать из списка: поле показало бы пустое значение, и сохранение
         * формы молча обнулило бы внешний ключ, то есть потеряло бы товар
         * из его раздела.
         */
        $productInHiddenCategory = $this->productIn($chicken);

        $editOptions = array_map(
            'strval',
            array_keys(Livewire::test(EditProduct::class, ['record' => $productInHiddenCategory->getKey()])
                ->instance()
                ->getSchema('form')
                ->getComponent('product_category_id')
                ->getOptions()),
        );

        $this->assertContains((string) $chicken->getKey(), $editOptions);
        $this->assertContains((string) $eggs->getKey(), $editOptions);
        $this->assertSame(
            (int) $chicken->getKey(),
            (int) $productInHiddenCategory->fresh()->product_category_id,
            'Категория товара не должна была измениться сама.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Редактирование
    |--------------------------------------------------------------------------
    */

    public function test_an_admin_can_open_a_product_for_editing(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        $this->get('/admin/products/'.$product->getKey().'/edit')->assertOk();
    }

    public function test_an_admin_can_change_the_name(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['name' => 'Переименованный товар'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Переименованный товар', $product->fresh()->name);
    }

    public function test_an_admin_can_move_a_product_to_another_category(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['product_category_id' => $this->chicken()->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame((int) $this->chicken()->getKey(), (int) $product->fresh()->product_category_id);
    }

    public function test_an_admin_can_change_the_slug(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());
        $original = $product->slug;

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['slug' => 'peremenen-sayl'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('peremenen-sayl', $product->fresh()->slug);
        $this->assertNotSame($original, $product->fresh()->slug);
    }

    public function test_an_admin_can_edit_every_characteristic(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->chicken());

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm([
                'short_description' => 'Короткое описание товара.',
                'weight' => 'от 800 г',
                'packaging' => 'Целый, охлаждённый, в вакуумной упаковке',
                'storage' => 'От -18 градусов',
                'shelf_life' => '12 месяцев',
                'additional_info' => 'Произведено из охлаждённого мяса.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $product->fresh();

        $this->assertSame('Короткое описание товара.', $fresh->short_description);
        $this->assertSame('от 800 г', $fresh->weight);
        $this->assertSame('Целый, охлаждённый, в вакуумной упаковке', $fresh->packaging);
        $this->assertSame('От -18 градусов', $fresh->storage);
        $this->assertSame('12 месяцев', $fresh->shelf_life);
        $this->assertSame('Произведено из охлаждённого мяса.', $fresh->additional_info);
    }

    public function test_clearing_a_characteristic_stores_null_and_not_a_placeholder(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm([
                'weight' => 'от 1 кг',
                'shelf_life' => '30 дней',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('от 1 кг', $product->fresh()->weight);

        /*
         * Требование ТЗ: отсутствующие данные нельзя заменять заглушками.
         * Очистка поля обязана дать NULL, а не «-», «нет» или «[УТОЧНИТЬ]».
         */
        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['weight' => null, 'shelf_life' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $product->fresh();

        $this->assertNull($fresh->weight);
        $this->assertNull($fresh->shelf_life);
    }

    public function test_an_admin_can_change_the_sort_order(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['sort_order' => 42])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(42, (int) $product->fresh()->sort_order);
    }

    public function test_the_sort_order_refuses_a_negative_value(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['sort_order' => -1])
            ->call('save')
            ->assertHasFormErrors(['sort_order' => 'min']);
    }

    public function test_an_admin_can_toggle_the_active_flag_from_the_list(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        $this->assertTrue((bool) $product->is_active);

        /*
         * ToggleColumn в Filament 5 сохраняет значение прямым вызовом
         * updateTableColumnState() — тем же методом, который дёргает Alpine в
         * браузере. Проверка идёт через него, а не через click(): клик по
         * переключателю означал бы обращение к разметке, меняющейся от
         * версии к версии.
         */
        Livewire::test(ListProducts::class)
            ->assertTableColumnStateSet('is_active', true, $product)
            ->call('updateTableColumnState', 'is_active', (string) $product->getKey(), false)
            ->assertHasNoActionErrors();

        $this->assertFalse((bool) $product->fresh()->is_active);
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Составная уникальность slug
    |--------------------------------------------------------------------------
    */

    public function test_a_duplicate_slug_inside_one_category_is_refused(): void
    {
        $this->actingAsAdmin();

        $existing = $this->productIn($this->eggs());
        $before = Product::query()->count();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => $this->eggs()->getKey(),
                'name' => 'Второй товар с тем же адресом',
                'slug' => $existing->slug,
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);

        $this->assertSame(
            $before,
            Product::query()->count(),
            'Товар с занятым адресом внутри категории не должен был сохраниться.',
        );
    }

    public function test_validation_errors_are_written_in_russian_and_not_as_raw_translation_keys(): void
    {
        $this->actingAsAdmin();

        $existing = $this->productIn($this->eggs());

        $duplicate = Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => $this->eggs()->getKey(),
                'name' => 'Товар с занятым адресом',
                'slug' => $existing->slug,
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);

        $this->assertSame(
            ['В выбранной категории уже есть товар с таким адресом.'],
            $duplicate->errors()->get('data.slug'),
            'Отказ по адресу должен объясняться по-русски, а не ключом перевода.',
        );

        $empty = Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => $this->eggs()->getKey(),
                'name' => null,
                'slug' => null,
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'slug' => 'required',
            ]);

        $this->assertSame(
            ['Поле «название» обязательно для заполнения.'],
            $empty->errors()->get('data.name'),
            'Обязательное поле должно называться по-русски, а не ключом правила.',
        );

        foreach ([$duplicate, $empty] as $component) {
            foreach ($component->errors()->all() as $message) {
                $this->assertStringNotContainsString(
                    'validation.',
                    $message,
                    'В проекте нет переводов на ru, поэтому ключ правила не должен попадать в панель.',
                );
            }
        }
    }

    public function test_a_duplicate_slug_is_refused_when_moving_a_product_inside_a_category(): void
    {
        $this->actingAsAdmin();

        $eggs = $this->eggs();
        $products = $eggs->products()->orderBy('id')->get();

        $first = $products->first();
        $second = $products->get(1);

        $this->assertNotSame($first->slug, $second->slug);

        Livewire::test(EditProduct::class, ['record' => $second->getKey()])
            ->fillForm(['slug' => $first->slug])
            ->call('save')
            ->assertHasFormErrors(['slug' => 'unique']);

        $this->assertSame(
            $second->slug,
            $second->fresh()->slug,
            'Адрес не должен был измениться при отказе проверки.',
        );
    }

    public function test_the_same_slug_in_two_different_categories_is_allowed(): void
    {
        $this->actingAsAdmin();

        foreach ([$this->eggs(), $this->chicken()] as $category) {
            Livewire::test(CreateProduct::class)
                ->fillForm([
                    'product_category_id' => $category->getKey(),
                    'name' => 'Товар с общим адресом',
                    'slug' => self::SHARED_SLUG,
                    'sort_order' => 0,
                ])
                ->call('create')
                ->assertHasNoFormErrors();
        }

        $this->assertSame(
            2,
            Product::query()->where('slug', self::SHARED_SLUG)->count(),
            'Одинаковый slug в разных категориях должен сохраниться в обеих: индекс составной, не глобальный.',
        );
    }

    public function test_editing_a_product_does_not_conflict_with_its_own_slug(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());
        $own = $product->slug;

        /*
         * Ключевой случай: валидатор по умолчанию игнорирует текущую запись.
         * Если бы проверка шла по базе без этого исключения, сохранение
         * товара без изменения slug падало бы с ошибкой уникальности на самой
         * себе — и редактировать было бы невозможно.
         */
        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['name' => 'То же название, тот же адрес', 'slug' => $own])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($own, $product->fresh()->slug);
    }

    public function test_the_slug_uniqueness_follows_the_newly_chosen_category(): void
    {
        $this->actingAsAdmin();

        $taken = $this->productIn($this->eggs());
        $moved = $this->productIn($this->chicken());

        /*
         * Перенос товара в другую категорию проверяется по адресу ЗАНЯТОМУ в
         * ЦЕЛЕВОЙ категории. Фильтр берётся из состояния формы, а не из
         * записи: если бы он читался из записи, при переносе проверялась бы
         * старая категория и дубль проскочил бы в базу.
         */
        Livewire::test(EditProduct::class, ['record' => $moved->getKey()])
            ->fillForm([
                'product_category_id' => $this->eggs()->getKey(),
                'slug' => $taken->slug,
            ])
            ->call('save')
            ->assertHasFormErrors(['slug' => 'unique']);

        $this->assertNotSame(
            (int) $this->eggs()->getKey(),
            (int) $moved->fresh()->product_category_id,
            'Товар с конфликтующим адресом не должен был сменить категорию.',
        );
        $this->assertSame(
            1,
            $this->eggs()->products()->where('slug', $taken->slug)->count(),
            'В целевой категории должен остаться ровно один товар с этим адресом.',
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Удаление закрыто
    |--------------------------------------------------------------------------
    */

    public function test_deleting_a_product_is_not_allowed_by_policy(): void
    {
        $this->assertFalse(ProductResource::canDelete(Product::query()->firstOrFail()));
    }

    public function test_bulk_deleting_products_is_not_allowed_by_policy(): void
    {
        $this->assertFalse(ProductResource::canDeleteAny());
    }

    public function test_the_list_has_no_delete_action_in_the_header(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListProducts::class)
            ->assertActionDoesNotExist(DeleteAction::class);
    }

    public function test_the_list_has_no_row_delete_action(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListProducts::class)
            ->assertTableActionDoesNotExist(DeleteAction::class, record: $this->productIn($this->eggs()));
    }

    public function test_the_list_has_no_bulk_delete_action(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListProducts::class)
            ->assertTableBulkActionDoesNotExist(DeleteBulkAction::class);
    }

    public function test_the_edit_page_has_no_delete_action(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->assertActionDoesNotExist(DeleteAction::class);
    }

    public function test_no_product_is_deleted_while_the_resource_is_used(): void
    {
        $this->actingAsAdmin();

        $count = Product::query()->count();
        $product = $this->productIn($this->eggs());

        $this->get('/admin/products')->assertOk();
        $this->get('/admin/products/create')->assertOk();
        $this->get('/admin/products/'.$product->getKey().'/edit')->assertOk();

        // Повторный запрос списка: страница отрисовывается дважды.
        $this->get('/admin/products')->assertOk();

        $this->assertSame($count, Product::query()->count());
        $this->assertDatabaseHas('products', ['id' => $product->getKey()]);
    }

    /*
    |--------------------------------------------------------------------------
    | 7. is_active и публичный каталог
    |--------------------------------------------------------------------------
    */

    public function test_deactivating_a_product_removes_it_from_its_public_page(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse((bool) $product->fresh()->is_active);

        $this->get('/products/'.self::EGGS_SLUG)
            ->assertOk()
            ->assertDontSee($product->name);
    }

    public function test_reactivating_a_product_brings_it_back_to_its_public_page(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['is_active' => false])
            ->call('save');

        $this->get('/products/'.self::EGGS_SLUG)->assertDontSee($product->name);

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['is_active' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue((bool) $product->fresh()->is_active);

        $this->get('/products/'.self::EGGS_SLUG)
            ->assertOk()
            ->assertSee($product->name);
    }

    public function test_deactivating_one_product_leaves_the_rest_of_the_category_on_page(): void
    {
        $this->actingAsAdmin();

        $hidden = $this->productIn($this->eggs());
        $visible = $this->eggs()->products()->where('id', '!=', $hidden->getKey())->firstOrFail();

        Livewire::test(EditProduct::class, ['record' => $hidden->getKey()])
            ->fillForm(['is_active' => false])
            ->call('save');

        $this->get('/products/'.self::EGGS_SLUG)
            ->assertOk()
            ->assertDontSee($hidden->name)
            ->assertSee($visible->name);
    }

    public function test_a_newly_created_inactive_product_does_not_appear_on_the_public_page(): void
    {
        $this->actingAsAdmin();

        $chicken = $this->chicken();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => $chicken->getKey(),
                'name' => 'Скрытый при создании товар',
                'slug' => 'skrytyy-tovar',
                'is_active' => false,
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', [
            'slug' => 'skrytyy-tovar',
            'is_active' => false,
        ]);

        $this->get('/products/'.self::CHICKEN_SLUG)
            ->assertOk()
            ->assertDontSee('Скрытый при создании товар');
    }

    public function test_the_public_catalog_pages_still_work(): void
    {
        $this->actingAsAdmin();

        $this->get('/')->assertOk();
        $this->get('/products/'.self::EGGS_SLUG)->assertOk();
        $this->get('/products/'.self::CHICKEN_SLUG)->assertOk();
    }

    public function test_an_unknown_category_page_is_still_a_404(): void
    {
        $this->actingAsAdmin();

        $this->get('/products/net-takoy-kategorii')->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | 8. Изображение товара
    |--------------------------------------------------------------------------
    |
    | Загрузка изображений перенесена на страницу поля FileUpload
    | (диск, каталог, типы, замена, очистка) в ProductImageUploadTest.
    | Здесь остаётся привязка к правам и целостности формы: существующий
    | редактор без изменений изображения не трогает ни поле, ни файл, а
    | создание товара с изображением кладёт в products.image относительный
    | путь публичного диска.
    |
    | История: до Этапа 9.4 здесь был тест, который специально подтверждал
    | ОТСУТСТВИЕ FileUpload в форме. Он противоречил новой функциональности
    | и заменён проверками ниже (см. ProductImageUploadTest — полный сценарий
    | загрузки, замены и очистки файла).
    */

    public function test_editing_other_fields_leaves_the_image_path_untouched(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());
        $imageBefore = $product->image;

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['name' => 'Переименованный товар'])
            ->call('save');

        $this->assertSame(
            $imageBefore,
            $product->fresh()->image,
            'Правка названия не должна изменять изображение.',
        );
    }

    /**
     * В форме товара НЕТ поля загрузки файла — только показ пути.
     *
     * Фотографии каталога лежат в public/images/products и обновляются через
     * Git. Загрузка через панель дала бы путь на временный диск storage, который
     * не переживает деплой, поэтому её убрали целиком, а не просто заблокировали.
     *
     * Поле image_path остаётся видимым: администратор должен видеть, какая
     * фотография прописана у товара, иначе пришлось бы лезть в базу.
     */
    public function test_the_form_shows_the_photo_path_and_offers_no_upload(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        $this->assertSame('images/products/egg-c0.jpg', $product->image);

        $form = Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->instance()
            ->getSchema('form');

        $components = $form->getComponents(withHidden: true);

        $fieldNames = array_map(
            static fn ($component): ?string => $component->getName(),
            $components,
        );

        $this->assertContains('image_path', $fieldNames, 'Показ пути к фотографии должен остаться.');
        $this->assertNotContains('image', $fieldNames, 'Поля image в форме быть не должно.');

        // Компонент загрузки файлов в форме отсутствует полностью: пока он
        // есть, загрузку можно случайно вернуть и снова получить временные
        // фотографии, исчезающие при деплое.
        $this->assertFalse(
            $this->formHasFileUpload($components),
            'В форме не должно быть ни одного компонента загрузки файлов.',
        );
    }

    /**
     * Создание товара с файлом НЕ записывает путь в products.image.
     *
     * Загрузка в панели отключена (ProductForm, поле image помечено
     * disabled): фотографии каталога лежат в public/images/products и
     * обновляются через Git. Поэтому товар, созданный из админки, остаётся
     * без картинки, пока её не добавят в репозиторий.
     *
     * Полный сценарий путей в базе — в ProductImageUploadTest.
     */
    public function test_creating_a_product_with_an_image_stores_no_public_disk_path(): void
    {
        $this->actingAsAdmin();
        Storage::fake('public');

        $category = $this->eggs();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => $category->getKey(),
                'name' => self::NEW_NAME,
                'slug' => self::NEW_SLUG,
                'image' => UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg'),
            ])
            ->call('create');

        $product = Product::query()->where('slug', self::NEW_SLUG)->firstOrFail();

        $this->assertNull(
            $product->image,
            'Путь на диск storage не должен попадать в базу: страница читает public/.',
        );
    }

    /**
     * Есть ли в компонентах формы хоть один элемент загрузки файлов.
     *
     * @param  iterable<Component>  $components
     */
    private function formHasFileUpload(iterable $components): bool
    {
        foreach ($components as $component) {
            if ($component instanceof FileUpload) {
                return true;
            }

            if (method_exists($component, 'getChildSchemas')) {
                foreach ($component->getChildSchemas(withHidden: true) as $child) {
                    if ($this->formHasFileUpload($child->getComponents(withHidden: true))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
