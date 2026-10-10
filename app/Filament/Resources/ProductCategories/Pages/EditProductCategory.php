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
 * возвращает !$record->products()->exists(), поэтому действие не
 * регистрируется вовсе — кнопки у категории с товарами нет в панели (база
 * тот же случай страхует restrictOnDelete). Группового удаления у категорий
 * нет нигде.
 */
class EditProductCategory extends EditRecord
{
    protected static string $resource = ProductCategoryResource::class;

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        $actions = [];

        // canDelete() учитывается здесь, а не видимостью действия: кнопки у
        // занятой категории не должно быть вообще, а не только в узком
        // состоянии «не видна в этой сессии запроса».
        if (ProductCategoryResource::canDelete($this->getRecord())) {
            $actions[] = DeleteAction::make();
        }

        return $actions;
    }
}
