<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Список категорий продукции.
 *
 * Кнопки «Создать» здесь нет и быть не должна: страницы создания у ресурса
 * нет вовсе (см. ProductCategoryResource::getPages()), а canCreate() вернул
 * бы false в любом случае. Пустой getHeaderActions() — это и отсутствие
 * заголовка страницы, и отсутствие кнопки, то есть защита видна в вёрстке,
 * а не только в политике.
 */
class ListProductCategories extends ListRecords
{
    protected static string $resource = ProductCategoryResource::class;

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
