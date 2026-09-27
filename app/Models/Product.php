<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
