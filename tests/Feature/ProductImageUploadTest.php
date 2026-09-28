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

    public function test_the_image_field_has_public_disk_products_directory_and_mime_limits(): void
    {
        $this->actingAsAdmin();

        $form = Livewire::test(EditProduct::class, ['record' => $this->productIn($this->eggs())->getKey()])
            ->instance()
            ->getSchema('form');

        $field = collect($form->getComponents(withHidden: true))
            ->first(fn ($component): bool => $component->getName() === 'image');

        $this->assertNotNull($field, 'В форме должен быть компонент image.');
        $this->assertInstanceOf(\Filament\Forms\Components\FileUpload::class, $field);

        $this->assertSame('public', $field->getDiskName(), 'Файлы обязаны лежать на публичном диске.');
        $this->assertSame(self::PRODUCTS_DIRECTORY, $field->getDirectory());
        $this->assertSame('public', $field->getVisibility());
        $this->assertSame(4096, $field->getMaxSize(), 'Предпочтительный лимит — 4096 КБ (4 МБ).');
        $this->assertSame('200', $field->getImagePreviewHeight());

        // Проверка типов на СЕРВЕРЕ, а не только через accept браузера:
        // acceptedFileTypes() превращается в правило mimetypes.
        $this->assertSame(
            ['image/jpeg', 'image/png', 'image/webp'],
            $field->getAcceptedFileTypes(),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Создание товара с изображением
    |--------------------------------------------------------------------------
    */

    public function test_a_jpeg_upload_through_create_stores_a_relative_products_path(): void
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

        $this->assertMatchesRegularExpression(
            '#^products/[A-Za-z0-9_-]+\.jpg$#',
            $product->image,
            'В базе должен лежать только относительный путь внутри products/.',
        );

        Storage::disk('public')->assertExists($product->image);
    }

    public function test_a_png_upload_through_create_is_stored(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => $this->eggs()->getKey(),
                'name' => self::NEW_NAME,
                'slug' => self::NEW_SLUG,
                'image' => $this->fakePng(),
            ])
            ->call('create');

        $image = Product::query()->where('slug', self::NEW_SLUG)->firstOrFail()->image;

        $this->assertIsString($image);
        $this->assertMatchesRegularExpression('#^products/[A-Za-z0-9_-]+\.png$#', $image);
        Storage::disk('public')->assertExists($image);
    }

    public function test_a_webp_upload_through_create_is_stored(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => $this->eggs()->getKey(),
                'name' => self::NEW_NAME,
                'slug' => self::NEW_SLUG,
                'image' => $this->fakeWebp(),
            ])
            ->call('create');

        $image = Product::query()->where('slug', self::NEW_SLUG)->firstOrFail()->image;

        $this->assertIsString($image);
        $this->assertMatchesRegularExpression('#^products/[A-Za-z0-9_-]+\.webp$#', $image);
        Storage::disk('public')->assertExists($image);
    }

    public function test_the_database_never_stores_absolute_paths_or_public_urls(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => $this->eggs()->getKey(),
                'name' => self::NEW_NAME,
                'slug' => self::NEW_SLUG,
                'image' => $this->fakeJpeg('fancy client photo.JPG'),
            ])
            ->call('create');

        $image = Product::query()->where('slug', self::NEW_SLUG)->firstOrFail()->image;

        // Расширение приходит от клиента, поэтому даже «fancy client photo.JPG»
        // обязано превратиться в «products/<ULID>.JPG» — но остаться РОДИТЕЛЬСКИМ
        // путём, без пробелов и следов исходного имени.
        $this->assertMatchesRegularExpression(
            '#^products/[A-Za-z0-9_-]+\.(jpe?g|png|webp)$#i',
            $image,
        );

        // Ни абсолютного filesystem-пути, ни URL, ни префикса каталога диска.
        $this->assertFalse(str_contains($image, '\\'), 'В БД не должно быть Windows-путей.');
        $this->assertFalse(str_starts_with($image, '/'), 'В БД не должно быть абсолютных путей.');
        $this->assertFalse(str_contains($image, '://'), 'В БД не должно быть URL.');
        $this->assertFalse(str_contains($image, '/storage/'), 'В БД не должно быть адреса публичного хранилища.');
        $this->assertFalse(str_contains($image, 'app/public'), 'В БД не должно быть пути storage/app/public.');
    }

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

    /*
    |--------------------------------------------------------------------------
    | 3. Отклонение недопустимых файлов
    |--------------------------------------------------------------------------
    */

    public function test_a_plain_text_file_is_rejected_with_a_clear_russian_message(): void
    {
        $this->actingAsAdmin();

        $component = Livewire::test(CreateProduct::class);
        $component
            ->fillForm([
                'product_category_id' => $this->eggs()->getKey(),
                'name' => self::NEW_NAME,
                'slug' => self::NEW_SLUG,
                'image' => $this->fakeText(),
            ])
            ->call('create');

        $this->assertSame(
            0,
            Product::query()->where('slug', self::NEW_SLUG)->count(),
            'Создание с недопустимым файлом не должно сохранить товар.',
        );

        $messages = $this->allErrorMessages($component);

        $this->assertNotEmpty($messages, 'Недопустимый файл обязан дать ошибку формы.');

        $this->assertStringContainsString(
            'из типов',
            implode(' ', $messages),
            'Сообщение должно объяснять разрешённые типы (mimetypes).',
        );

        $this->assertStringContainsString(
            'image/jpeg, image/png, image/webp',
            implode(' ', $messages),
            'В сообщении должны быть перечислены разрешённые форматы.',
        );

        foreach ($messages as $message) {
            $this->assertFalse(str_starts_with($message, 'validation.'), 'Сообщение не должно быть сырым ключом: '.$message);
        }
    }

    public function test_an_oversized_file_is_rejected_with_a_clear_russian_message(): void
    {
        $this->actingAsAdmin();

        $component = Livewire::test(CreateProduct::class);
        $component
            ->fillForm([
                'product_category_id' => $this->eggs()->getKey(),
                'name' => self::NEW_NAME,
                'slug' => self::NEW_SLUG,
                'image' => $this->oversizedJpeg(),
            ])
            ->call('create');

        $this->assertSame(
            0,
            Product::query()->where('slug', self::NEW_SLUG)->count(),
            'Создание со слишком большим файлом не должно сохранить товар.',
        );

        $messages = $this->allErrorMessages($component);

        $this->assertNotEmpty($messages, 'Слишком большой файл обязан дать ошибку формы.');

        $this->assertStringContainsString(
            '4096 КБ',
            implode(' ', $messages),
            'Сообщение должно называть лимит 4096 КБ (max.file из lang/ru/validation.php).',
        );

        foreach ($messages as $message) {
            $this->assertFalse(str_starts_with($message, 'validation.'), 'Сообщение не должно быть сырым ключом: '.$message);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Публичное отображение
    |--------------------------------------------------------------------------
    */

    public function test_the_public_page_renders_the_public_disk_url_of_an_uploaded_image(): void
    {
        $this->actingAsAdmin();

        $product = $this->productWithImage('products/tushka.jpg');

        $this->get('/products/'.self::EGGS_SLUG)
            ->assertOk()
            ->assertSee('src="'.Storage::disk('public')->url($product->image).'"', false);
    }

    public function test_a_product_without_an_image_still_uses_the_placeholder(): void
    {
        $this->actingAsAdmin();

        $this->assertNull($this->productIn($this->eggs())->image);

        $this->get('/products/'.self::EGGS_SLUG)
            ->assertOk()
            ->assertSee('aria-hidden="true"', false)
            ->assertDontSee('/storage/products/');
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Замена и очистка на странице редактирования
    |--------------------------------------------------------------------------
    */

    public function test_editing_other_fields_without_a_new_image_keeps_the_file(): void
    {
        $this->actingAsAdmin();

        $product = $this->productWithImage();

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['name' => 'Переименованный товар'])
            ->call('save');

        $this->assertSame(
            'products/old-image.jpg',
            $product->fresh()->image,
            'Правка названия не должна менять изображение.',
        );

        Storage::disk('public')->assertExists('products/old-image.jpg');
    }

    public function test_replacing_the_image_updates_the_database_and_deletes_the_old_file(): void
    {
        $this->actingAsAdmin();

        $product = $this->productWithImage();

        /*
         * Жизненный цикл ЗАМЕНЫ проверяется на уровне пути: в состояние формы
         * подставляется путь уже сохранённого нового файла «products/new.jpg»,
         * как если бы upload завершился (этапы upload→путь покрыты
         * create-тестами). Это детерминировано: в Livewire-тест-харнесе поток
         * файла в НЕпустое одиночное поле ДОБАВЛЯЕТСЯ к существующему состоянию
         * вместо замены, что расходится с поведением браузера и не входит
         * в предмет нашего механизма очистки.
         *
         * Состояние передаётся массивом: валидация FileUpload ожидает массив
         * (правила «image.*»), а не голую строку.
         */
        Storage::disk('public')->put('products/new-image.jpg', 'новые байты');

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['image' => ['products/new-image.jpg']])
            ->call('save');

        $this->assertSame(
            'products/new-image.jpg',
            $product->fresh()->image,
            'БД обязана указывать на новый файл.',
        );

        Storage::disk('public')->assertExists('products/new-image.jpg');
        Storage::disk('public')->assertMissing('products/old-image.jpg');
    }

    public function test_a_validation_failure_during_edit_does_not_delete_the_old_file(): void
    {
        $this->actingAsAdmin();

        $product = $this->productWithImage();

        $component = Livewire::test(EditProduct::class, ['record' => $product->getKey()]);
        $component
            ->fillForm([
                'image' => ['products/target.jpg'],
                'slug' => '', // required нарушен сознательно.
            ])
            ->call('save')
            ->assertHasFormErrors(['slug' => 'required']);

        $this->assertSame(
            'products/old-image.jpg',
            $product->fresh()->image,
            'При ошибке validation запись не должна была измениться.',
        );

        Storage::disk('public')->assertExists('products/old-image.jpg');
    }

    public function test_clearing_the_image_sets_null_and_deletes_the_file(): void
    {
        $this->actingAsAdmin();

        $product = $this->productWithImage();

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['image' => null])
            ->call('save');

        $this->assertNull($product->fresh()->image, 'Очистка должна обнулить изображение.');
        Storage::disk('public')->assertMissing('products/old-image.jpg');
    }

    public function test_the_public_page_shows_the_placeholder_after_clearing_the_image(): void
    {
        $this->actingAsAdmin();

        $product = $this->productWithImage();

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['image' => null])
            ->call('save');

        $this->get('/products/'.self::EGGS_SLUG)
            ->assertOk()
            ->assertSee('aria-hidden="true"', false)
            ->assertDontSee('/storage/products/');
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