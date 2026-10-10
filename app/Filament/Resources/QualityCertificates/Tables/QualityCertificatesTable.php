<?php

declare(strict_types=1);

namespace App\Filament\Resources\QualityCertificates\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Таблица сертификатов качества.
 *
 * Сортировка по умолчанию — sort_order, затем id: порядок документов на
 * странице «Качество» задаёт поле sort_order, а второй ключ удерживает
 * выдачу стабильной, когда у нескольких записей порядок совпадает.
 * Второй ключ добавляет defaultKeySort() — штатным способом.
 */
class QualityCertificatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Название')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('file')
                    ->label('Файл')
                    // В списке видно имя загруженного файла и его тип: путь в
                    // базе относительный (certificates/cld-…), и показывать
                    // его целиком незачем — администратору достаточно имени.
                    ->formatStateUsing(fn (?string $state): string => $state === null || $state === ''
                        ? '—'
                        : basename($state))
                    ->badge()
                    ->color('gray'),

                ToggleColumn::make('is_active')
                    ->label('Показан'),

                TextColumn::make('sort_order')
                    ->label('Порядок')
                    ->sortable(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderBy('sort_order'))
            ->defaultKeySort()
            ->recordActions([
                EditAction::make(),
                // Удаление записи уносит её файл из Cloudinary (событие
                // deleting модели QualityCertificate).
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
