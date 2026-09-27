<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductCategories;

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
 * РЕСУРС В РЕЖИМЕ READ + UPDATE
 *
 * Категория не создаётся и не удаляется через админку, и это решение о
 * устройстве данных, а не временная недоработка интерфейса.
 *
 * Создание: публичная часть сейчас рассчитана на два известных адреса —
 * /products/eggs и /products/chicken. Оформление этих страниц живёт в
 * config/catalog.php, то есть описание категории обязано существовать и в
 * базе, и в конфиге. Категория, добавленная через админку, получила бы
 * строку в базе без единого ключа в конфиге, и её страница отдала бы пустые
 * eyebrow, hero, intro и заголовок каталога. Хуже того, раздел появился бы в
 * панели, но не на сайте — то есть администратор увидел бы неработающую
 * запись как рабочую. Поэтому страница создания не зарегистрирована вовсе:
 * маршрута /create не существует, а не «существует и отказывает».
 *
 * Удаление: с категорией связаны товары, и внешний ключ объявлен с
 * restrictOnDelete — удалить категорию с товарами база не даст, а удалить
 * пустую можно, и это куда опаснее: адрес /products/{slug} перестанет
 * работать, при этом сама категория исчезнет из панели вместе с историей
 * того, что по этому адресу было. Чтобы случайная кнопка не могла убрать
 * раздел каталога, удаление закрыто тремя независимыми способами: не
 * зарегистрировано действие DeleteAction, canDelete() и canDeleteAny()
 * возвращают false, а группового удаления в таблице просто нет.
 *
 * ПОЧЕМУ ЗАКРЫТО ТРИЖДЫ, А НЕ ОДНОЙ ПРОВЕРКОЙ
 *
 * canDelete() — это политика, а не физическое ограничение. Проверка
 * авторизации живёт в контроллере: тот, кто вызовет удаление в обход
 * интерфейса, получит отказ, но опирается на код, который легко случайно
 * отключить вместе с кнопкой. Здесь закрыты все три слоя — маршрут,
 * разрешение и отсутствие действия, — поэтому случайное удаление невозможно
 * даже при невнимательной правке интерфейса.
 *
 * ЧТО РЕДАКТИРУЕТСЯ
 *
 * Название, порядок и признак активности. slug не меняется: он входит в
 * публичный URL и в ключи config/catalog.php, то есть это не поле записи, а
 * ключ, по которому сайт ищет страницу. Подробнее — в ProductCategoryForm.
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
        // Связи не выводятся: просмотр товаров категории — задача Этапа 9.3
        // вместе с ProductResource. Пока их нет, пустой список честнее
        // вкладки, которая ведёт в никуда.
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductCategories::route('/'),
            'edit' => EditProductCategory::route('/{record}/edit'),

            // Страницы создания нет и создавать её на этом этапе нельзя.
            // Отсутствие ключа 'create' здесь — основная причина, по которой
            // маршрута /create не существует вовсе.
        ];
    }

    /**
     * Создание категории недоступно: см. шапку класса.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Удаление отдельной категории недоступно: см. шапку класса.
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
