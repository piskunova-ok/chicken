<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * Начальное наполнение каталога: категории и товары.
 *
 * ЧТО ЗДЕСЬ ЕСТЬ, А ЧТО НЕТ
 *
 * Позиции мяса кур и их описания утверждены заказчиком, поэтому они
 * перенесены дословно. Описания НЕ улучшались, НЕ дописывались и НЕ
 * унифицировались: тест сверяет их с config/catalog.php, чтобы правка
 * текста здесь или там ломала сборку, а не проходила незаметно.
 *
 * Позиции яиц — «Вариант продукции 01/02/03» — это временные
 * демонстрационные заглушки из config/catalog.php, а не ассортимент
 * заказчика. Реальные категории яиц (C0/C1/C2), масса, упаковка и сроки
 * годности не подтверждены, поэтому их НЕ выдумываем: slug нейтральные
 * (variant-01…03), а все характеристики остаются NULL. Отдельной колонки
 * «временная позиция» в схеме нет и она не добавляется: подтверждённых
 * данных для такого признака нет, и молчаливая колонка-флаг скорее
 * скроет мусор, чем защитит от него.
 *
 * Вес, упаковка, условия и срок хранения, дополнительная информация и
 * фотографии не заполняются: заказчиком они не подтверждены, а правдоподобные
 * значения выглядели бы как настоящие данные.
 *
 * ПОЧЕМУ ТЕКСТЫ ПРОПИСАНЫ ЗДЕСЬ, А НЕ ЧИТАЮТСЯ ИЗ config/catalog.php
 *
 * Читать config на этапе наполнения было бы соблазнительно: гарантия
 * «тексты совпадают» далась бы сама. Но seeder — это снимок состояния,
 * а не вид на источник. Публичные страницы перестанут читать
 * config/catalog.php на следующем этапе, файл перестанет быть
 * источником правды, и seeder, зависящий от него, однажды заполнит базу
 * тем, что уже не является подтверждённым ассортиментом. Поэтому тексты
 * зафиксированы здесь, а сверка с config вынесена в тест — там, где
 * расхождение и должно быть видно.
 *
 * ЗДЕСЬ МОДЕЛИ НЕ ИСПОЛЬЗУЮТСЯ САЙТОМ
 *
 * Seeder наполняет базу, но публичные страницы по-прежнему работают на
 * config/catalog.php. Переключение страниц на модели — отдельный этап.
 */
class ProductCatalogSeeder extends Seeder
{
    /**
     * Порядок категорий в каталоге: 1 — яйца, 2 — мясо.
     *
     * @var array<string, array{name: string, sort_order: int}>
     */
    private const CATEGORIES = [
        'eggs' => ['name' => 'Яйца кур', 'sort_order' => 1],
        'chicken' => ['name' => 'Мясо кур', 'sort_order' => 2],
    ];

    /**
     * Товары категории «Мясо кур», sort_order с 1 по порядку config/catalog.php.
     *
     * @var list<array{name: string, slug: string, short_description: string}>
     */
    private const CHICKEN_PRODUCTS = [
        [
            'name' => 'Тушка курицы',
            'slug' => 'tushka-kuritsy',
            'short_description' => 'Целая тушка — универсальный вариант для запекания, приготовления бульонов, первых и вторых блюд.',
        ],
        [
            'name' => 'Окорочка',
            'slug' => 'okorochka',
            'short_description' => 'Для запекания, тушения, жарки и приготовления на гриле.',
        ],
        [
            'name' => 'Куриные бёдра',
            'slug' => 'kurinye-bedra',
            'short_description' => 'Части курицы для горячих блюд, запекания и тушения.',
        ],
        [
            'name' => 'Куриные сердца',
            'slug' => 'kurinye-serdtsa',
            'short_description' => 'Субпродукт для горячих блюд, тушения, салатов и закусок.',
        ],
        [
            'name' => 'Куриная печень',
            'slug' => 'kurinaya-pechen',
            'short_description' => 'Продукт для паштетов, горячих блюд, закусок и домашней кухни.',
        ],
        [
            'name' => 'Суповые наборы',
            'slug' => 'supovye-nabory',
            'short_description' => 'Вариант для приготовления бульонов, супов и других первых блюд.',
        ],
        [
            'name' => 'Другие продукты',
            'slug' => 'drugie-produkty',
            'short_description' => 'Ассортимент куриной продукции дополняется другими позициями. Актуальное наличие и характеристики можно уточнить у наших специалистов.',
        ],
    ];

    /**
     * Временные позиции категории «Яйца кур».
     *
     * Это НЕ ассортимент заказчика, а перенос демонстрационных карточек из
     * config/catalog.php, чтобы структуру каталога можно было проверить на
     * данных. Коротких описаний в конфиге у этих позиций нет, поэтому
     * short_description остаётся NULL, а не заполняется выдумкой.
     *
     * @var list<array{name: string, slug: string}>
     */
    private const EGGS_PRODUCTS = [
        ['name' => 'Вариант продукции 01', 'slug' => 'variant-01'],
        ['name' => 'Вариант продукции 02', 'slug' => 'variant-02'],
        ['name' => 'Вариант продукции 03', 'slug' => 'variant-03'],
    ];

    /**
     * Поля, которые заказчиком не подтверждены.
     *
     * Перечислены явно, а не «оставлены на волю случая»: так видно, что
     * пустота здесь намеренная, а не забытая.
     *
     * @var list<string>
     */
    private const UNCONFIRMED_FIELDS = [
        'image',
        'weight',
        'packaging',
        'storage',
        'shelf_life',
        'additional_info',
    ];

    public function run(): void
    {
        $categories = [];

        foreach (self::CATEGORIES as $slug => $attributes) {
            $categories[$slug] = $this->upsertCategory($slug, $attributes);
        }

        $this->upsertProducts($categories['chicken'], self::CHICKEN_PRODUCTS);
        $this->upsertProducts($categories['eggs'], self::EGGS_PRODUCTS);
    }

    /**
     * Категория по slug: создаётся, если её нет, иначе приводится к виду,
     * объявленному в seeder.
     *
     * Ключ поиска — сам slug, потому что он и есть глобальный уникальный
     * индекс таблицы. Совпадение ключа с индексом означает, что запись
     * опознаётся тем же способом, каким база запрещает дубликат.
     *
     * @param  array{name: string, sort_order: int}  $attributes
     */
    private function upsertCategory(string $slug, array $attributes): ProductCategory
    {
        return ProductCategory::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $attributes['name'],
                'is_active' => true,
                'sort_order' => $attributes['sort_order'],
            ],
        );
    }

    /**
     * Товары категории, по одному на каждый элемент $products.
     *
     * Ключ поиска — пара «категория + slug», то есть ровно тот составной
     * уникальный индекс, который задан миграцией. Повторный запуск seeder
     * обновляет существующие строки и не создаёт новых.
     *
     * @param  list<array{name: string, slug: string, short_description?: string}>  $products
     */
    private function upsertProducts(ProductCategory $category, array $products): void
    {
        foreach ($products as $position => $product) {
            // sort_order начинается с 1 в пределах категории: нулевой
            // зарезервирован миграцией как «значение по умолчанию», то есть
            // как «позиция не задана», и не должен занимать первую позицию.
            $attributes = [
                'name' => $product['name'],
                'short_description' => $product['short_description'] ?? null,
                'is_active' => true,
                'sort_order' => $position + 1,
            ];

            foreach (self::UNCONFIRMED_FIELDS as $field) {
                $attributes[$field] = null;
            }

            Product::updateOrCreate(
                [
                    'product_category_id' => $category->id,
                    'slug' => $product['slug'],
                ],
                $attributes,
            );
        }
    }
}
