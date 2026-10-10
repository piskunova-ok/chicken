<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Колонка product_categories.description
|--------------------------------------------------------------------------
|
| Описание категории, которым владелец делится с посетителями каталога:
| выводится в карточке категории на странице /products и как вводный абзац
| на странице /products/{slug}. Раньше описание жило только в config/catalog.php
| (для известных категорий eggs/chicken) либо отсутствовало вовсе.
|
| Колонка nullable: новая категория может не иметь описания, и страница
| спокойно обходится без него (карточка показывает только название и CTA).
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};