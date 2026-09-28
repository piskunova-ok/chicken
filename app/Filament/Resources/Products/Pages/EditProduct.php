<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

/**
 * Редактирование товара.
 *
 * Кнопки удаления здесь нет. Удаление закрыто в трёх местах: действие не
 * добавлено в getHeaderActions(), а canDelete() и canDeleteAny() в ресурсе
 * возвращают false. Одной пустой строки было бы достаточно для интерфейса,
 * но политика обязана закрывать и обход интерфейса.
 *
 * Отличие от ProductCategoryResource: здесь есть создание, поэтому
 * администратор может добавить товар в существующую категорию.
 *
 * ЖИЗНЕННЫЙ ЦИКЛ ФАЙЛА ИЗОБРАЖЕНИЯ — ОДИН МЕХАНИЗМ
 *
 * Physical удаление старого изображения при замене и при очистке делает
 * пара хуков beforeSave()/afterSave() — и НИЧТО больше. deleteUploadedFileUsing
 * в форме намеренно не регистрируется, поэтому у файла нет двух конкурирующих
 * «удаляльщиков»: кнопка «удалить» в FileUpload только убирает путь из
 * состояния формы, а настоящей очисткой диска занимается страница.
 *
 * Порядок в Filament (EditRecord::save, см. вендор):
 *
 *  1. dehydrate формы → beforeStateDehydrated сохраняет НОВЫЙ загруженный
 *     файл на публичный диск (имя «products/<ulid>.<ext>»);
 *  2. validation — при ошибке сохранение прерывается, handleRecordUpdate
 *     и afterSave НЕ выполняются, старый файл и БД не трогаются;
 *  3. handleRecordUpdate() — запись получает новый путь (или NULL);
 *  4. afterSave() — ЗДЕСЬ удаляется старый файл, только если он существует,
 *     отличается от нового пути и лежит внутри public disk/products.
 *
 * Поэтому: новый файл всегда сохранён и записан в БД до удаления старого,
 * при ошибке validation ничего не удаляется, а удалить можно только файл
 * этого товара в безопасном каталоге public disk. После Create ничего
 * удалять не нужно: старого файла у новой записи нет.
 */
class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * Путь изображения, прочитанный из БД ДО сохранения.
     */
    private ?string $previousImage = null;

    /**
     * Запоминаем старое значение image из БД, пока запись ещё не обновлена.
     *
     * В beforeSave() (вызывается после validation) record всё ещё держит
     * значения из базы: handleRecordUpdate() применит данные формы только
     * после этого хука. getOriginal() страхует от любых правок атрибута
     * между hydrate и save.
     */
    protected function beforeSave(): void
    {
        $this->previousImage = $this->record->getOriginal('image');
    }

    /**
     * После успешной записи удаляем СТАРОЕ изображение с публичного диска.
     *
     * К этому моменту новый путь (или NULL) уже в БД: handleRecordUpdate()
     * выполнился. Файл удаляется только когда путь изменился и ведёт в
     * публичный products-каталог товаров.
     */
    protected function afterSave(): void
    {
        $previousImage = $this->previousImage;

        unset($this->previousImage);

        if (! is_string($previousImage) || $previousImage === '') {
            return;
        }

        if ($previousImage === $this->record->image) {
            return;
        }

        if (! $this->isManagedProductImagePath($previousImage)) {
            return;
        }

        Storage::disk('public')->delete($previousImage);
    }

    /**
     * Безопасен ли путь для удаления: относительный, внутри products/.
     *
     * Защита от выхода за пределы каталога товаров: в БД может попасть
     * только путь вида «products/<имя>», но проверяем всё равно — удалять
     * файл вправе лишь такой относительный путь публичного диска.
     */
    private function isManagedProductImagePath(string $path): bool
    {
        return str_starts_with($path, 'products/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\')
            && ! str_starts_with($path, '/');
    }

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
