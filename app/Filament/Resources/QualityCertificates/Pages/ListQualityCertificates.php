<?php

declare(strict_types=1);

namespace App\Filament\Resources\QualityCertificates\Pages;

use App\Filament\Resources\QualityCertificates\QualityCertificateResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Список сертификатов качества.
 *
 * В шапке — кнопка «Создать сертификат»: она появляется при canCreate().
 */
class ListQualityCertificates extends ListRecords
{
    protected static string $resource = QualityCertificateResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
