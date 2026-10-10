<?php

declare(strict_types=1);

namespace App\Filament\Resources\QualityCertificates\Pages;

use App\Filament\Resources\QualityCertificates\QualityCertificateResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Создание сертификата качества.
 *
 * Уборки файла здесь нет и быть не может: у новой записи нет старого файла.
 */
class CreateQualityCertificate extends CreateRecord
{
    protected static string $resource = QualityCertificateResource::class;
}
