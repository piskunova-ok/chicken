<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Список категорий продукции.
 *
 * В шапке — кнопка «Создать категорию»: она появляется при canCreate()
 * и ведёт на /admin/.../product-categories/create.
 */
class ListProductCategories extends ListRecords
{
    protected static string $resource = ProductCategoryResource::class;

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
