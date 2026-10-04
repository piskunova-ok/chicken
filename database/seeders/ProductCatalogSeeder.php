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
 * унифицировались: тест хранит их эталонным снимком, поэтому правка
 * текста здесь или там ломала бы сборку, а не проходила незаметно.
 *
 * Позиции яиц — это подтверждённые категории C0/C1/C2. Раньше вместо них
 * стояли демонстрационные заглушки «Вариант продукции 01/02/03»; они удалены,
 * потому что ассортимент заказчика теперь известен, а заглушки только занимали
 * место настоящих товаров. Масса, упаковка и сроки годности по-прежнему не
 * подтверждены, поэтому их НЕ выдумываем: все характеристики остаются NULL.
 *
 * Вес, упаковка, условия и срок хранения и дополнительная информация не
 * заполняются: заказчиком они не подтверждены, а правдоподобные значения
 * выглядели бы как настоящие данные.
 *
 * ФОТОГРАФИИ — ЧАСТЬ КАТАЛОГА, А НЕ ЗАГЛУШКА
 *
 * Фотографии товаров подтверждены, поэтому в image пишется путь внутри
 * public/: «images/products/<slug>.jpg». Это НЕ путь на диске Filament и не
 * загрузка через админку: файлы лежат в public/images/products, отслеживаются
 * Git и попадают на сервер обычным деплоем. Страница строит ссылку через
 * asset(), поэтому каталог воспроизводится из репозитория целиком — вместе с
 * фото, без ручной выкладки на диск.
 *
 * Путь хранится относительным («images/products/…»), а не абсолютным и не
 * URL: так он переживает смену домена и не врёт о способе отдачи файла.
 * Товар без фотографии (Другие продукты) получает NULL, и страница показывает
 * заглушку.
 *
 * ПОЧЕМУ ТЕКСТЫ ПРОПИСАНЫ ЗДЕСЬ, А НЕ ЧИТАЮТСЯ ИЗ config/catalog.php
 *
 * Читать config было бы соблазнительно: гарантия «тексты совпадают»
 * далась бы сама. Но seeder — это снимок состояния, а не вид на источник.
 * Публичные страницы теперь читают модели, config/catalog.php хранит только
 * оформление разделов, а его списки товаров удалены: список ассортимента
 * должен иметь ровно один источник истины. Поэтому тексты зафиксированы
 * здесь, а сверка с эталоном вынесена в тест — там, где расхождение и
 * должно быть видно.
 *
 * СТРАНИЦЫ ЧИТАЮТ БАЗУ
 *
 * Seeder наполняет базу, а ProductCategoryController выбирает из неё
 * категорию и активные товары. Повторный запуск идемпотентен: значения
 * обновляются по slug, поэтому база возвращается в то же состояние, даже
 * если её правили вручную.
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
     * Товары категории «Мясо кур», sort_order с 1 по порядку, который был
     * задан в config/catalog.php до перехода страниц на модели.
     *
     * У каждого товара, кроме последнего, есть подтверждённая фотография в
     * public/images/products. У «Другие продукты» её нет: позиция обобщённая,
     * и выдуманное фото было бы ложью, поэтому image остаётся NULL.
     *
     * @var list<array{name: string, slug: string, short_description: string, image: string|null}>
     */
    private const CHICKEN_PRODUCTS = [
        [
            'name' => 'Тушка курицы',
            'slug' => 'tushka-kuritsy',
            'short_description' => 'Целая тушка — универсальный вариант для запекания, приготовления бульонов, первых и вторых блюд.',
            'image' => 'images/products/tushka-kuritsy.jpg',
        ],
        [
            'name' => 'Окорочка',
            'slug' => 'okorochka',
            'short_description' => 'Для запекания, тушения, жарки и приготовления на гриле.',
            'image' => 'images/products/okorochka.jpg',
        ],
        [
            'name' => 'Куриные бёдра',
            'slug' => 'kurinye-bedra',
            'short_description' => 'Части курицы для горячих блюд, запекания и тушения.',
            'image' => 'images/products/kurinye-bedra.jpg',
        ],
        [
            'name' => 'Куриные сердца',
            'slug' => 'kurinye-serdtsa',
            'short_description' => 'Субпродукт для горячих блюд, тушения, салатов и закусок.',
            'image' => 'images/products/kurinye-serdtsa.jpg',
        ],
        [
            'name' => 'Куриная печень',
            'slug' => 'kurinaya-pechen',
            'short_description' => 'Продукт для паштетов, горячих блюд, закусок и домашней кухни.',
            'image' => 'images/products/kurinaya-pechen.jpg',
        ],
        [
            'name' => 'Суповые наборы',
            'slug' => 'supovye-nabory',
            'short_description' => 'Вариант для приготовления бульонов, супов и других первых блюд.',
            'image' => 'images/products/supovye-nabory.jpg',
        ],
        [
            'name' => 'Другие продукты',
            'slug' => 'drugie-produkty',
            'short_description' => 'Ассортимент куриной продукции дополняется другими позициями. Актуальное наличие и характеристики можно уточнить у наших специалистов.',
            'image' => null,
        ],
    ];

    /**
     * Товары категории «Яйца кур»: подтверждённые категории C0, C1 и C2.
     *
     * Раньше здесь стояли временные «Вариант продукции 01/02/03» с
     * нейтральными slug. Заказчик подтвердил настоящие категории, поэтому
     * заглушки удалены, а slug совпадают с именами файлов фотографий —
     * egg-c0.jpg, egg-c1.jpg, egg-c2.jpg.
     *
     * У яиц нет подтверждённых коротких описаний, поэтому short_description
     * остаётся NULL, а не заполняется выдумкой. Характеристики (масса,
     * упаковка, срок годности) не подтверждены и тоже остаются NULL.
     *
     * @var list<array{name: string, slug: string, image: string}>
     */
    private const EGGS_PRODUCTS = [
        ['name' => 'Яйцо куриное C0', 'slug' => 'egg-c0', 'image' => 'images/products/egg-c0.jpg'],
        ['name' => 'Яйцо куриное C1', 'slug' => 'egg-c1', 'image' => 'images/products/egg-c1.jpg'],
        ['name' => 'Яйцо куриное C2', 'slug' => 'egg-c2', 'image' => 'images/products/egg-c2.jpg'],
    ];

    /**
     * Поля, которые заказчиком не подтверждены.
     *
     * Перечислены явно, а не «оставлены на волю случая»: так видно, что
     * пустота здесь намеренная, а не забытая.
     *
     * image здесь НЕТ намеренно: фотографии подтверждены и приходят из
     * public/images/products. Пока image был в этом списке, каждый запуск
     * seeder обнулял его, и каталог терял все фотографии.
     *
     * @var list<string>
     */
    private const UNCONFIRMED_FIELDS = [
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
     * @param  list<array{name: string, slug: string, short_description?: string, image?: string|null}>  $products
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
                // Файл каталога лежит в public/images/products, поэтому в базе
                // хранится путь относительно public/ — его превращает в ссылку
                // asset(). image отсутствует у позиции без фотографии, и ?? null
                // оставляет её без картинки, а не подставляет чужой путь.
                'image' => $product['image'] ?? null,
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
