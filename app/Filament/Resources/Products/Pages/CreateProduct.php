<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Создание товара.
 *
 * Кнопки удаления здесь нет и быть не может: удаление закрыто в
 * ProductResource (canDelete() и canDeleteAny() возвращают false) и не
 * зарегистрировано в таблице. Запись создаётся, но не уничтожается —
 * скрыть товар с сайта можно переключателем is_active.
 */
class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
}
