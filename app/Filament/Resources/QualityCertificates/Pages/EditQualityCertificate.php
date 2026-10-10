<?php

declare(strict_types=1);

namespace App\Filament\Resources\QualityCertificates\Pages;

use App\Filament\Resources\QualityCertificates\QualityCertificateResource;
use App\Support\CloudinaryAssets;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Редактирование сертификата качества.
 *
 * ЖИЗНЕННЫЙ ЦИКЛ ФАЙЛА: ЗАМЕНА И ОЧИСТКА УБИРАЮТ СТАРЫЙ ФАЙЛ
 *
 * Новый файл сохраняет на диск сам FileUpload при дегидратации формы. Здесь
 * остаётся убрать СТАРЫЙ файл, и только после успешной записи:
 *
 *  - beforeSave() запоминает путь из базы, пока запись ещё не обновлена;
 *  - afterSave() удаляет старый файл, если он отличался от нового значения.
 *
 * Уборка best-effort (CloudinaryAssets::delete): запись к этому моменту уже
 * сохранена, и недоступность CDN не должна превращать сохранение в 500-ю.
 * Удаление всей записи уносит её файл отдельно — событие deleting модели.
 */
class EditQualityCertificate extends EditRecord
{
    protected static string $resource = QualityCertificateResource::class;

    /**
     * Путь файла, прочитанный из БД ДО сохранения.
     */
    private ?string $previousFile = null;

    protected function beforeSave(): void
    {
        $this->previousFile = $this->record->getOriginal('file');
    }

    protected function afterSave(): void
    {
        $previousFile = $this->previousFile;

        unset($this->previousFile);

        if ($previousFile === null || $previousFile === '') {
            return;
        }

        if ($previousFile === $this->record->file) {
            return;
        }

        CloudinaryAssets::delete($previousFile);
    }

    /**
     * Вверху страницы — удаление записи вместе с уборкой файла (событие
     * deleting модели QualityCertificate).
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
