<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Загрузка изображений товаров (Этап 9.4, перенос в Cloudinary).
 *
 * ЧТО ЗДЕСЬ ПРОВЕРЯЕТСЯ
 *
 * Поле image товара — FileUpload Filament 5.9 на облачном диске:
 *
 *  - disk = cloudinary, directory = products, maxSize = 5120 КБ, image();
 *  - в products.image попадает только относительный путь
 *    «products/cld-<ulid>.<ext>», а не абсолютный путь и не URL;
 *  - публичная страница строит URL через Cloudinary CDN для путей
 *    products/cld-* и откатывается на публичный диск, пока переменные
 *    окружения Cloudinary не заданы;
 *  - замена и очистка изображения удаляют СТАРЫЙ управляемый файл ровно
 *    одним механизмом — beforeSave()/afterSave() на странице EditProduct —
 *    и только после успешной записи;
 *  - удаление товара уносит его управляемый файл (событие deleting модели
 *    Product), но НИКОГДА не трогает файлы Git-макета public/images/products.
 *
 * О ПРОВЕРКЕ ЗАМЕНЫ И ОЧИСТКИ
 *
 * Замена/очистка проверяются на уровне пути: в состояние формы
 * подставляется путь уже сохранённого файла (как если бы upload завершился).
 * Сам конвейер upload→путь покрыт create-тестами ниже. Так сделано потому,
 * что в Livewire-тест-харнесе поток файла в НЕпустое одиночное поле
 * ДОБАВЛЯЕТСЯ к существующему состоянию, а не заменяет его, — это
 * расходится с поведением браузера и не относится к нашему lifecycle-механизму
 * очистки. Поэтому состояние сначала сбрасывается set('data.image', null).
 *
 * STORAGE::FAKE
 *
 * Публичный диск подменяется на Storage::fake() в setUp: файлы Git-макета и
 * старые «products/…» проверяются на подменённом диске, реальный
 * storage/app/public не затрагивается. Облачный диск подменяется точечно —
 * только в тестах, где идёт загрузка/удаление (fakeCloudinaryDisk()), иначе
 * Storage::fake() подменил бы CDN-адаптер и нельзя было бы проверить URL.
 * RefreshDatabase работает на SQLite :memory: из phpunit.xml, поэтому рабочая
 * база database/database.sqlite тоже не затрагивается.
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

    private const CHICKEN_SLUG = 'chicken';

    /**
     * Имя и адрес нового товара для проверок создания.
     */
    private const NEW_NAME = 'Яйцо деревянное';

    private const NEW_SLUG = 'yaitso-derevyannoe';

    /**
     * Каталог загрузок товара.
     */
    private const PRODUCTS_DIRECTORY = 'products';

    /**
     * Облачное имя файла начинается с этого префикса (см. Product).
     */
    private const CLOUDINARY_FILE_PREFIX = 'products/cld-';

    /**
     * Путь к фотографии Git-макета: такие файлы панель не удаляет никогда.
     */
    private const GIT_MANAGED_PATH = 'images/products/egg-c0.jpg';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('admin.panel_access_emails', [self::ALLOWED_EMAIL]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->seed(ProductCatalogSeeder::class);

        // Обязательная подмена диска: тесты не должны писать в реальный
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
     * Подмена облачного диска: загрузка не уходит в сеть.
     */
    private function fakeCloudinaryDisk(): void
    {
        Storage::fake('cloudinary');
    }

    /**
     * Товар с существующим управляемым файлом изображения на диске.
     */
    private function productWithImage(
        string $relativePath = 'products/old-image.jpg',
        string $disk = 'public',
    ): Product {
        $product = $this->productIn($this->eggs());

        Storage::disk($disk)->put($relativePath, 'старые байты изображения');

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

    /*
    |--------------------------------------------------------------------------
    | 1. Конфигурация поля
    |--------------------------------------------------------------------------
    */

    public function test_the_form_has_a_cloudinary_file_upload_component(): void
    {
        $this->actingAsAdmin();

        $form = Livewire::test(CreateProduct::class)
            ->instance()
            ->getSchema('form');

        $components = $form->getComponents(withHidden: true);

        $fieldNames = array_map(
            static fn ($component): ?string => $component->getName(),
            $components,
        );

        $this->assertContains('image', $fieldNames, 'Поле image в форме должно быть.');
        $this->assertNotContains('image_path', $fieldNames, 'Показ пути заменён загрузкой.');

        $upload = $form->getComponent('image');

        $this->assertInstanceOf(FileUpload::class, $upload);
        $this->assertSame('cloudinary', $upload->getDiskName());
        $this->assertSame(self::PRODUCTS_DIRECTORY, $upload->getDirectory());
        $this->assertSame(5120, $upload->getMaxSize());
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Создание товара
    |--------------------------------------------------------------------------
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
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(Product::query()->where('slug', self::NEW_SLUG)->firstOrFail()->image);
    }

    public function test_creating_with_an_image_stores_a_cloudinary_path(): void
    {
        $this->actingAsAdmin();
        $this->fakeCloudinaryDisk();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'product_category_id' => $this->eggs()->getKey(),
                'name' => self::NEW_NAME,
                'slug' => self::NEW_SLUG,
                'image' => $this->fakeJpeg(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('slug', self::NEW_SLUG)->firstOrFail();

        $this->assertNotNull($product->image);
        $this->assertStringStartsWith(self::CLOUDINARY_FILE_PREFIX, $product->image);
        $this->assertTrue(
            Storage::disk('cloudinary')->exists($product->image),
            'Файл должен переехать на диск cloudinary.',
        );
    }

    public function test_png_and_webp_images_are_accepted(): void
    {
        $this->actingAsAdmin();
        $this->fakeCloudinaryDisk();

        $uploads = [
            'yaitso-png' => $this->fakePng(),
            'yaitso-webp' => $this->fakeWebp(),
        ];

        foreach ($uploads as $slug => $file) {
            Livewire::test(CreateProduct::class)
                ->fillForm([
                    'product_category_id' => $this->eggs()->getKey(),
                    'name' => 'Товар '.$slug,
                    'slug' => $slug,
                    'image' => $file,
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $product = Product::query()->where('slug', $slug)->firstOrFail();

            $this->assertStringStartsWith(self::CLOUDINARY_FILE_PREFIX, $product->image);
            $this->assertTrue(Storage::disk('cloudinary')->exists($product->image));
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Путь в базе
    |--------------------------------------------------------------------------
    */

    public function test_the_database_never_stores_absolute_paths_or_public_urls(): void
    {
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

    public function test_the_public_page_renders_a_git_managed_photo_as_an_asset_url(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        // Seeder уже положил в image путь внутри public/.
        $this->assertSame(self::GIT_MANAGED_PATH, $product->image);

        $this->get('/products/'.self::EGGS_SLUG)
            ->assertOk()
            ->assertSee('src="'.asset(self::GIT_MANAGED_PATH).'"', false);
    }

    public function test_a_cloudinary_photo_is_rendered_through_the_cdn(): void
    {
        $this->actingAsAdmin();

        config(['filesystems.disks.cloudinary.cloud_name' => 'test-cloud']);
        Storage::forgetDisk('cloudinary');

        $path = 'products/cld-render-test.jpg';

        $product = $this->productIn($this->eggs());
        $product->forceFill(['image' => $path])->save();

        $expected = 'https://res.cloudinary.com/test-cloud/image/upload/'.$path;

        $this->get('/products/'.self::EGGS_SLUG)
            ->assertOk()
            ->assertSee('src="'.$expected.'"', false);
    }

    public function test_a_cloudinary_photo_falls_back_to_public_disk_without_configuration(): void
    {
        $this->actingAsAdmin();

        config([
            'filesystems.disks.cloudinary.cloud_name' => null,
            'filesystems.disks.cloudinary.url' => null,
        ]);
        Storage::forgetDisk('cloudinary');

        $path = 'products/cld-render-test.jpg';

        $product = $this->productIn($this->eggs());
        $product->forceFill(['image' => $path])->save();

        $expected = Storage::disk('public')->url($path);

        $this->get('/products/'.self::EGGS_SLUG)
            ->assertOk()
            ->assertSee('src="'.$expected.'"', false);
    }

    public function test_a_product_without_an_image_still_uses_the_placeholder(): void
    {
        $this->actingAsAdmin();

        $chicken = ProductCategory::query()->where('slug', self::CHICKEN_SLUG)->firstOrFail();
        $withoutPhoto = $chicken->products()->where('name', 'Другие продукты')->firstOrFail();

        $this->assertNull($withoutPhoto->image);

        $this->get('/products/'.self::CHICKEN_SLUG)
            ->assertOk()
            ->assertSee('aria-hidden="true"', false)
            ->assertDontSee('/storage/products/');
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Жизненный цикл файла: замена и очистка
    |--------------------------------------------------------------------------
    */

    public function test_replacing_an_image_deletes_the_old_managed_file(): void
    {
        $this->actingAsAdmin();

        $product = $this->productWithImage('products/old-image.jpg');

        Storage::disk('public')->assertExists('products/old-image.jpg');

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->set('data.image', null)
            ->fillForm([
                'image' => ['new-key' => 'products/cld-new-image.jpg'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('products/cld-new-image.jpg', $product->fresh()->image);
        Storage::disk('public')->assertMissing('products/old-image.jpg');
    }

    public function test_clearing_an_image_deletes_the_old_managed_file_and_sets_null(): void
    {
        $this->actingAsAdmin();

        $product = $this->productWithImage('products/old-image.jpg');

        Storage::disk('public')->assertExists('products/old-image.jpg');

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->set('data.image', null)
            ->fillForm(['image' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($product->fresh()->image);
        Storage::disk('public')->assertMissing('products/old-image.jpg');
    }

    public function test_replacing_an_image_does_not_touch_a_git_managed_file(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        $this->assertSame(self::GIT_MANAGED_PATH, $product->image);

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->set('data.image', null)
            ->fillForm([
                'image' => ['new-key' => 'products/cld-new-image.jpg'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // Файл каталога лежит в public/images/products и отслеживается Git:
        // он не управляемый, поэтому уборкой не трогается.
        $this->assertFileExists(public_path(self::GIT_MANAGED_PATH));
    }

    public function test_editing_other_fields_leaves_the_seeded_path_untouched(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        Livewire::test(EditProduct::class, ['record' => $product->getKey()])
            ->fillForm(['name' => 'Переименованный товар'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            self::GIT_MANAGED_PATH,
            $product->fresh()->image,
            'Правка названия не должна стирать путь к фотографии каталога.',
        );
        $this->assertFileExists(public_path(self::GIT_MANAGED_PATH));
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Удаление товара уносит управляемый файл
    |--------------------------------------------------------------------------
    */

    public function test_deleting_a_product_removes_its_cloudinary_file(): void
    {
        $this->actingAsAdmin();
        $this->fakeCloudinaryDisk();

        $product = $this->productWithImage('products/cld-delete-me.jpg', 'cloudinary');

        Storage::disk('cloudinary')->assertExists('products/cld-delete-me.jpg');

        $product->delete();

        Storage::disk('cloudinary')->assertMissing('products/cld-delete-me.jpg');
        $this->assertDatabaseMissing('products', ['id' => $product->getKey()]);
    }

    public function test_deleting_a_product_removes_its_legacy_managed_file(): void
    {
        $this->actingAsAdmin();

        $product = $this->productWithImage('products/legacy-delete-me.jpg', 'public');

        Storage::disk('public')->assertExists('products/legacy-delete-me.jpg');

        $product->delete();

        Storage::disk('public')->assertMissing('products/legacy-delete-me.jpg');
    }

    public function test_deleting_a_product_never_removes_a_git_managed_file(): void
    {
        $this->actingAsAdmin();

        $product = $this->productIn($this->eggs());

        $product->forceFill(['image' => self::GIT_MANAGED_PATH])->save();

        $this->assertFileExists(public_path(self::GIT_MANAGED_PATH));

        $product->fresh()->delete();

        $this->assertFileExists(
            public_path(self::GIT_MANAGED_PATH),
            'Удаление товара не должно трогать файл Git-макета.',
        );
    }
}
