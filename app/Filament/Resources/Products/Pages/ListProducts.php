<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * Список товаров.
 *
 * ОТЛИЧИЕ ОТ ListProductCategories
 *
 * У категорий действий в шапке нет: их нельзя ни создать, ни удалить.
 * Здесь создание открыто (ProductResource::canCreate() — true), поэтому
 * кнопка «Создать» нужна, иначе страница создания была бы достижима
 * только вручную по адресу.
 *
 * Кнопки удаления в шапке нет: удаление закрыто политикой ресурса
 * (canDelete(), canDeleteAny() — false), действие Delete не
 * зарегистрировано ни здесь, ни в таблице, а у товара нет и soft delete,
 * то есть скрыть его с сайта можно только переключателем is_active.
 */
class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

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
