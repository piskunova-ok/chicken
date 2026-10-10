<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Редактирование категории продукции.
 *
 * Удаление доступно только пустой категории: canDelete() в ресурсе
 * возвращает !$record->products()->exists(), поэтому кнопка у категории с
 * товарами скрыта (база тот же случай страхует restrictOnDelete). Группового
 * удаления у категорий нет вовсе.
 */
class EditProductCategory extends EditRecord
{
    protected static string $resource = ProductCategoryResource::class;

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
