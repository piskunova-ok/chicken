<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Редактирование товара.
 *
 * Удаление разрешено: товар — обычная запись каталога, и владелец может
 * убрать его окончательно (само удаление плюс физическая уборка файла
 * изображения). Удаление открыто в трёх местах: действие DeleteAction на
 * странице, DeleteAction в таблице, canDelete()/canDeleteAny() в ресурсе.
 * Полное удаление с публичной страницы можно также сделать переключателем
 * is_active — запись и адрес сохраняются.
 *
 * ЖИЗНЕННЫЙ ЦИКЛ ФАЙЛА ИЗОБРАЖЕНИЯ — ОДИН МЕХАНИЗМ
 *
 * Физическое удаление старого изображения при замене и при очистке делает
 * пара хуков beforeSave()/afterSave() — и НИЧТО больше. deleteUploadedFileUsing
 * в форме намеренно не регистрируется, поэтому у файла нет двух конкурирующих
 * «удаляльщиков»: кнопка «удалить» в FileUpload только убирает путь из
 * состояния формы, а настоящей очисткой диска занимается страница.
 *
 * Диск выбирается по виду пути (Product::diskNameForPath): облачные файлы
 * «products/cld-…» удаляются через Cloudinary API, локальные «products/…» —
 * с публичного диска. Управляемые пути описаны в Product::isManagedProductImagePath.
 *
 * Порядок в Filament (EditRecord::save, см. вендор):
 *
 *  1. dehydrate формы → beforeStateDehydrated сохраняет НОВЫЙ загруженный
 *     файл на выбранный диск (имя «products/cld-<ulid>.<ext>»);
 *  2. validation — при ошибке сохранение прерывается, handleRecordUpdate
 *     и afterSave НЕ выполняются, старый файл и БД не трогаются;
 *  3. handleRecordUpdate() — запись получает новый путь (или NULL);
 *     если запись падает, здесь же удаляется НОВЫЙ файл, который dehydrate
 *     уже положил на диск, а исходное исключение пробрасывается дальше:
 *     иначе новый файл остался бы без ссылки в БД;
 *  4. afterSave() — ЗДЕСЬ удаляется старый файл, только если он существует,
 *     отличается от нового пути и лежит внутри управляемого products/.
 *
 * Поэтому: новый файл всегда сохранён и записан в БД до удаления старого,
 * при ошибке validation ничего не удаляется, при ошибке записи удаляется
 * только неиспользуемый новый файл, а удалить можно только файл этого
 * товара в безопасном каталоге. После Create ничего удалять не нужно:
 * старого файла у новой записи нет.
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
            Storage::disk(Product::diskNameForPath($newImage))->delete($newImage);
        } catch (Throwable) {
            // Исходная ошибка записи важнее ошибки уборки: её пробрасываем.
        }
    }

    /**
     * После успешной записи удаляем СТАРОЕ изображение с правильного диска.
     *
     * К этому моменту новый путь (или NULL) уже в БД: handleRecordUpdate()
     * выполнился. Файл удаляется только когда путь изменился и ведёт в
     * управляемый products-каталог. Диск — Cloudinary для «products/cld-…»,
     * публичный для остальных «products/…» (Product::diskNameForPath).
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

        /*
         * Диск 'cloudinary' настроен с throw => true (см. config/filesystems.php):
         * сбой загрузки НОВОГО файла обязан валить сохранение явно, а не
         * прятаться. Но уборка СТАРОГО файла — второстепенна: к этому
         * моменту запись уже успешно обновлена, и ошибка удаления (сеть,
         * временная недоступность Cloudinary API) не должна превращать
         * успешное сохранение в 500-ю. Поэтому удаление здесь best-effort,
         * как и в событии deleting модели Product.
         */
        try {
            Storage::disk(Product::diskNameForPath($previousImage))->delete($previousImage);
        } catch (Throwable) {
            // Успешное сохранение важнее неудачной уборки старого файла.
        }
    }

    /**
     * Безопасен ли путь для удаления: относительный, внутри products/.
     *
     * Защита от выхода за пределы каталога товаров: в БД может попасть
     * только путь вида «products/<имя>», но проверяем всё равно — удалять
     * файл вправе лишь такой относительный путь. Логика вынесена в модель,
     * чтобы страницы и события пользовались одним правилом.
     */
    private function isManagedProductImagePath(string $path): bool
    {
        return Product::isManagedProductImagePath($path);
    }

    /**
     * Действия вверху страницы редактирования: удаление записи вместе с
     * физической уборкой файла (событие deleting модели Product).
     *
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
