<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductCategories\Pages;

use App\Filament\Resources\ProductCategories\ProductCategoryResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Создание категории продукции.
 *
 * Slug задаётся здесь и только здесь: поле дегидрируется на create, а при
 * правке остаётся неизменным (см. ProductCategoryForm). Новая категория
 * сразу доступна владельцу: страница /products/{slug} открывается по общей
 * категорийной разметке, в карточку на /products попадает активная запись.
 */
class CreateProductCategory extends CreateRecord
{
    protected static string $resource = ProductCategoryResource::class;
}