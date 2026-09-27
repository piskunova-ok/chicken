<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Таблица product_categories
|--------------------------------------------------------------------------
|
| Категории верхнего уровня: /products/{slug}. На этом этапе таблица хранит
| только то, что нужно для адреса и порядка вывода, — ни SEO, ни hero, ни
| изображений, ни описаний. Эти поля заказчик не подтвердил, а «на будущее»
| колонки без потребителя только усложняют схему.
|
| slug уникален глобально, потому что входит в публичный URL
| /products/{slug} и должен однозначно определять страницу.
|
| is_active и sort_order позволяют скрыть категорию и задать её место в
| каталоге, не удаляя запись и не ломая уже опубликованные ссылки.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table): void {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
