<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Товар каталога.
 *
 * Набор полей совпадает с тем, который компонент ProductCard уже умеет
 * выводить, поэтому перенос данных из config/catalog.php в базу не потребует
 * правки представлений.
 *
 * Модель используется страницами категорий: список карточек на
 * /products/{slug} строится из этих записей. Оформление страницы при этом
 * по-прежнему приходит из config/catalog.php.
 *
 * $guarded = [] намеренно не используется: явно перечисленные #[Fillable]
 * защищают служебные колонки (id, created_at, updated_at) от массового
 * присваивания и делают контракт модели явным.
 */
#[Fillable([
    'product_category_id',
    'name',
    'slug',
    'short_description',
    'image',
    'weight',
    'packaging',
    'storage',
    'shelf_life',
    'additional_info',
    'is_active',
    'sort_order',
])]
class Product extends Model
{
    /**
     * Файл, загруженный админкой в Cloudinary, помечается префиксом cld-.
     *
     * Раньше загрузки админки лежали на local-диске с именем
     * «products/<ulid>.<ext>», и путь в базе был неотличим от записи нового
     * хранилища по форме. Чтобы адресация осталась детерминированной (по
     * виду пути), новые загрузки из Cloudinary получают имя
     * «products/cld-<ulid>.<ext>» — по этому префиксу модель выбирает диск
     * и модель, и страницы, и уборка файлов.
     */
    public const CLOUDINARY_PREFIX = 'cld-';

    /**
     * Категория, которой принадлежит товар.
     *
     * Имя внешнего ключа указано явно и это не перестраховка. У hasMany
     * Laravel выводит внешний ключ из имени родительской модели, поэтому
     * ProductCategory::products() сам получает product_category_id. У
     * belongsTo ключ выводится из ИМЕНИ МЕТОДА: для метода category() это
     * category_id — колонки, которой в таблице нет. Без явного указания
     * связь молча возвращала бы null вместо категории.
     *
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * Характеристики товара в том виде, в котором их понимает карточка.
     *
     * Ключи соответствуют подписям в App\View\Components\ProductCard.
     * Значения пока равны null: заказчиком не подтверждены ни вес, ни
     * упаковка, ни срок годности, ни условия хранения. Компонент не
     * выводит характеристику, если её значение пустое, поэтому страница
     * сейчас выглядит ровно так же, как до перехода на базу, а с
     * появлением подтверждённых данных строки появятся сами — без
     * правки шаблона.
     *
     * Сопоставление живёт в модели, а не в Blade: именно модель знает
     * названия своих колонок. Ключи, которых в схеме нет (количество,
     * пищевая ценция), сюда не добавлены — подставить данные из
     * несуществующих колонок нельзя.
     *
     * @return array<string, string|null>
     */
    public function cardSpecs(): array
    {
        return [
            'packaging' => $this->packaging,
            'weight' => $this->weight,
            'shelf_life' => $this->shelf_life,
            'storage' => $this->storage,
        ];
    }

    /**
     * Адрес фотографии товара для публичной страницы.
     *
     * ПЕРЕХОДНЫЙ ПЕРИОД: ТРИ ТИПА ПУТЕЙ
     *
     * Сейчас в базе встречаются пути трёх типов, и это не ошибка данных:
     *
     *  - images/products/<файл>.jpg — файл лежит прямо в public/,
     *    отслеживается Git и попадает на сервер деплоем. Ссылка строится
     *    через asset(): от корня сайта, чтобы на /products/eggs путь не
     *    превратился в /products/images/…
     *
     *  - products/<ulid>.<ext> — путь записи старой загрузки админки на
     *    local-диск. Файл лежит на диске storage/app/public и отдаётся
     *    ссылкой публичного диска: Storage::disk('public')->url().
     *
     *  - products/cld-<ulid>.<ext> — путь загрузки из Cloudinary (текущая
     *    админка). Префикс cld- позволяет отличить облачные файлы от
     *    локальных по одному виду пути.
     *
     * Почему нужна совместимость, а не только один путь: рабочая база ещё
     * содержит старые пути, а перезапуск seeder на ней запрещён. Без этих
     * веток карточки на сайте были бы битыми до отдельной чистки.
     *
     * Ветка по типу пути, а не «попробовать asset(), иначе Storage»:
     * решение принимает вид значения, а не наличие файла, поэтому рендер
     * детерминирован и не зависит от того, что лежит на диске в данный
     * момент. Тип пути — единственное, что известно о нём надёжно.
     *
     * Для облачных путей есть второй якорь: если Cloudinary ещё не настроен,
     * фото просто не могло туда попасть, поэтому ссылка строится через
     * публичный диск (там тоже нет файла, но страница не падает) — это
     * деградация до настройки переменных окружения, а не штатное поведение.
     *
     * Пустое значение возвращает null, а не «/»: пустой src хуже
     * отсутствия, потому что браузер запросил бы страницу как картинку.
     * Компонент карточки в этом случае показывает заглушку.
     */
    public function imageUrl(): ?string
    {
        $path = $this->image;

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        // Облачная загрузка админки: только products/cld-<файл>.
        if (self::isCloudinaryImagePath($path)) {
            return self::cloudinaryConfigured()
                ? Storage::disk('cloudinary')->url($path)
                : Storage::disk('public')->url($path);
        }

        // Старый управляемый путь загрузок админки: только products/<файл>.
        // Проверка строгая: чужой путь не должен молча превратиться в
        // ссылку публичного диска.
        if (self::isManagedProductImagePath($path)) {
            return Storage::disk('public')->url($path);
        }

        // Новый путь внутри public/.
        return asset($path);
    }

    /**
     * Управляемый пути загрузок: относительный и внутри products/.
     */
    public static function isManagedProductImagePath(string $path): bool
    {
        return str_starts_with($path, 'products/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\')
            && ! str_starts_with($path, '/');
    }

    /**
     * Путь записи Cloudinary-загрузки админки: products/cld-<файл>.
     *
     * Строже, чем isManagedProductImagePath(): облачные файлы имеют
     * префикс cld- и, в отличие от local-загрузок, удаляются через API
     * Cloudinary, а ссылка строится с CDN. Проверка по префиксу не даёт
     * перепутать два диска.
     */
    public static function isCloudinaryImagePath(string $path): bool
    {
        return self::isManagedProductImagePath($path)
            && str_starts_with($path, 'products/'.self::CLOUDINARY_PREFIX);
    }

    /**
     * Имя диска для пути: Cloudinary для cld-файлов, public для остальных.
     */
    public static function diskNameForPath(string $path): string
    {
        return self::isCloudinaryImagePath($path) ? 'cloudinary' : 'public';
    }

    /**
     * Настроен ли Cloudinary (задан ли cloud_name в конфиге диска).
     */
    public static function cloudinaryConfigured(): bool
    {
        $cloudName = config('filesystems.disks.cloudinary.cloud_name');

        return is_string($cloudName) && trim($cloudName) !== '';
    }

    /**
     * Приведение типов атрибутов.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Зарегистрированные события модели.
     */
    protected static function booted(): void
    {
        static::deleting(function (Product $product): void {
            $path = $product->image;

            if (! is_string($path) || ! self::isManagedProductImagePath($path)) {
                return;
            }

            try {
                Storage::disk(self::diskNameForPath($path))->delete($path);
            } catch (Throwable) {
                // Запись удаляется в любом случае: файл мог не попасть в
                // хранилище (Cloudinary ещё не настроен), а удаление записи
                // не должно зависеть от недоступности CDN.
            }
        });
    }
}
