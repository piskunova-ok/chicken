<?php

declare(strict_types=1);

namespace App\Filesystem;

use Cloudinary\Api\Exception\NotFound;
use Cloudinary\Cloudinary;
use Cloudinary\Configuration\ApiConfig;
use Cloudinary\Configuration\Configuration;
use Illuminate\Support\Facades\Log;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\InvalidVisibilityProvided;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToListContents;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use RuntimeException;

/**
 * Адаптер Flysystem для хранилища Cloudinary.
 *
 * Диск существует всегда, даже когда переменные CLOUDINARY_* ещё не заданы:
 * конфигурация проверяется лениво, в момент первой операции. Пути выглядят
 * как products/cld-<ulid>.jpg — в Cloudinary их публичный id это
 * products/cld-<ulid> (без расширения).
 */
final class CloudinaryFilesystemAdapter implements FilesystemAdapter
{
    private const DEFAULT_BASE_URL = 'https://res.cloudinary.com';

    private array $config;

    private ?Cloudinary $cloudinary = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Лениво создаёт клиент Cloudinary.
     *
     * Cloudinary::__construct() валидирует конфигурацию и бросает исключение,
     * если не задан cloud_name. Поэтому клиент строится только при первом
     * обращении, и диск спокойно резолвится до настройки окружения.
     *
     * Конфигурация собирается ЯВНО из трёх стандартных учётных данных
     * (cloud_name + api_key + api_secret) и передаётся в SDK готовым объектом
     * Configuration. Так URL Upload API не зависит от того, что ещё лежит в
     * окружении.
     *
     * Зачем это нужно:
     *
     *  - Cloudinary\Configuration\Configuration::import() читает переменную
     *    окружения CLOUDINARY_URL, если конфиг ей НЕ передан. В строке
     *    CLOUDINARY_URL допускаются query-параметры вида ?upload_prefix=…,
     *    и «промах» upload_prefix уводит upload на маркетинговый сайт
     *    cloudinary.com или на res.cloudinary.com. Оба на неизвестный путь
     *    отдают HTML «Cloudinary - Page not found», а SDK из-за
     *    не-JSON тела рапортует невнятную ошибку
     *    «Error parsing server response (404)». Именно это и выглядело как
     *    «файл не загружается, а в логе непонятный HTML».
     *
     *  - Поэтому upload_prefix ЖЁСТКО фиксируется официальной константой
     *    самого SDK (ApiConfig::DEFAULT_UPLOAD_PREFIX), а не берётся из
     *    окружения. Это не кастом: это ровно тот endpoint, который SDK
     *    использует по умолчанию — https://api.cloudinary.com.
     *
     * Итоговый адрес загрузки: https://api.cloudinary.com/v1_1/<cloud>/image/upload
     */
    private function client(): Cloudinary
    {
        if ($this->cloudinary instanceof Cloudinary) {
            return $this->cloudinary;
        }

        $cloudName = trim((string) ($this->config['cloud_name'] ?? ''));

        if ($cloudName === '') {
            throw new RuntimeException(
                'Cloudinary не настроен: отсутствует переменная окружения CLOUDINARY_CLOUD_NAME.'
            );
        }

        /*
         * Имя облака обязано быть «голым» идентификатором. Любой лишний символ
         * (точка, слэш, пробел, https://…) не меняет хост — upload_prefix
         * закреплён ниже — но ломает ПУТЬ:
         *
         *   cloud_name = "my-cloud"        -> /v1_1/my-cloud/image/upload   (200/400 JSON)
         *   cloud_name = "my-cloud.com"    -> /v1_1/my-cloud.com/image/upload   (404 HTML)
         *   cloud_name = "res.cloudinary.com/my-cloud" -> 404 HTML «Page not found»
         *   cloud_name = "my-cloud/image"  -> /v1_1/my-cloud/image/image/upload (404 HTML)
         *
         * Именно 404 с HTML-страницей «Cloudinary - Page not found» и видел
         * SDK как «Error parsing server response (404)». Поэтому неверное имя
         * облака отсекаем сразу, с понятным сообщением, а не глухой ошибкой.
         */
        if (preg_match('/^[A-Za-z0-9_-]+$/', $cloudName) !== 1) {
            throw new RuntimeException(sprintf(
                'CLOUDINARY_CLOUD_NAME задан неверно: %s. Ожидается только имя облака '
                .'(буквы, цифры, "-", "_"), например "my-cloud", — без URL, слэшей, '
                .'пробелов и точек. Иначе Upload API уходит на неверный путь и Cloudinary '
                .'отвечает HTML «Page not found» (404).',
                $cloudName
            ));
        }

        return $this->cloudinary = new Cloudinary($this->configuration());
    }

    /**
     * Явная конфигурация SDK: три стандартных учётных данных плюс официальный
     * хост Upload/Admin API из константы самого SDK. Благодаря этому URL не
     * зависит от переменной окружения CLOUDINARY_URL и её параметра
     * upload_prefix.
     */
    private function configuration(): Configuration
    {
        return new Configuration([
            'cloud' => [
                'cloud_name' => trim((string) ($this->config['cloud_name'] ?? '')),
                'api_key' => $this->config['api_key'] ?? null,
                'api_secret' => $this->config['api_secret'] ?? null,
            ],
            'api' => [
                'upload_prefix' => ApiConfig::DEFAULT_UPLOAD_PREFIX,
            ],
        ]);
    }

    public function fileExists(string $path): bool
    {
        try {
            $this->client()->adminApi()->asset($this->publicId($path));
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    public function directoryExists(string $path): bool
    {
        try {
            $response = $this->client()->adminApi()->assets([
                'prefix' => rtrim($path, '/').'/',
                'max_results' => 1,
            ]);
        } catch (\Throwable) {
            return false;
        }

        return ! empty($response['resources']);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->uploadContents($path, $contents);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        if (! is_resource($contents)) {
            throw UnableToWriteFile::atLocation($path, 'Не передан поток данных.');
        }

        $data = stream_get_contents($contents);

        if ($data === false) {
            throw UnableToWriteFile::atLocation($path, 'Не удалось прочитать поток данных.');
        }

        $this->uploadContents($path, $data);
    }

    public function read(string $path): string
    {
        $contents = @file_get_contents($this->getUrl($path));

        if ($contents === false) {
            throw UnableToReadFile::fromLocation($path, 'Не удалось скачать файл.');
        }

        return $contents;
    }

    /**
     * @return resource
     */
    public function readStream(string $path)
    {
        $stream = @fopen($this->getUrl($path), 'rb');

        if ($stream === false) {
            throw UnableToReadFile::fromLocation($path, 'Не удалось открыть файл.');
        }

        return $stream;
    }

    public function delete(string $path): void
    {
        try {
            $this->client()->uploadApi()->destroy($this->publicId($path), ['invalidate' => true]);
        } catch (\Throwable $e) {
            throw UnableToDeleteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $this->client();
        } catch (RuntimeException) {
            return;
        }

        $folder = rtrim($path, '/');

        try {
            try {
                $this->client()->adminApi()->deleteAssetsByPrefix($folder.'/');
            } catch (NotFound) {
                // папка пуста или её нет — продолжаем
            }

            try {
                $this->client()->adminApi()->deleteFolder($folder);
            } catch (NotFound) {
                // папки не существует — деградируем молча
            }
        } catch (\Throwable $e) {
            throw UnableToDeleteDirectory::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        // В Cloudinary папки неявные — создавать ничего не нужно.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        if (! in_array($visibility, ['public', 'private'], true)) {
            throw InvalidVisibilityProvided::withVisibility($visibility, 'public|private');
        }
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        $mimeType = $this->mimeTypeForFormat($this->formatFromPath($path));

        if ($mimeType === null) {
            throw UnableToRetrieveMetadata::mimeType($path, 'Не удалось определить MIME-тип.');
        }

        return new FileAttributes($path, null, null, null, $mimeType);
    }

    public function lastModified(string $path): FileAttributes
    {
        $resource = $this->resource($path);

        if ($resource === null || empty($resource['created_at'])) {
            throw UnableToRetrieveMetadata::lastModified($path, 'Asset не найден.');
        }

        return new FileAttributes($path, null, null, (int) strtotime((string) $resource['created_at']));
    }

    public function fileSize(string $path): FileAttributes
    {
        $resource = $this->resource($path);

        if ($resource === null || ! isset($resource['bytes'])) {
            throw UnableToRetrieveMetadata::fileSize($path, 'Asset не найден.');
        }

        return new FileAttributes($path, (int) $resource['bytes']);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = rtrim(ltrim($path, '/'), '/');

        if ($prefix !== '') {
            $prefix .= '/';
        }

        try {
            $cursor = null;
            $directories = [];

            do {
                $options = ['prefix' => $prefix, 'max_results' => 100];

                if ($cursor !== null) {
                    $options['next_cursor'] = $cursor;
                }

                $response = $this->client()->adminApi()->assets($options);

                foreach ($response['resources'] ?? [] as $resource) {
                    $publicId = (string) ($resource['public_id'] ?? '');
                    $relative = $publicId !== '' ? substr($publicId, strlen($prefix)) : '';

                    if ($relative === '') {
                        continue;
                    }

                    $format = isset($resource['format']) ? (string) $resource['format'] : '';

                    if (! $deep && str_contains($relative, '/')) {
                        $directory = substr($relative, 0, strpos($relative, '/'));

                        if (! isset($directories[$directory])) {
                            $directories[$directory] = true;

                            yield new DirectoryAttributes($prefix.$directory);
                        }

                        continue;
                    }

                    yield new FileAttributes(
                        $publicId.($format !== '' ? '.'.$format : ''),
                        isset($resource['bytes']) ? (int) $resource['bytes'] : null,
                        'public',
                        isset($resource['created_at']) ? (int) strtotime((string) $resource['created_at']) : null,
                        $this->mimeTypeForFormat($format)
                    );
                }

                $cursor = isset($response['next_cursor']) ? (string) $response['next_cursor'] : null;
            } while ($cursor !== null);
        } catch (NotFound) {
            return;
        } catch (\Throwable $e) {
            throw UnableToListContents::atLocation($path, $deep, $e);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->client()->uploadApi()->rename(
                $this->publicId($source),
                $this->publicId($destination),
                ['overwrite' => true]
            );
        } catch (\Throwable $e) {
            throw UnableToMoveFile::because($e->getMessage(), $source, $destination);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->client()->uploadApi()->upload($this->getUrl($source), [
                'public_id' => $this->publicId($destination),
                'overwrite' => true,
            ]);
        } catch (\Throwable $e) {
            throw UnableToCopyFile::because($e->getMessage(), $source, $destination);
        }
    }

    /**
     * Прямая ссылка на изображение в CDN. Не требует обращения к API и работает
     * даже без учётных данных: применяется Laravel/Filament'ом при выводе
     * URL через FilesystemAdapter::url().
     */
    public function getUrl(string $path): string
    {
        $cloudName = trim((string) ($this->config['cloud_name'] ?? ''), '/');

        $base = $cloudName !== ''
            ? self::DEFAULT_BASE_URL.'/'.$cloudName.'/image/upload'
            : self::DEFAULT_BASE_URL.'/image/upload';

        return rtrim($base, '/').'/'.ltrim($path, '/');
    }

    private function uploadContents(string $path, string $contents): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'cloudinary');

        if ($temporary === false) {
            throw UnableToWriteFile::atLocation($path, 'Не удалось создать временный файл.');
        }

        try {
            if (@file_put_contents($temporary, $contents) === false) {
                throw UnableToWriteFile::atLocation($path, 'Не удалось записать временный файл.');
            }

            /*
             * Безопасная диагностика production: в лог уходит ФАКТИЧЕСКИЙ URL
             * Upload API, который строит SDK, имя облака и версия SDK. Здесь
             * НЕТ api_key, api_secret и CLOUDINARY_URL целиком — в URL учётных
             * данных нет. По этой строке в логе Render сразу видно, на какой
             * адрес реально уходит upload.
             */
            $cloudName = trim((string) ($this->config['cloud_name'] ?? ''));

            if ($cloudName !== '') {
                Log::info('Cloudinary upload: фактический URL Upload API.', [
                    'upload_url' => (new Cloudinary($this->configuration()))->uploadApi()->getUploadUrl('image'),
                    'cloud_name' => $cloudName,
                    'sdk_version' => Cloudinary::VERSION,
                ]);
            }

            $this->client()->uploadApi()->upload($temporary, [
                'public_id' => $this->publicId($path),
                'overwrite' => true,
            ]);
        } catch (UnableToWriteFile $e) {
            throw $e;
        } catch (\Throwable $e) {
            /*
             * Безопасная диагностика: в лог уходят только путь назначения и
             * текст ошибки. Учётные данные (api_key/api_secret) сюда не
             * попадают: они не входят ни в path, ни в сообщение исключения
             * Cloudinary. Без этой строки сбой «не задан cloud_name» не
             * оставлял вообще ничего в логах приложения.
             */
            Log::error('Не удалось загрузить файл в Cloudinary.', [
                'path' => $path,
                'reason' => $e->getMessage(),
            ]);

            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        } finally {
            @unlink($temporary);
        }
    }

    private function resource(string $path): ?array
    {
        try {
            return (array) $this->client()->adminApi()->asset($this->publicId($path));
        } catch (NotFound) {
            return null;
        } catch (\Throwable $e) {
            throw UnableToRetrieveMetadata::mimeType($path, $e->getMessage(), $e);
        }
    }

    /**
     * Публичный id Cloudinary — путь без расширения файла.
     */
    private function publicId(string $path): string
    {
        $path = ltrim($path, '/');
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        if ($extension !== '') {
            $path = substr($path, 0, -(strlen($extension) + 1));
        }

        return $path;
    }

    private function formatFromPath(string $path): string
    {
        $format = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return $format === '' ? 'png' : $format;
    }

    private function mimeTypeForFormat(string $format): ?string
    {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp',
            'heic' => 'image/heic',
            'heif' => 'image/heif',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
        ];

        return isset($mimeTypes[$format]) ? $mimeTypes[$format] : null;
    }
}
