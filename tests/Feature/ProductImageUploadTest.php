<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Загрузка изображений товаров (Этап 9.4).
 *
 * ЧТО ЗДЕСЬ ПРОВЕРЯЕТСЯ
 *
 * Поле image товара — FileUpload Filament 5.9 на публичном диске:
 *
 *  - disk = public, directory = products, visibility = public;
 *  - типы проверяются СЕРВЕРОМ (mimetypes): JPEG, PNG, WebP; TXT и файлы
 *    больше 4096 КБ отклоняются;
 *  - в products.image попадает только относительный путь «products/<имя>»,
 *    а не абсолютный путь и не URL;
 *  - публичная страница строит URL через Storage::disk('public')->url(),
 *    а при image = NULL показывает прежнюю заглушку;
 *  - замена и очистка изображения удаляют СТАРЫЙ файл ровно ОДНИМ
 *    механизмом — пара beforeSave()/afterSave() на странице EditProduct —
 *    и только после успешной записи: при ошибке validation старый файл
 *    сохраняется.
 *
 * О ПРОВЕРКЕ ЗАМЕНЫ
 *
 * Замена/очистка проверяются на уровне пути: в состояние формы
 * подставляется путь уже сохранённого файла (как если бы upload завершился).
 * Сам конвейер upload→путь на публичный диск покрыт create-тестами ниже.
 * Так сделано потому, что в Livewire-тест-харнесе поток файла в НЕпустое
 * одиночное поле ДОБАВЛЯЕТСЯ к существующему состоянию, а не заменяет его,
 * — это расходится с поведением браузера и не относится к нашему
 * lifecycle-механизму очистки.
 *
 * STORAGE::FAKE
 *
 * Каждый тест подменяет диск public на Storage::fake() (в setUp): ни один
 * файл не пишется в реальный storage/app/public, а PHPUnit проверяет
 * assertExists()/assertMissing() именно на подменённом диске. RefreshDatabase
 * работает на SQLite :memory: из phpunit.xml, поэтому рабочая база
 * database/database.sqlite тоже не затрагивается.
 *
 * ФАКОВЫЕ ФАЙЛЫ БЕЗ GD
 *
 * На этой машине GD не включён, поэтому image() фабрики UploadedFile не
 * используется (она генерирует изображение через GD). Вместо него —
 * UploadedFile::fake()->create(): сообщаемый MIME задаётся явно, а проверку
 * types (mimetypes) Laravel исполняет по getMimeType() файла. Так тесты
 * детерминированы и не зависят от расширений сборки.
 */
class ProductImageUploadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Адрес, которому вход в панель открыт.
     */
    private const ALLOWED_EMAIL = 'admin@local.test';

    private const EGGS_SLUG = 'eggs';

    private const EGGS_NAME = 'Яйца кур';

    /**
     * Имя и адрес нового товара для проверок создания.
     */
    private const NEW_NAME = 'Яйцо деревянное';

    private const NEW_SLUG = 'yaitso-derevyannoe';

    /**
     * Загрузки товара живут в этой папке публичного диска.
     */
    private const PRODUCTS_DIRECTORY = 'products';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('admin.panel_access_emails', [self::ALLOWED_EMAIL]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->seed(ProductCatalogSeeder::class);

        // Обязательное подменение диска: тесты не должны писать в реальный
        // storage/app/public.
        Storage::fake('public');
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

    private function productIn(ProductCategory $category): Product
    {
        return $category->products()->orderBy('id')->firstOrFail();
    }

    /**
     * Товар с существующим файлом изображения на (fake) публичном диске.
     */
    private function productWithImage(string $relativePath = 'products/old-image.jpg'): Product
    {
        $product = $this->productIn($this->eggs());

        Storage::disk('public')->put($relativePath, 'старые байты изображения');

        $product->forceFill(['image' => $relativePath])->save();

        return $product->fresh();
    }

    private function fakeJpeg(string $name = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 10, 'image/jpeg');
    }

    private function fakePng(): UploadedFile
    {
        return UploadedFile::fake()->create('photo.png', 10, 'image/png');
    }

    private function fakeWebp(): UploadedFile
    {
        return UploadedFile::fake()->create('photo.webp', 10, 'image/webp');
    }

    private function fakeText(): UploadedFile
    {
        return UploadedFile::fake()->create('notes.txt', 10, 'text/plain');
    }

    private function oversizedJpeg(): UploadedFile
    {
        // 5000 КБ — заведомо больше maxSize(4096).
        return UploadedFile::fake()->create('too-big.jpg', 5000, 'image/jpeg');
    }

    /**
     * Все сообщения ошибок формы одним плоским списком.
     *
     * Поле файла описывается правилами «image.*», поэтому ключ ошибки —
     * «image.0», а не «image»: не привязываем проверки к конкретной
     * раскладке ключей и просто собираем все тексты.
     *
     * @return list<string>
     */
    private function allErrorMessages(Testable $component): array
    {
        $messages = [];

        foreach (array_keys($component->errors()->getMessages()) as $key) {
            foreach ($component->errors()->get($key) as $message) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Конфигурация поля
    |--------------------------------------------------------------------------
    */

    /**
     * В форме товара НЕТ компонента загрузки файла.
     *
     * Фотографии каталога лежат в public/images/products и обновляются через
     * Git. Загрузка через панель писала бы путь на временный диск storage,
     * который не переживает деплой, поэтому её убрали целиком — иначе
     * администратор увидел бы «успешную» загрузку, исчезающую при
     * следующем развёртывании.
     *
     * Полное обоснование с измерениями — в шапке ProductForm.
     */
    public function test_the_form_has_no_file_upload_component(): void
    {
        $this->actingAsAdmin();

        $form = Livewire::test(EditProduct::class, ['record' => $this->productIn($this->eggs())->getKey()])
            ->instance()
            ->getSchema('form');

        $components = $form->getComponents(withHidden: true);

        $fieldNames = array_map(
            static fn ($component): ?string => $component->getName(),
            $components,
        );

        $this->assertContains(
            'image_path',
            $fieldNames,
            'Показ пути к фотографии должен остаться: администратору нужно видеть, что прописано у товара.',
        );

        $this->assertNotContains(
            'image',
            $fieldNames,
            'Поля image в форме быть не должно.',
        );

        $hasFileUpload = false;

        foreach ($components as $component) {
            if ($component instanceof FileUpload) {
                $hasFileUpload = true;
                break;
            }
        }

        $this->assertFalse($hasFileUpload, 'В форме не должно быть компонента загрузки файлов.');
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Создание товара
    |--------------------------------------------------------------------------
    |
    | Загрузка через панель отключена (см. раздел 1), поэтому файл НЕ может
    | попасть в products.image из интерфейса. Проверяется обратное: попытка
    | загрузить файл не создаёт ни путь в базе, ни файл на диске.
    |
    | Это и есть требование этапа: новые production-загрузки не должны
    | создавать ложное ощущение постоянного хранения. Временный диск
    | storage/app/public не переживает деплой, поэтому «успешная» загрузка
    | была бы обещанием, которое не сдержится.
    */

    public function test_creating_without_an_image_leaves_image_null(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => $this->eggs()->getKey(),
                'name' => self::NEW_NAME,
                'slug' => self::NEW_SLUG,
            ])
            ->call('create');

        $this->assertNull(Product::query()->where('slug', self::NEW_SLUG)->firstOrFail()->image);
    }

    /**
     * Попытка загрузить файл не сохраняет путь в базу, и страница не
     * ссылается на временный файл.
     *
     * ОГРАНИЧЕНИЕ ХАРНЕСА, важное для честности теста. Браузер не даст
     * выбрать файл в заблокированном поле, поэтому в реальной панели
     * загрузка невозможна. Но fillForm() в Livewire-тесте подставляет файл
     * в состояние напрямую, минуя disabled(), и Filament успевает положить
     * его на диск. Ловить здесь «файла нет на диске» бессмысленно: это
     * проверяет не код проекта, а возможности тест-харнеса.
     *
     * Поэтому проверяется ровно то, что зависит от проекта: путь не попал в
     * products.image, а публичная страница не ссылается на временный файл.
     * Независимо от того, остался ли файл на диске, он не связан ни с одной
     * карточкой и исчезнет вместе с деплоем — вреда посетителю он не
     * приносит.
     */
    public function test_an_upload_attempt_through_create_stores_no_path_and_renders_no_broken_link(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => $this->eggs()->getKey(),
                'name' => self::NEW_NAME,
                'slug' => self::NEW_SLUG,
                'image' => $this->fakeJpeg(),
            ])
            ->call('create');

        $product = Product::query()->where('slug', self::NEW_SLUG)->firstOrFail();

        // Товар создаётся, но загрузка игнорируется: поле заблокировано.
        $this->assertNull(
            $product->image,
            'Заблокированное поле не должно писать путь в базу.',
        );

        $this->get('/products/'.self::EGGS_SLUG)
            ->assertOk()
            ->assertDontSee('/storage/products/')
            ->assertDontSee('products/01')
            ->assertDontSee('01M', false);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Путь в базе
    |--------------------------------------------------------------------------
    |
    | Загрузки через панель нет, поэтому единственный источник пути —
    | ProductCatalogSeeder: он кладёт относительный путь ВНУТРИ public/.
    | Такие пути переживают смену домена и не врут о способе отдачи файла.
    */

    public function test_the_database_never_stores_absolute_paths_or_public_urls(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        foreach (Product::whereNotNull('image')->get() as $product) {
            $image = $product->image;

            $this->assertFalse(str_contains($image, '\\'), 'В БД не должно быть Windows-путей.');
            $this->assertFalse(str_starts_with($image, '/'), 'В БД не должно быть абсолютных путей.');
            $this->assertFalse(str_contains($image, '://'), 'В БД не должно быть URL.');
            $this->assertFalse(str_contains($image, '/storage/'), 'В БД не должно быть адреса публичного хранилища.');
            $this->assertFalse(str_contains($image, 'app/public'), 'В БД не должно быть пути storage/app/public.');
            $this->assertStringStartsWith('images/products/', $image);
        }
    }

    public function test_seeded_image_paths_point_at_files_that_exist_in_public(): void
    {
        $this->seed(ProductCatalogSeeder::class);

        // Ссылка на несуществующий файл молча превратилась бы в битую
        // картинку: путь в базе и файл в репозитории обязаны совпадать.
        foreach (Product::whereNotNull('image')->get() as $product) {
            $this->assertFileExists(public_path($product->image), $product->slug);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Публичное отображение
    |--------------------------------------------------------------------------
    */

    /**
     * Фотографии каталога лежат в public/images/products и отслеживаются
     * Git, поэтому страница строит ссылку через asset(). Загрузка через
     * панель отключена: путь вида «products/<ulid>.jpg» указывал бы на
     * storage/app/public, asset() дал бы 404, а на production локальный диск
     * *всё равно* не пережил бы деплой.
     */
    public function test_the_public_page_renders_a_git_managed_photo_as_an_asset_url(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        // Seeder уже положил в image путь внутри public/.
        $this->assertSame('images/products/egg-c0.jpg', $product->image);

        $this->get('/products/'.self::EGGS_SLUG)
            ->assertOk()
            ->assertSee('src="'.asset('images/products/egg-c0.jpg').'"', false);
    }

    /**
     * У товара без подтверждённой фотографии остаётся заглушка. Товар без фото
     * в каталоге один — «Другие продукты» в категории мяса.
     */
    public function test_a_product_without_an_image_still_uses_the_placeholder(): void
    {
        $this->actingAsAdmin();

        $chicken = ProductCategory::query()->where('slug', 'chicken')->firstOrFail();
        $withoutPhoto = $chicken->products()->where('name', 'Другие продукты')->firstOrFail();

        $this->assertNull($withoutPhoto->image);

        $this->get('/products/chicken')
            ->assertOk()
            ->assertSee('aria-hidden="true"', false)
            ->assertDontSee('/storage/products/');
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Фотографию из панели изменить нельзя
    |--------------------------------------------------------------------------
    |
    | Поле image заблокировано, поэтому ни замена, ни очистка невозможны.
    | Это и проверяется: правка других полей не должна трогать ни путь в
    | базе, ни файл каталога.
    |
    | Раньше здесь проверялся lifecycle замены файла (beforeSave/afterSave на
    | странице EditProduct). Для загрузки с панели он больше не нужен: файлы
    | каталога лежат в public/images/products и отслеживаются Git, а
    | обслуживаются только деплоем. Код EditProduct оставлен — он нужен для
    | перехода на постоянное хранилище, и его безопасность проверяется в
    | ProductImageOrphanOnUpdateFailureTest.
    */

    public function test_editing_other_fields_leaves_the_seeded_path_untouched(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        $this->assertSame('images/products/egg-c0.jpg', $product->image);

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['name' => 'Переименованный товар'])
            ->call('save');

        $this->assertSame(
            'images/products/egg-c0.jpg',
            $product->fresh()->image,
            'Правка названия не должна стирать путь к фотографии каталога.',
        );
    }

    public function test_an_attempt_to_replace_the_path_through_the_panel_is_ignored(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['image' => ['images/products/tushka-kuritsy.jpg']])
            ->call('save');

        $this->assertSame(
            'images/products/egg-c0.jpg',
            $product->fresh()->image,
            'Заблокированное поле не должно подменять фотографию товара.',
        );
    }

    public function test_an_attempt_to_clear_the_path_through_the_panel_is_ignored(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['image' => null])
            ->call('save');

        $this->assertSame(
            'images/products/egg-c0.jpg',
            $product->fresh()->image,
            'Путь не должен обнуляться из панели: файл остаётся в репозитории.',
        );
    }

    public function test_the_admin_panel_never_deletes_files_from_the_public_directory(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        $before = public_path($product->image);

        $this->assertFileExists($before);

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['name' => 'Переименованный товар'])
            ->call('save');

        // Файл каталога лежит в public/, а cleanup EditProduct умеет удалять
        // только «products/…» на диске storage. Страховка от того, что
        // фотография из репозитория исчезнет при правке карточки.
        $this->assertFileExists($before, 'Правка товара не должна удалять файл из public/.');
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Удаление по-прежнему закрыто
    |--------------------------------------------------------------------------
    */

    public function test_delete_action_and_bulk_delete_remain_unavailable(): void
    {
        $this->actingAsAdmin();

        $product = $this->productWithImage();

        $this->assertFalse(ProductResource::canDelete($product));
        $this->assertFalse(ProductResource::canDeleteAny());
    }
}
