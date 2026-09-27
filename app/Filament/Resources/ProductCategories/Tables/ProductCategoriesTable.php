<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductCategories\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Таблица категорий продукции.
 *
 * Сортировка по умолчанию — sort_order, а не id: порядок разделов в
 * каталоге задаёт поле sort_order, и именно его показывает администратор.
 * Порядок вставки (id) — техническая величина.
 */
class ProductCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    // Подсветка нужна, чтобы администратор видел главное
                    // предупреждение этого этапа прямо в списке: именно
                    // это значение завязано на публичные адреса и на ключи
                    // config/catalog.php, и его нельзя менять из панели.
                    ->badge()
                    ->color('gray'),

                ToggleColumn::make('is_active')
                    ->label('Активна'),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),

                /*
                 * Количество товаров берётся агрегатом в том же запросе,
                 * который и так выбирает строки, — products_count
                 * подставляет счётчик подзапроса (withCount), а не
                 * отдельный запрос на каждую строку.
                 *
                 * counts() — штатный способ Filament 5 сделать именно
                 * withCount: без него пришлось бы переопределять
                 * getEloquentQuery() таблицы, и тогда правило подсчёта
                 * жило бы в Resource, а колонка молчала бы о том, откуда
                 * взялось значение. Ручной $category->products()->count()
                 * в колонке дал бы N+1: по запросу на категорию.
                 *
                 * Колонка сортируема: счётчик попадает в select, и база
                 * может упорядочить по нему без отдельного подсчёта.
                 */
                TextColumn::make('products_count')
                    ->label('Товаров')
                    ->counts('products')
                    ->sortable(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('sort_order'))
            /**
             * Второй ключ сортировки — id.
             *
             * defaultKeySort() добавляет его сам, штатным способом, и ровно
             * тогда, когда он ещё не задан. Без него две категории с
             * одинаковым sort_order (частый результат ручной правки или
             * повторного включения) могли бы возвращаться в любом порядке
             * от запроса к запросу, и список выглядел бы нестабильным при
             * простом обновлении страницы.
             *
             * Задано явно, хотя значение по умолчанию уже true: правило
             * «сортируй по главному ключу, если не сказано иначе» должно
             * быть видно в коде, а не зависеть от значения в вендоре.
             *
             * Имя метода — defaultKeySort(), а не hasDefaultKeySort():
             * последнее без аргументов читает признак, а не задаёт его.
             */
            ->defaultKeySort()
            ->recordActions([
                // Единственное действие над записью — открыть её на
                // редактирование. Ни удаления, ни просмотра во вкладке.
                EditAction::make(),
            ])
            ->toolbarActions([
                // Групповых действий нет намеренно. canDeleteAny() уже
                // скрывает удаление, но пустой списокToolbarActions
                // означает и то, что панель групповых действий не
                // отрисуется вовсе: защита должна быть видна и в вёрстке,
                // а не только в политике.
            ]);
    }
}
