<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Таблица товаров.
 *
 * ПОЧЕМУ ЗДЕСЬ ПЯТЬ КОЛОНОК, А НЕ ВСЕ ПОЛЯ
 *
 * У товара одиннадцать колонок, и пять из них — длинные тексты
 * (short_description, packaging, storage, additional_info) плюс вес и срок
 * годности. Выводить их в таблице значит показать администратору строки
 * шириной в пол-экрана, где различить записи между собой невозможно.
 * Эти поля живут в форме редактирования, где для них есть место, — а в
 * списке остаются то, по чему товар опознают: название, категория, адрес,
 * активность и порядок вывода.
 *
 * Активность вынесена отдельной колонкой с переключателем: это самое частое
 * действие в каталоге (скрыть товар, не трогая запись), и делать его через
 * форму значило бы открывать страницу ради одного клика.
 */
class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                /*
                 * Категория выводится как связь category.name, а не как
                 * product_category_id: администратор оперирует названиями
                 * («Яйца кур», «Мясо кур»), а не числами.
                 *
                 * Связь загружается одним запросом: Filament сам добавляет
                 * with('category') для колонок-связей, поэтому на каждую
                 * строку дополнительного SELECT не уходит. Количество
                 * запросов зафиксировано тестом — как и на Этапе 9.2.
                 */
                TextColumn::make('category.name')
                    ->label('Категория')
                    ->sortable()
                    // Поиск по названию категории имеет смысл, но идёт
                    // через связь, поэтому ограничен явным поиском по
                    // relation: «яйц» находит товар из категории «Яйца кур».
                    ->searchable(['relation', 'name']),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                ToggleColumn::make('is_active')
                    ->label('Активен'),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
            ])
            /*
             * Порядок по умолчанию: sort_order категории, затем sort_order
             * товара, затем id товара.
             *
             * Товары принадлежат разделам каталога, и администратор
             * ожидает увидеть их сгруппированными по категориям, а не
             * перемешанными в общем списке. Внутри категории порядок задаёт
             * sort_order — то самое поле, которое видно в таблице.
             *
             * Сначала именно sort_order КАТЕГОРИИ, а не её название. Порядок
             * разделов на сайте задаёт sort_order (Яйца кур — 1, Мясо кур —
             * 2), и группировка по названию противоречила бы сайту: по
             * алфавиту «Мясо кур» встал бы раньше «Яйца кур».
             *
             * Сортировка задана замыканием, возвращающим Builder, потому что
             * defaultSort() принимает ОДИН столбец, а нужен составной из трёх.
             * Это штатный способ Filament задать составной порядок; обход
             * через modifyQueryUsing здесь не нужен.
             *
             * Порядок категории берётся подзапросом, а не JOIN. JOIN здесь
             * ломал бы счётчик строк для пагинации (одна строка товара
             * превратилась бы в пару «товар + категория») и добавил бы в
             * выборку колонки присоединяемой таблицы, что Filament
             * специально избегает. Подзапрос в ORDER BY не трогает ни
             * предложение SELECT, ни подсчёт, ни загрузку связи category.
             *
             * Третий ключ — products.id — стабилизирует выдачу товаров с
             * одинаковым sort_order внутри категории. defaultKeySort() ниже
             * оставлен как страховка для сортировки, заданной администратором
             * кликом по заголовку: он сам обнаруживает, что id уже в порядке,
             * и потому дублировать его не станет.
             */
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderBy(
                    // Внутреннее замыкание получает уже Query\Builder —
                    // Query\Builder::orderBy() сам собирает подзапрос и
                    // подставляет его в ORDER BY. Тип внешнего замыкания —
                    // Eloquent\Builder (его отдаёт Filament), внутреннего —
                    // Query\Builder.
                    fn (QueryBuilder $sub): QueryBuilder => $sub
                        ->select('sort_order')
                        ->from('product_categories')
                        ->whereColumn('product_categories.id', 'products.product_category_id'),
                )
                ->orderBy('products.sort_order')
                ->orderBy('products.id'))
            ->defaultKeySort()
            ->recordActions([
                // Единственное действие над записью — открыть её на
                // редактирование. Ни удаления, ни просмотра во вкладке.
                EditAction::make(),
            ])
            ->toolbarActions([
                // Групповых действий нет намеренно. canDeleteAny() уже
                // скрывает удаление, но пустой список означает и то, что
                // панель групповых действий не отрисуется вовсе: защита
                // должна быть видна и в вёрстке, а не только в политике.
            ]);
    }
}
