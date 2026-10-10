<?php

declare(strict_types=1);

namespace App\Filament\Resources\QualityCertificates;

use App\Filament\Resources\QualityCertificates\Pages\CreateQualityCertificate;
use App\Filament\Resources\QualityCertificates\Pages\EditQualityCertificate;
use App\Filament\Resources\QualityCertificates\Pages\ListQualityCertificates;
use App\Filament\Resources\QualityCertificates\Schemas\QualityCertificateForm;
use App\Filament\Resources\QualityCertificates\Tables\QualityCertificatesTable;
use App\Models\QualityCertificate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Управление сертификатами качества: страница /quality.
 *
 * РЕСУРС В РЕЖИМЕ READ + CREATE + UPDATE + DELETE
 *
 * Сертификат — обычная запись контента: его создание, правка и удаление
 * ничего не ломают на публичной странице. Скрыть документ без удаления можно
 * переключателем is_active — запись и файл сохраняются.
 *
 * Удаление записи идёт вместе с уборкой файла: событие deleting модели
 * QualityCertificate снимает файл с облачного диска best-effort — неудачная
 * чистка не мешает удалить саму запись.
 *
 * ЧТО РЕДАКТИРУЕТСЯ
 *
 * Название, файл, порядок и признак активности. Набор полей совпадает с
 * колонками quality_certificates. Подробнее — в QualityCertificateForm.
 */
class QualityCertificateResource extends Resource
{
    protected static ?string $model = QualityCertificate::class;

    /**
     * Атрибут, которым запись подписывается в интерфейсе: заголовок
     * страницы редактирования, хлебные крошки, глобальный поиск.
     */
    protected static ?string $recordTitleAttribute = 'title';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    public static function form(Schema $schema): Schema
    {
        return QualityCertificateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QualityCertificatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        // Связей у сертификата нет: пустой список честнее вкладки в никуда.
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQualityCertificates::route('/'),
            'create' => CreateQualityCertificate::route('/create'),
            'edit' => EditQualityCertificate::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canDelete(Model $record): bool
    {
        return true;
    }

    public static function canDeleteAny(): bool
    {
        return true;
    }

    /**
     * Человеческие названия раздела, заданные явно: в проекте нет файлов
     * локализации, а единственная панель принадлежит этому проекту.
     */
    public static function getModelLabel(): string
    {
        return 'Сертификат';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Сертификаты';
    }

    public static function getNavigationLabel(): string
    {
        return 'Сертификаты';
    }

    /**
     * Группа «Сайт» — та же, что у страницы «Настройки сайта»: оба раздела
     * управляют содержимым публичного сайта и стоят рядом.
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Сайт';
    }
}
