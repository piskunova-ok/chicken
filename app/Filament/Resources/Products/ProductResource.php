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
 * РЕСУРС В РЕЖИМЕ READ + CREATE + UPDATE
 *
 * Отличие от ProductCategoryResource — сознательное. Категория задаёт
 * устройство публичного раздела (её описание живёт в config/catalog.php),
 * поэтому её нельзя ни добавить, ни убрать из панели. Товар же — обычная
 * запись каталога: его создание и правка ничего не ломают на сайте, а
 * закрывать их было бы искусственным ограничением.
 *
 * ПОЧЕМУ УДАЛЕНИЕ ЗАКРЫТО
 *
 * Товар скрывается с сайта переключателем is_active, и удалять запись для
 * этого не нужно: публичная страница и так показывает только активные
 * товары. Физическое удаление куда опаснее — адрес товара перестаёт
 * существовать вместе с записью, а отменить такое действие нельзя.
 *
 * Закрыто тремя независимыми способами, как и в ProductCategoryResource:
 * действие DeleteAction не зарегистрировано ни в таблице, ни на странице
 * редактирования, canDelete() и canDeleteAny() возвращают false, а панели
 * групповых действий в таблице нет вовсе. Одной проверки политики мало:
 * она защищает код, а отсутствие действия защищает интерфейс.
 *
 * Создание, в отличие от удаления, открыто: страница создания
 * зарегистрирована в getPages(), и canCreate() возвращает true.
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
 * Колонка image в таблице есть, но на этом этапе она не показывается и не
 * загружается: файловый ввод и работа с хранилищем — отдельная задача.
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
     * Удаление отдельного товара недоступно: см. шапку класса.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * Групповое удаление недоступно: тем же основанием, что и canDelete().
     */
    public static function canDeleteAny(): bool
    {
        return false;
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
