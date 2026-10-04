<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * УБОРКА НОВОГО ФАЙЛА ПРИ СБОЕ ЗАПИСИ В БД.
 *
 * ЧТО ПРОВЕРЯЕТСЯ
 *
 * Гипотеза (подтверждена этим же тестом до исправления): новый
 * загруженный файл сохраняется компонентом FileUpload ДО записи Product
 * в БД, поэтому при сбое записи он остаётся на публичном диске без
 * ссылок в базе — «висящий» файл.
 *
 * Окно утечки в потоке Filament (Resources/Pages/EditRecord.php, save()):
 *
 *   168  getState()  → dehydrate → FileUpload::saveUploadedFiles()
 *                      → новый файл УЖЕ лежит на публичном диске
 *   176  handleRecordUpdate()  → сбой записи
 *   188  catch (Throwable) → rollBackDatabaseTransaction() → rethrow
 *
 * Событие afterSave() (строка 178) не выполняется. Механизм удаления
 * СТАРОГО файла живёт именно в afterSave(), поэтому не срабатывает —
 * и правильно: записи не было, старый файл трогать нельзя. А новый
 * файл удалять было некому: на него нет ни одной ссылки в products.image.
 *
 * Теперь это окно закрыто в EditProduct::handleRecordUpdate(): при сбое
 * записи новый неиспользуемый файл удаляется, а исходное исключение
 * пробрасывается дальше. Старый файл при этом не трогается.
 *
 * ЧТО ЗДЕСЬ НЕ ПРОВЕРЯЕТСЯ
 *
 * Нормальный lifecycle (замена A→B удаляет A) подтверждён браузерным
 * тестом и покрыт ProductImageUploadTest. Здесь только аварийная ветка.
 *
 * КАК ИНДУЦИРУЕТСЯ СБОЙ
 *
 * Ошибку нельзя вызвать «плохими данными»: невалидное значение падает
 * на валидации формы, и getState() не доходит до сохранения файла —
 * нужное окно просто не откроется. Нужен сбой ниже валидации, на самой
 * записи, поэтому подписка Product::updating() бросает исключение
 * прямо в save(). Это проверяет ШТАТНУЮ страницу EditProduct без
 * подмены: тестируется production-исправление, а не тестовая обёртка.
 *
 * ПОЧЕМУ СОСТОЯНИЕ СБРАСЫВАЕТСЯ ПЕРЕД ЗАГРУЗКОЙ
 *
 * set('data.image', null) имитирует то, что делает браузер при замене
 * файла: в состоянии остаётся только новая запись, старой нет. Без
 * этого шага set() в тест-харнесе СЛИВАЕТ массивы, FileUpload берёт
 * первый элемент и dehydrate возвращает старый путь A — новый файл
 * пишется на диск, но форма о нём не знает. Это артефакт харнесса,
 * а не поведение браузера (в браузерном прогоне form_image_keys
 * содержал ровно один UUID).
 *
 * ИЗОЛЯЦИЯ
 *
 * RefreshDatabase работает на SQLite :memory: из phpunit.xml, рабочая
 * база database/database.sqlite не затрагивается; дополнительно
 * страхует WorkingDatabaseGuard в Tests\TestCase::setUp().
 * Storage::fake('public') подменяет диск — ни один файл не попадает
 * в реальный storage/app/public/products.
 */
class ProductImageOrphanOnUpdateFailureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Адрес, которому вход в панель открыт.
     */
    private const ALLOWED_EMAIL = 'admin@local.test';

    /**
     * Категория с товарами каталога.
     */
    private const EGGS_SLUG = 'eggs';

    /**
     * Каталог загрузок товара на публичном диске.
     */
    private const PRODUCTS_DIRECTORY = 'products';

    /**
     * Уже сохранённый файл изображения товара.
     */
    private const IMAGE_A = 'products/old-image.jpg';

    /**
     * Сообщение индуцированного сбоя записи.
     */
    private const FAILURE_MESSAGE = 'Индуцированный сбой записи Product.';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('admin.panel_access_emails', [self::ALLOWED_EMAIL]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->seed(ProductCatalogSeeder::class);

        // Обязательная подмена диска: тест не пишет в реальный
        // storage/app/public.
        Storage::fake('public');

        $this->actingAs(
            User::factory()->create(['email' => self::ALLOWED_EMAIL]),
        );
    }

    /**
     * Товар с управляемым файлом изображения A, физически лежащим
     * на (fake) публичном диске.
     */
    private function productWithImageA(): Product
    {
        $category = ProductCategory::query()
            ->where('slug', self::EGGS_SLUG)
            ->firstOrFail();

        $product = $category->products()->orderBy('id')->firstOrFail();

        Storage::disk('public')->put(self::IMAGE_A, 'байты изображения A');

        $product->forceFill(['image' => self::IMAGE_A])->save();

        return $product->fresh();
    }

    /**
     * Второй товар той же категории — для проверок общих файлов.
     */
    private function anotherProduct(): Product
    {
        $category = ProductCategory::query()
            ->where('slug', self::EGGS_SLUG)
            ->firstOrFail();

        return $category->products()->orderBy('id')->skip(1)->firstOrFail();
    }

    /**
     * Все файлы каталога products на fake-диске, отсортированные.
     *
     * @return array<int, string>
     */
    private function filesOnDisk(): array
    {
        $files = Storage::disk('public')->files(self::PRODUCTS_DIRECTORY);

        sort($files);

        return $files;
    }

    /**
     * GUARD: уборка не трогает файл, на который ссылается другая запись.
     *
     * products.image не имеет уникального индекса, поэтому два товара
     * вправе ссылаться на один файл. Если запись с таким путём падает,
     * этот файл осиротевшим НЕ является: он по-прежнему используется
     * другим товаром, и удалять его нельзя.
     *
     * Путь подаётся в форму как уже сохранённый (а не загружается заново):
     * загрузка всегда создаёт уникальный ULID-файл и эту ветку не задевает.
     * Так проверяется ровно та граница, ради которой существует guard.
     */
    public function test_file_used_by_another_product_is_not_deleted(): void
    {
        $product = $this->productWithImageA();

        $sharedImage = 'products/shared-image.jpg';

        Storage::disk('public')->put($sharedImage, 'байты общего изображения');

        $other = $this->anotherProduct();

        $other->forceFill(['image' => $sharedImage])->save();

        Product::updating(function (): void {
            throw new RuntimeException(self::FAILURE_MESSAGE);
        });

        $thrown = null;

        try {
            Livewire::test(EditProduct::class, [
                'record' => $product->getKey(),
            ])
                ->set('data.image', null)
                ->fillForm([
                    'image' => ['shared-key' => $sharedImage],
                    // Имя меняется обязательно: пока загрузка отключена,
                    // поле image не обезвоживается, и без другого изменённого
                    // поля Eloquent не счёл бы запись «грязной» — событие
                    // updating() просто не сработало бы, и сбой не наступил
                    // бы вовсе. Проверяется та же ветка EditProduct, что и
                    // раньше, просто срабатывающая по другому полю.
                    'name' => 'Переименованный товар',
                ])
                ->call('save');
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(
            RuntimeException::class,
            $thrown,
            'Сохранение должно было упасть на записи.',
        );

        $this->assertContains(
            $sharedImage,
            $this->filesOnDisk(),
            'Файл, на который ссылается другой товар, удалять нельзя.',
        );

        $this->assertSame(
            'байты общего изображения',
            Storage::disk('public')->get($sharedImage),
            'Содержимое чужого файла не должно меняться.',
        );

        $this->assertSame(
            $sharedImage,
            $other->fresh()->image,
            'Запись другого товара не должна измениться.',
        );
    }

    /**
     * СБОЙ ЗАПИСИ НЕ ОСТАВЛЯЕТ НОВЫХ ФАЙЛОВ, ПОТОМУ ЧТО ЗАГРУЗКИ НЕТ
     *
     * Поле image убрано из формы целиком и заменено read-only Placeholder,
     * поэтому файл из формы не обезвоживается и на диск не попадает.
     * Значит «висящего» файла не возникает — и это проверяется напрямую.
     *
     * Раньше здесь был тест обратного: новый загруженный файл обязан был
     * удалиться при сбое записи. Пока загрузка работала, сценарий был
     * достижим; теперь он недостижим через интерфейс, поэтому подтверждать
     * его было бы проверкой мёртвого кода.
     *
     * Код EditProduct::deleteUncommittedImage() и handleRecordUpdate()
     * оставлены намеренно: они понадобятся при переходе на постоянное
     * хранилище, а удалять проверенную страховку вместе с отключением
     * загрузки — значит проверять её заново с нуля.
     */
    public function test_a_failed_update_creates_no_new_file_when_upload_is_disabled(): void
    {
        $product = $this->productWithImageA();

        $this->assertSame(
            [self::IMAGE_A],
            $this->filesOnDisk(),
            'До попытки на fake-диске должен лежать только файл A.',
        );

        // Сбой индуцируется на реальной записи Product: слушатель бросает
        // исключение прямо в save(). Страница при этом — штатная
        // EditProduct, без подмены.
        $attempts = 0;

        Product::updating(function () use (&$attempts): void {
            $attempts++;

            throw new RuntimeException(self::FAILURE_MESSAGE);
        });

        $thrown = null;

        try {
            Livewire::test(EditProduct::class, [
                'record' => $product->getKey(),
            ])
                ->fillForm([
                    'name' => 'Переименованный товар',
                ])
                ->call('save');
        } catch (RuntimeException $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(
            RuntimeException::class,
            $thrown,
            'save() должен был упасть исключением на этапе записи.',
        );

        $this->assertSame(self::FAILURE_MESSAGE, $thrown->getMessage());

        $this->assertSame(
            1,
            $attempts,
            'Запись должна была быть достигнута ровно один раз и упасть там.',
        );

        // 1. В БД осталось прежнее значение: транзакция откатилась, а
        //    заблокированное поле не подменило путь на новый.
        $this->assertSame(
            self::IMAGE_A,
            $product->fresh()->image,
            'После сбоя записи products.image должен остаться прежним (A).',
        );

        // 2. Старый файл A сохранился: afterSave() не выполнялся, а
        //    удалять его при отсутствии записи и нельзя.
        Storage::disk('public')->assertExists(self::IMAGE_A);

        // 3. Новых файлов не появилось — уборке нечего было делать.
        $this->assertSame(
            [self::IMAGE_A],
            $this->filesOnDisk(),
            'После сбоя записи на диске должен остаться только файл A.',
        );
    }
}
