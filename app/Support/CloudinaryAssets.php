<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Общие правила для файлов, которые админка кладёт в Cloudinary:
 * имя файла, публичный адрес и безопасное удаление.
 *
 * Логотип компании (brand/cld-…) и сертификаты (certificates/cld-…) —
 * новые загрузки, и правила у них одни и те же: админка, облачный диск,
 * маркер cld- в имени, выработка URL и уборка старого файла при замене.
 * Чтобы не повторять эти правила в двух местах, они собраны здесь.
 *
 * Товары (Product) намеренно НЕ переведены на этот класс: их жизненный цикл
 * (три типа путей переходного периода, локальный и облачный диски) уже
 * проверен тестами, и менять работающий механизм ради единообразия нельзя.
 * Здесь — только новые загрузки, которым префикс cld- и диск Cloudinary
 * известны заранее.
 */
final class CloudinaryAssets
{
    /**
     * Маркер облачной загрузки админки в имени файла.
     *
     * Повторяет Product::CLOUDINARY_PREFIX: по этому признаку путь в базе
     * отличим от локальной загрузки прошлых версий, а уборка выбирает
     * облачный диск.
     */
    public const PREFIX = 'cld-';

    /**
     * Имя файла для хранения: «cld-<ulid>.<расширение>».
     *
     * Расширение берётся из исходного имени, а если его нет — по содержимому.
     * Уникальный ulid исключает столкновение имён при повторной загрузке.
     */
    public static function fileName(UploadedFile $file): string
    {
        $extension = strtolower(
            $file->getClientOriginalExtension() ?: (string) $file->guessExtension()
        );

        return self::PREFIX.Str::ulid().($extension !== '' ? '.'.$extension : '');
    }

    /**
     * Публичный адрес файла либо null, если путь пуст.
     *
     * Адрес строится облачным диском, пока задан cloud_name, и публичным
     * диском в остальных случаях — та же деградация, что у Product::imageUrl:
     * до настройки переменных окружения Cloudinary страница не падает, а
     * отдаёт ссылку публичного диска (файла там нет, но картинка просто не
     * загрузится, вместо 500-й).
     */
    public static function url(?string $path): ?string
    {
        $path = self::normalize($path);

        if ($path === null) {
            return null;
        }

        return self::configured()
            ? Storage::disk('cloudinary')->url($path)
            : Storage::disk('public')->url($path);
    }

    /**
     * Удаление файла best-effort: отсутствие файла или недоступность CDN
     * не должны превращать успешное сохранение записи в ошибку 500.
     */
    public static function delete(?string $path): void
    {
        $path = self::normalize($path);

        if ($path === null) {
            return;
        }

        try {
            Storage::disk(self::configured() ? 'cloudinary' : 'public')->delete($path);
        } catch (Throwable) {
            // Уборка старого файла второстепенна по сравнению с сохранением.
        }
    }

    /**
     * Настроен ли Cloudinary (задан ли cloud_name в конфиге диска).
     */
    public static function configured(): bool
    {
        $cloudName = config('filesystems.disks.cloudinary.cloud_name');

        return is_string($cloudName) && trim($cloudName) !== '';
    }

    /**
     * Относительный безопасный путь или null: пустое значение, абсолютный
     * путь, выход за каталог и URL отбрасываются. Удалять и строить ссылку
     * вправе только управляемый относительный путь.
     */
    private static function normalize(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (str_starts_with($path, '/')
            || str_contains($path, '..')
            || str_contains($path, '\\')
            || str_contains($path, '://')
        ) {
            return null;
        }

        return $path;
    }
}
