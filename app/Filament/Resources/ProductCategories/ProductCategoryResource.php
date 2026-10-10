<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductCategories;

use App\Filament\Resources\ProductCategories\Pages\CreateProductCategory;
use App\Filament\Resources\ProductCategories\Pages\EditProductCategory;
use App\Filament\Resources\ProductCategories\Pages\ListProductCategories;
use App\Filament\Resources\ProductCategories\Schemas\ProductCategoryForm;
use App\Filament\Resources\ProductCategories\Tables\ProductCategoriesTable;
use App\Models\ProductCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Управление категориями продукции: /products/{slug}.
 *
 * РЕСУРС В РЕЖИМЕ READ + CREATE + UPDATE + (DELETE, ЕСЛИ КАТЕГОРИЯ ПУСТА)
 *
 * Категория — опубликованный раздел каталога, и её жизненный цикл намеренно
 * отличается от жизненного цикла товара.
 *
 * Создание: разрешено. Новая категория живёт по общей категорийной
 * разметке: страница /products/{slug} собирается из записи (название,
 * описание), а не из ключей config/catalog.php, которых для неё нет.
 * Slug вводится при создании и больше не меняется.
 *
 * Удаление: категорию можно удалить, ТОЛЬКО если у неё нет товаров —
 * canDelete() возвращает !$record->products()->exists(), а внешний ключ с
 * restrictOnDelete страхует тот же случай на уровне базы. Пустая категория
 * удаляется вместе со своим адресом; категория с товарами не удаляется
 * никак, чтобы ассортимент не пропал разом.
 *
 * Группового удаления нет вовсе: canDeleteAny() возвращает false, панель
 * групповых действий в таблице не регистрируется. Удаление — только по
 * одной категории с подтверждением.
 *
 * ЧТО РЕДАКТИРУЕТСЯ
 *
 * Название, описание, порядок, признак активности. slug при правке не
 * меняется: он входит в публичный URL, и его смена тихо унесла бы старый
 * адрес в 404. Подробнее — в ProductCategoryForm.
 */
class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    /**
     * Атрибут, которым запись подписывается в интерфейсе: в заголовке
     * страницы редактирования, в хлебных крошках и в глобальном поиске.
     * Без него панель показывала бы «Категория #1» вместо «Яйца кур».
     */
    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ProductCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        // Связи не выводятся: список товаров категории показан в общем
        // ресурсе товаров, а не вкладкой на странице категории.
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductCategories::route('/'),
            'create' => CreateProductCategory::route('/create'),
            'edit' => EditProductCategory::route('/{record}/edit'),
        ];
    }

    /**
     * Создание категории разрешено: см. шапку класса.
     */
    public static function canCreate(): bool
    {
        return true;
    }

    /**
     * Удаление категории разрешено только у записи без товаров.
     *
     * Тот же запрет в базе держит внешний ключ (restrictOnDelete), здесь
     * он нужен интерфейсу: кнопка скрывается у категорий с товарами, а не
     * нажимается «вслепую» с последующей ошибкой базы.
     */
    public static function canDelete(Model $record): bool
    {
        return ! $record->products()->exists();
    }

    /**
     * Групповое удаление недоступно: удаление категории — только по одной.
     */
    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Человеческие названия раздела.
     *
     * Без них Filament выводит английские «Product categories» — в русском
     * интерфейсе это выглядит как недоделка. Имена заданы явно, а не
     * переведены файлами локализации: в проекте их нет, а единственная
     * панель принадлежит этому проекту.
     */
    public static function getModelLabel(): string
    {
        return 'Категория';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Категории';
    }

    public static function getNavigationLabel(): string
    {
        return 'Категории';
    }

    /**
     * Группа «Каталог» заведена уже сейчас, хотя в ней пока один пункт.
     *
     * Этап 9.3 добавит сюда управление товарами, и без группы оба раздела
     * оказались бы рядом в корне навигации вперемешку со служебными пунктами
     * Filament. Назвать группу заранее дешевле, чем потом разбираться, куда
     * переехали разделы.
     */
    public static function getNavigationGroup(): ?string
    {
        return 'Каталог';
    }
}
