<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Таблица products
|--------------------------------------------------------------------------
|
| Товары каталога. Поля соответствуют тем, которые компонент ProductCard уже
| умеет выводить, поэтому перенос данных из config/catalog.php в базу не
| потребует правки Blade.
|
| УНИКАЛЬНОСТЬ slug
|
| Публичный адрес товара планируется вида /products/{категория}/{товар}, то
| есть запись однозначно задаётся парой «категория + товар». Поэтому slug
| товара уникален только ВНУТРИ своей категории, а не глобально:
| products(['product_category_id', 'slug'])->unique()
|
| Глобальная уникальность была бы лишним ограничением: одинаковые
| осмысленные слаги («mix», «other», « assortment») законно встречаются в
| разных категориях, и глобальный индекс заставил бы разводить их
| искусственными суффиксами. Составной индекс, наоборот, гарантирует, что
| два разных товара одной категории не займут один адрес.
|
| Типы полей
|
| weight и shelf_life — string, а не число: значения заказчика приходят
| текстом («от 1 кг», «5 суток», «12 месяцев»), и числовой тип заставил бы
| выбирать между потерей данных и разбором единиц.
|
| packaging — text, а не string: это свободное описание варианта упаковки
| («Целый, охлаждённый, в вакуумной упаковке»), а не короткий код.
| В SQLite длина строки не проверяется, но в MySQL и PostgreSQL string —
| это varchar(255) с жёстким ограничением, и длинный текст молча обрезался
| бы или вызывал ошибку. text снимает вопрос длины и одинаково ведёт себя
| во всех СУБД.
|
| ПОВОДЕНИЕ ПРИ УДАЛЕНИИ КАТЕГОРИИ
|
| restrictOnDelete: удалить категорию, у которой есть товары, нельзя.
| Товар без категории не имеет смысла, но каскадное удаление уничтожило бы
| весь ассортимент раздела одной командой без предупреждения. Ограничение
| заставляет сначала осознанно разобрать товары — перенести их в другую
| категорию или удалить по одной. Данные не пропадают молча.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();

            // По умолчанию constrained() выводит имя внешнего ключа из имени
            // колонки: product_category_id -> product_categories.id.
            $table->foreignId('product_category_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('name');

            // slug уникален в пределах категории, а не глобально: см. шапку.
            $table->string('slug');
            $table->unique(['product_category_id', 'slug']);

            $table->text('short_description')->nullable();

            // Относительный путь из public/images, как в config/catalog.php.
            $table->string('image')->nullable();

            $table->string('weight')->nullable();
            $table->text('packaging')->nullable();
            $table->text('storage')->nullable();
            $table->string('shelf_life')->nullable();
            $table->text('additional_info')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
