<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Тесты адреса фотографии товара: Product::imageUrl().
 *
 * Отдельный юнит-файл, потому что здесь проверяются все ветви выбора типа
 * пути, а не одна из них. Страница покрывает только два реальных
 * сценария из базы; остальные случаи — пустое значение, мусорные строки и
 * защита от выхода за пределы каталога — проверяются здесь, без базы и без
 * HTTP-запроса.
 *
 * Наследует Tests\TestCase, потому что использует фасады Storage и asset(),
 * которым нужен поднятый контейнер приложения.
 */
class ProductImageUrlTest extends TestCase
{
    /**
     * Переходный период: в базе сосуществуют два формата пути, и разбирать
     * вид значения должен сам метод, а не шаблон.
     */
    public function test_a_managed_legacy_path_goes_through_the_public_disk(): void
    {
        Storage::fake('public');

        $url = (new Product(['image' => 'products/01M3T1AMSMS67QZFV35HBX9ZPW.png']))->imageUrl();

        $this->assertSame(
            Storage::disk('public')->url('products/01M3T1AMSMS67QZFV35HBX9ZPW.png'),
            $url,
        );
    }

    public function test_a_new_path_inside_public_goes_through_asset(): void
    {
        $url = (new Product(['image' => 'images/products/egg-c0.jpg']))->imageUrl();

        $this->assertSame(asset('images/products/egg-c0.jpg'), $url);
        $this->assertStringNotContainsString('/storage/', $url);
    }

    /**
     * Ссылка должна идти от корня сайта: на /products/eggs относительный
     * путь превратился бы в /products/images/… и дал 404.
     *
     * Хост намеренно не сравнивается: APP_URL не зафиксирован в
     * phpunit.xml и приходит из окружения. Проверяется форма адреса, а не
     * значение домена.
     */
    public function test_a_new_path_is_rooted_at_the_site_root(): void
    {
        $url = (new Product(['image' => 'images/products/tushka-kuritsy.jpg']))->imageUrl();

        $this->assertSame(url('/images/products/tushka-kuritsy.jpg'), $url);
        $this->assertStringNotContainsString('/products/images/', (string) $url);
        $this->assertStringStartsWith(
            rtrim((string) config('app.url'), '/').'/images/products/',
            (string) $url,
            'Путь обязан начинаться с корня сайта, а не с текущего адреса страницы.',
        );
    }

    /**
     * Товар без фотографии («Другие продукты») не должен получать ссылку
     * вида «/»: браузер пошёл бы загружать страницу как картинку. Компонент
     * карточки показывает заглушку на null.
     */
    public function test_no_photo_yields_null(): void
    {
        $this->assertNull((new Product(['image' => null]))->imageUrl());
    }

    public function test_blank_values_yield_null(): void
    {
        $this->assertNull((new Product(['image' => '']))->imageUrl());
        $this->assertNull((new Product(['image' => '   ']))->imageUrl());
    }

    public function test_padded_paths_are_trimmed_before_the_type_is_detected(): void
    {
        Storage::fake('public');

        $url = (new Product(['image' => '  images/products/egg-c1.jpg  ']))->imageUrl();

        $this->assertSame(asset('images/products/egg-c1.jpg'), $url);
    }

    /**
     * То, что не является управляемым путём products/…, не должно молча
     * превращаться в ссылку публичного диска: так можно было бы отдать
     * файл из произвольного каталога хранилища.
     */
    public function test_a_path_outside_products_is_not_treated_as_managed(): void
    {
        Storage::fake('public');

        $url = (new Product(['image' => 'documents/secret.pdf']))->imageUrl();

        $this->assertSame(asset('documents/secret.pdf'), $url);
    }

    /**
     * Обход каталога отсекается: строка с products/ внутри и «..» не должна
     * уходить в ветку публичного диска.
     */
    public function test_a_traversal_inside_a_managed_prefix_is_rejected(): void
    {
        Storage::fake('public');

        $url = (new Product(['image' => 'products/../app/private.key']))->imageUrl();

        $this->assertStringNotContainsString('/storage/', (string) $url);
        $this->assertSame(asset('products/../app/private.key'), $url);
    }

    /**
     * Разделитель каталогов Windows в значении базы — признак либо
     * повреждённой записи, либо попытки выйти из каталога хранилища.
     * Ветку публичного диска он запускать не должен.
     */
    public function test_a_windows_separator_does_not_trigger_the_managed_branch(): void
    {
        Storage::fake('public');

        $url = (new Product(['image' => 'products\\..\\app\\private.key']))->imageUrl();

        $this->assertStringNotContainsString('/storage/', (string) $url);
    }

    /**
     * Путь products/cld-… — загрузка из Cloudinary. Когда облако настроено,
     * ссылку отдаёт сам диск: у него url строится из cloud_name, а не из
     * локального /storage. Проверяется точная форма адреса CDN.
     */
    public function test_a_cloudinary_path_is_rendered_through_the_cdn_when_configured(): void
    {
        Storage::forgetDisk('cloudinary');

        config([
            'filesystems.disks.cloudinary.cloud_name' => 'test-cloud',
            'filesystems.disks.cloudinary.url' => null,
        ]);

        $path = 'products/cld-01M3T1AMSMS67QZFV35HBX9ZPW.jpg';

        $this->assertTrue(Product::isCloudinaryImagePath($path));

        $this->assertSame(
            'https://res.cloudinary.com/test-cloud/image/upload/'.$path,
            (new Product(['image' => $path]))->imageUrl(),
        );
    }

    /**
     * Пока Cloudinary не настроен, облачных файлов там быть не может, и
     * ссылка деградирует до публичного диска — страница не падает и не
     * показывает битый адрес CDN.
     */
    public function test_a_cloudinary_path_falls_back_to_the_public_disk_when_not_configured(): void
    {
        Storage::forgetDisk('cloudinary');
        Storage::fake('public');

        config([
            'filesystems.disks.cloudinary.cloud_name' => null,
            'filesystems.disks.cloudinary.url' => null,
        ]);

        $path = 'products/cld-01M3T1AMSMS67QZFV35HBX9ZPW.jpg';
        $url = (new Product(['image' => $path]))->imageUrl();

        $this->assertSame(Storage::disk('public')->url($path), $url);
        $this->assertStringNotContainsString('res.cloudinary.com', (string) $url);
    }

    /**
     * Обычная запись старого локального пути products/<ulid> не должна
     * уходить в Cloudinary даже при настроенном облаке: префикс cld-
     * единственный признак облачной загрузки.
     */
    public function test_a_non_cloudinary_managed_path_stays_on_the_public_disk(): void
    {
        Storage::forgetDisk('cloudinary');
        Storage::fake('public');

        config([
            'filesystems.disks.cloudinary.cloud_name' => 'test-cloud',
            'filesystems.disks.cloudinary.url' => null,
        ]);

        $path = 'products/01M3T1AMSMS67QZFV35HBX9ZPW.png';

        $this->assertFalse(Product::isCloudinaryImagePath($path));

        $url = (new Product(['image' => $path]))->imageUrl();

        $this->assertSame(Storage::disk('public')->url($path), $url);
        $this->assertStringNotContainsString('res.cloudinary.com', (string) $url);
    }

    /**
     * Метод не должен обращаться к базе: он вызывается при рендере списка
     * для каждого товара, и лишний запрос на строку там недопустим.
     */
    public function test_the_method_does_not_query_the_database(): void
    {
        $product = new Product(['image' => 'images/products/egg-c2.jpg']);

        $url = $product->imageUrl();

        $this->assertSame(asset('images/products/egg-c2.jpg'), $url);
        $this->assertFalse($product->exists, 'Модель осталась несохранённой: сохранений не было.');
    }
}
