<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
 *     если запись падает, здесь же удаляется НОВЫЙ файл, который dehydrate
 *     уже положил на диск, а исходное исключение пробрасывается дальше:
 *     иначе новый файл остался бы без ссылки в БД;
 *  4. afterSave() — ЗДЕСЬ удаляется старый файл, только если он существует,
 *     отличается от нового пути и лежит внутри public disk/products.
 *
 * Поэтому: новый файл всегда сохранён и записан в БД до удаления старого,
 * при ошибке validation ничего не удаляется, при ошибке записи удаляется
 * только неиспользуемый новый файл, а удалить можно только файл этого
 * товара в безопасном каталоге public disk. После Create ничего
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
     * Запись товара с уборкой НОВОГО файла, если запись не удалась.
     *
     * Окно утечки: FileUpload сохраняет новый файл на публичный диск
     * ещё при dehydrate формы, то есть ДО этого метода (EditRecord::save,
     * вендор: getState() на строке 168, handleRecordUpdate() на 176).
     * Если запись падает, транзакция откатывается, products.image
     * сохраняет старый путь, а новый файл остаётся на диске без ссылок.
     *
     * Здесь и только здесь это окно закрывается: удаляем новый файл и
     * пробрасываем исходное исключение дальше. Нормальный успешный путь
     * не изменён — метод лишь оборачивает штатный update.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $previousImage = Product::query()
            ->whereKey($record->getKey())
            ->value('image');

        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (Throwable $exception) {
            $this->deleteUncommittedImage($data, $previousImage);

            throw $exception;
        }
    }

    /**
     * Удаляет новый файл, который не был записан в БД из-за сбоя.
     *
     * Условия удаления, все обязательны:
     *  - путь стал новым значением image в данных формы;
     *  - путь управляемый: products/ без выхода за каталог;
     *  - путь отличается от сохранённого в БД старого изображения;
     *  - на путь не ссылается ни одна запись products.image — файл
     *    может быть общим, удалять его нельзя.
     *
     * Ошибка удаления не должна скрывать исходную ошибку записи, поэтому
     * проглатывается.
     *
     * @param array<string, mixed> $data
     */
    private function deleteUncommittedImage(array $data, ?string $previousImage): void
    {
        $newImage = $data['image'] ?? null;

        if (! is_string($newImage) || $newImage === '') {
            return;
        }

        if ($newImage === $previousImage) {
            return;
        }

        if (! $this->isManagedProductImagePath($newImage)) {
            return;
        }

        if (Product::query()->where('image', $newImage)->exists()) {
            return;
        }

        try {
            Storage::disk('public')->delete($newImage);
        } catch (Throwable) {
            // Исходная ошибка записи важнее ошибки уборки: её пробрасываем.
        }
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
