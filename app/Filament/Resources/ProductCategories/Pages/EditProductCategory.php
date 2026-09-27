<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Редактирование категории продукции.
 *
 * Кнопки удаления здесь нет. Удаление закрыто в трёх местах: действие не
 * добавляется в getHeaderActions(), а canDelete() и canDeleteAny() в
 * ресурсе возвращают false. Одной пустой строки было бы достаточно для
 * интерфейса, но политика обязана закрывать и обход интерфейса.
 */
class EditProductCategory extends EditRecord
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
