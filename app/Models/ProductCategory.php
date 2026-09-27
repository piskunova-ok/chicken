<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Категория продукции верхнего уровня: /products/{slug}.
 *
 * Модель описывает только структуру хранения. Категории, товары и тексты
 * появятся на следующих этапах, поэтому модель намеренно не содержит
 * ни SEO-полей, ни hero, ни изображений: незаполненных полей в схеме нет.
 *
 * $guarded = [] здесь не используется намеренно: пустой список разрешил бы
 * массовое присваивание любых колонок, включая id и timestamps, то есть
 * данные могли бы прийти из формы или импорта неожиданного вида. Явный
 * #[Fillable] перечисляет ровно те поля, которыми управляет каталог.
 */
#[Fillable(['name', 'slug', 'is_active', 'sort_order'])]
class ProductCategory extends Model
{
    /**
     * Товары категории.
     *
     * Имя внешнего ключа не указано явно: у hasMany Laravel выводит его из
     * имени родительской модели, то есть из ProductCategory — получается
     * product_category_id, ровно то же имя, что и в миграции. Явное
     * перечисление здесь было бы дублированием, которое пришлось бы
     * синхронизировать вручную. Обратная связь в Product::category() указана
     * явно — там Laravel выводит ключ из имени метода, а не модели.
     *
     * Порядок вывода не задан: сортировка по sort_order — решение уровня
     * запроса, а не отношения, иначе любая выборка молча приобрела бы
     * ORDER BY. Сейчас SQL возвращает записи в произвольном порядке.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Приведение типов атрибутов.
     *
     * SQLite не различает boolean и integer, поэтому без cast'ов
     * is_active вернул бы 1 вместо true, а sort_order — строку. На уровне
     * модели значения приводятся к ожидаемому типу независимо от СУБД.
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
