<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\EditRecord;

/**
 * Редактирование товара.
 *
 * Кнопки удаления здесь нет. Удаление закрыто в трёх местах: действие не
 * добавлено в getHeaderActions(), а canDelete() и canDeleteAny() в ресурсе
 * возвращают false. Одной пустой строки было бы достаточно для интерфейса,
 * но политика обязана закрывать и обход интерфейса.
 *
 * Отличие от ProductCategoryResource: здесь есть создание, поэтому
 * администратор может добавить товар в существующую категорию.
 */
class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
