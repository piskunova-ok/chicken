<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\CloudinaryAssets;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Документ о качестве, публикуемый на странице «Качество» (/quality).
 *
 * Запись хранит только подпись и относительный путь к файлу на облачном
 * диске. Публичный адрес, различение изображения и PDF и уборку файла при
 * удалении записи модель берёт на себя — странице остаётся перебрать
 * активные документы.
 *
 * $guarded = [] не используется намеренно: явный #[Fillable] фиксирует
 * контракт полей и защищает id и timestamps от массового присваивания.
 */
#[Fillable(['title', 'file', 'is_active', 'sort_order'])]
class QualityCertificate extends Model
{
    /**
     * Публичный адрес документа либо null, если файл не задан.
     */
    public function fileUrl(): ?string
    {
        return CloudinaryAssets::url($this->file);
    }

    /**
     * PDF это или нет — от этого зависит, как страница выводит документ:
     * изображение показывается картинкой, PDF — ссылкой-плиткой.
     */
    public function isPdf(): bool
    {
        return $this->extension() === 'pdf';
    }

    /**
     * Расширение файла в нижнем регистре (пустая строка, если пути нет).
     */
    public function extension(): string
    {
        if (! is_string($this->file) || trim($this->file) === '') {
            return '';
        }

        return strtolower((string) pathinfo($this->file, PATHINFO_EXTENSION));
    }

    /**
     * Приведение типов атрибутов.
     *
     * SQLite не различает boolean и integer, поэтому без cast'ов is_active
     * вернул бы 1, а sort_order — строку.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Удаление записи уносит её файл (best-effort).
     *
     * Ошибка уборки не мешает удалить запись: файл мог не попасть на диск
     * (Cloudinary ещё не настроен), а удаление записи не должно зависеть от
     * доступности CDN.
     */
    protected static function booted(): void
    {
        static::deleting(function (QualityCertificate $certificate): void {
            CloudinaryAssets::delete($certificate->file);
        });
    }
}
