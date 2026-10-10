<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Категория продукции верхнего уровня: /products/{slug}.
 *
 * Модель отвечает и за структуру хранения, и за данные страницы: контроллер
 * выбирает категорию по slug среди активных и отдаёт её вместе с активными
 * товарами. Списка товаров в модели намеренно нет: оформление раздела
 * (hero, заголовки, тексты, CTA) остаётся в config/catalog.php, а состав
 * ассортимента и его порядок приходят из базы.
 *
 * SEO-полей и hero в модели нет не по недосмотру, а потому что такими
 * данными владеет конфиг: незаполненных полей в схеме нет.
 *
 * $guarded = [] здесь не используется намеренно: пустой список разрешил бы
 * массовое присваивание любых колонок, включая id и timestamps, то есть
 * данные могли бы прийти из формы или импорта неожиданного вида. Явный
 * #[Fillable] перечисляет ровно те поля, которыми управляет каталог.
 */
#[Fillable(['name', 'description', 'slug', 'is_active', 'sort_order'])]
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
     * ORDER BY. Страницу категории сортирует контроллер, а тест
     * проверяет, что порядок из БД доезжает до вывода без перестановок.
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
