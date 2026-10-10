<?php

declare(strict_types=1);

namespace App\Filament\Resources\QualityCertificates\Schemas;

use App\Support\CloudinaryAssets;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;

/**
 * Форма сертификата качества (создание и редактирование).
 *
 * ПОЛЯ РОВНО ТЕ, ЧТО ЕСТЬ В ТАБЛИЦЕ quality_certificates
 *
 * title, file, is_active, sort_order — и ничего сверх. id, created_at и
 * updated_at служебные; выдумывать поля, которых в схеме нет, нельзя.
 *
 * ФАЙЛ — ИЗОБРАЖЕНИЕ ИЛИ PDF
 *
 * file уезжает на облачный диск 'cloudinary' (каталог certificates) с именем
 * «cld-<ulid>.<ext>». Принимаются картинки (скан сертификата) и PDF. PDF
 * Cloudinary хранит под ресурсным типом image и отдаёт по адресу …/<id>.pdf
 * (см. QualityCertificate::fileUrl). Ограничение acceptedFileTypes нужно,
 * чтобы в поле не попал произвольный файл: на странице «Качество» ссылка на
 * документ показывается посетителю, и неожиданный формат там неуместен.
 *
 * fetchFileInformation(false) выключает обращения к админ-API Cloudinary за
 * размерами и типом: форма работает и до настройки переменных окружения.
 */
class QualityCertificateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Название')
                    ->required()
                    // 255 — длина string-колонки из миграции.
                    ->maxLength(255)
                    ->helperText('Подпись документа, которую увидит посетитель. Например «Декларация о соответствии».'),

                FileUpload::make('file')
                    ->label('Файл')
                    ->disk('cloudinary')
                    ->directory('certificates')
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'application/pdf',
                    ])
                    ->maxSize(10240)
                    ->fetchFileInformation(false)
                    ->getUploadedFileNameForStorageUsing(
                        fn (UploadedFile $file): string => CloudinaryAssets::fileName($file),
                    )
                    ->columnSpanFull()
                    ->helperText('Изображение (JPG, PNG, WEBP) или PDF. Показывается на странице «Качество». Замена и удаление убирают старый файл.'),

                Toggle::make('is_active')
                    ->label('Показан на сайте')
                    ->default(true)
                    ->helperText('Выключенный документ исчезает со страницы «Качество», но запись и файл сохраняются.'),

                TextInput::make('sort_order')
                    ->label('Порядок')
                    ->required()
                    ->integer()
                    // Колонка объявлена unsignedInteger: отрицательное значение
                    // отсекается формой, а не ошибкой базы. Ноль допустим.
                    ->minValue(0)
                    ->default(0)
                    ->helperText('Меньшие значения выводятся раньше.'),
            ]);
    }
}
