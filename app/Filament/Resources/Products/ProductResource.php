<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Управление товарами: /products/{категория}/{товар}.
 *
 * РЕСУРС В РЕЖИМЕ READ + CREATE + UPDATE + DELETE
 *
 * В отличие от категории, товар — обычная запись каталога: его создание,
 * правка и удаление ничего не ломают на сайте. Скрыть же товар с сайта
 * без окончательного удаления по-прежнему можно переключателем is_active.
 *
 * ПОЧЕМУ УДАЛЕНИЕ ОТКРЫТО (и что оно делает)
 *
 * Удаление записи идёт вместе с физической уборкой фотографии: событие
 * deleting модели Product снимает файл с диска Cloudinary (пути
 * «products/cld-…») либо с публичного диска (старые «products/…»)
 * best-effort — неудачная чистка не мешает удалить саму запись.
 *
 * Открыто тремя согласованными способами: DeleteAction зарегистрирован на
 * странице редактирования и в таблице, групповое удаление — в панели
 * таблицы, canDelete() и canDeleteAny() возвращают true. Неактивный товар
 * удалить можно — на сайте он и так не показывается.
 *
 * ЧТО РЕДАКТИРУЕТСЯ
 *
 * Название, категория, slug, текстовые характеристики, порядок и признак
 * активности. В отличие от категории, slug товара редактируется: он входит
 * в составной уникальный ключ (product_category_id, slug), а не в ключ
 * конфига, поэтому его смена не ломает оформление страницы. Подробнее — в
 * ProductForm.
 *
 * ЗАГРУЗКА ИЗОБРАЖЕНИЙ
 *
 * Колонка image — FileUpload на облачном диске 'cloudinary' (каталог
 * products) с именами «products/cld-<ulid>.<ext>». В базе хранится только
 * относительный путь, публичный URL строится через Storage::disk('cloudinary')
 * ->url() (см. Product::imageUrl). Физическое удаление старого файла при
 * замене/очистке — на странице EditProduct (beforeSave/afterSave), при
 * удалении записи — события deleting модели. Старые пути «products/…» и
 * «images/products/…» продолжают читаться публичной страницей без изменений.
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    /**
     * Атрибут, которым запись подписывается в интерфейсе: заголовок
     * страницы редактирования, хлебные крошки, глобальный поиск.
     * Без него панель показывала бы «Товар #1».
     */
    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        // Связи не выводятся: у товара их нет, а пустой список честнее
        // вкладки, которая ведёт в никуда.
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    /**
     * Создание товара разрешено: запись каталога ничего не ломает на сайте.
     */
    public static function canCreate(): bool
    {
        return true;
    }

    /**
     * Удаление отдельного товара разрешено: см. шапку класса.
     */
    public static function canDelete(Model $record): bool
    {
        return true;
    }

    /**
     * Групповое удаление разрешено: тем же основанием, что и canDelete().
     */
    public static function canDeleteAny(): bool
    {
        return true;
    }

    /**
     * Человеческие названия раздела.
     *
     * Заданы явно, а не переведены файлами локализации: в проекте их нет,
     * а единственная панель принадлежит этому проекту.
     */
    public static function getModelLabel(): string
    {
        return 'Товар';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Товары';
    }

    public static function getNavigationLabel(): string
    {
        return 'Товары';
    }

    /**
     * Группа «Каталог» — та же, что у ProductCategoryResource, чтобы
     * «Категории» и «Товары» стояли рядом, а не поодиночке в корне навигации.
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Каталог';
    }
}
