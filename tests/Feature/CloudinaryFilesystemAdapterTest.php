<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filesystem\CloudinaryFilesystemAdapter;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Cloudinary;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use League\Flysystem\Filesystem;
use League\Flysystem\UnableToWriteFile;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

/**
 * Интеграция реального Cloudinary-адаптера с Laravel FilesystemAdapter.
 *
 * ЗАЧЕМ ЭТОТ ТЕСТ ОТДЕЛЬНО ОТ ProductImageUploadTest
 *
 * Тесты загрузки товара подменяют диск через Storage::fake('cloudinary'):
 * они проверяют конвейер Filament → Livewire → путь в products.image, но
 * НЕ трогают сам App\Filesystem\CloudinaryFilesystemAdapter и не видят,
 * что Laravel делает со сбоем записи. Именно это расхождение и было
 * причиной «фото выбрано, товар сохранён, а в Cloudinary пусто и в логах
 * тихо»:
 *
 *  - диск cloudinary был настроен с 'throw' => false, поэтому
 *    FilesystemAdapter::put() перехватывал UnableToWriteFile, ничего не
 *    логировал (report => false) и возвращал false;
 *  - Livewire TemporaryUploadedFile::storeAs() (вендор) игнорирует этот
 *    возврат и всё равно отдаёт путь, поэтому Filament писал
 *    «products/cld-…» в products.image.
 *
 * Здесь адаптер настоящий, а HTTP-клиент Cloudinary подменён мок-объектом
 * (через приватное свойство), поэтому сеть и учётные данные не нужны и нет
 * Storage::fake('cloudinary').
 */
class CloudinaryFilesystemAdapterTest extends TestCase
{
    /**
     * Конфигурация диска, повторяющая config/filesystems.php.
     *
     * @return array<string, mixed>
     */
    private function diskConfig(bool $throw): array
    {
        return [
            'driver' => 'cloudinary',
            'cloud_name' => 'test-cloud',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'throw' => $throw,
            'report' => false,
        ];
    }

    /**
     * Настоящий Laravel FilesystemAdapter поверх настоящего адаптера.
     *
     * @param  array<string, mixed>  $config
     */
    private function storage(CloudinaryFilesystemAdapter $adapter, array $config): FilesystemAdapter
    {
        return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
    }

    /**
     * Подменяет приватный клиент Cloudinary, минуя сеть.
     */
    private function injectClient(CloudinaryFilesystemAdapter $adapter, Cloudinary $client): void
    {
        $property = new ReflectionProperty($adapter, 'cloudinary');
        $property->setAccessible(true);
        $property->setValue($adapter, $client);
    }

    /**
     * @return resource
     */
    private function contentStream(string $contents = "\x89PNG\r\n\x1a\n")
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    private function uploadApiReturning(array $response): UploadApi
    {
        $uploadApi = $this->createMock(UploadApi::class);
        $uploadApi->method('upload')->willReturn($response);

        return $uploadApi;
    }

    private function clientWith(UploadApi $uploadApi): Cloudinary
    {
        $client = $this->createMock(Cloudinary::class);
        $client->method('uploadApi')->willReturn($uploadApi);

        return $client;
    }

    /**
     * Успешная загрузка: адаптер доходит до Cloudinary API и put() отдаёт true.
     */
    public function test_a_successful_upload_reaches_the_cloudinary_api(): void
    {
        $config = $this->diskConfig(true);
        $adapter = new CloudinaryFilesystemAdapter($config);

        $uploadApi = $this->createMock(UploadApi::class);
        $uploadApi->expects($this->once())
            ->method('upload')
            ->with(
                $this->isString(),
                $this->callback(
                    static fn (array $options): bool => ($options['public_id'] ?? null) === 'products/cld-sample'
                        && ($options['overwrite'] ?? null) === true
                )
            )
            ->willReturn(['public_id' => 'products/cld-sample', 'format' => 'jpg']);

        $this->injectClient($adapter, $this->clientWith($uploadApi));

        $storage = $this->storage($adapter, $config);

        $this->assertTrue(
            $storage->put('products/cld-sample.jpg', $this->contentStream()),
            'Успешная запись должна вернуть true.'
        );
    }

    /**
     * Сбой Cloudinary при throw => true обязан выбрасываться наружу, а не
     * молча возвращать false: только так Filament не запишет путь в БД.
     */
    public function test_a_failed_upload_is_surfaced_when_throw_is_enabled(): void
    {
        $config = $this->diskConfig(true);
        $adapter = new CloudinaryFilesystemAdapter($config);

        $uploadApi = $this->createMock(UploadApi::class);
        $uploadApi->method('upload')->willThrowException(new RuntimeException('доступ к Cloudinary запрещён'));

        $this->injectClient($adapter, $this->clientWith($uploadApi));

        Log::spy();

        $storage = $this->storage($adapter, $config);

        try {
            $storage->put('products/cld-sample.jpg', $this->contentStream());
            $this->fail('Сбой записи должен был выбросить UnableToWriteFile.');
        } catch (UnableToWriteFile $exception) {
            $this->assertStringContainsString('доступ к Cloudinary запрещён', $exception->getMessage());
        }

        Log::shouldHaveReceived('error')->once();
    }

    /**
     * Та же ошибка при throw => false возвращает false — ровно то поведение,
     * которое делало сбой невидимым для Livewire::storeAs().
     */
    public function test_a_failed_upload_returns_false_when_throw_is_disabled(): void
    {
        $config = $this->diskConfig(false);
        $adapter = new CloudinaryFilesystemAdapter($config);

        $uploadApi = $this->createMock(UploadApi::class);
        $uploadApi->method('upload')->willThrowException(new RuntimeException('сбой'));

        $this->injectClient($adapter, $this->clientWith($uploadApi));

        Log::spy();

        $storage = $this->storage($adapter, $config);

        $this->assertFalse($storage->put('products/cld-sample.jpg', $this->contentStream()));
    }

    /**
     * Не задан CLOUDINARY_CLOUD_NAME: адаптер обязан сообщить об этом, а не
     * проглотить. Раньше эта ветка не оставляла в логах вообще ничего.
     */
    public function test_an_unconfigured_client_fails_with_a_clear_message(): void
    {
        $config = $this->diskConfig(true);
        $config['cloud_name'] = null;

        $adapter = new CloudinaryFilesystemAdapter($config);

        Log::spy();

        $storage = $this->storage($adapter, $config);

        try {
            $storage->put('products/cld-sample.jpg', $this->contentStream());
            $this->fail('Без cloud_name запись должна выбросить UnableToWriteFile.');
        } catch (UnableToWriteFile $exception) {
            $this->assertStringContainsString('CLOUDINARY_CLOUD_NAME', $exception->getMessage());
        }
    }

    /**
     * Реальная конфигурация диска обязана выбрасывать сбой записи.
     */
    public function test_the_cloudinary_disk_surfaces_write_failures(): void
    {
        $this->assertTrue(
            (bool) config('filesystems.disks.cloudinary.throw'),
            'Диск cloudinary должен иметь throw => true, иначе сбой загрузки снова станет невидимым.'
        );
    }
}
