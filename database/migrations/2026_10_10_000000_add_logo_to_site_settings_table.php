<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Колонка site_settings.logo
|--------------------------------------------------------------------------
|
| Логотип компании, который владелец загружает сам из админки: шапка и
| подвал показывают картинку вместо текстового названия, пока файл задан,
| и возвращаются к названию, как только его убирают.
|
| Колонка nullable: логотип необязателен. Пустая ячейка означает «показывать
| название компании» (значение по умолчанию), а не «логотип потерян».
|
| В ячейке хранится только относительный путь на облачном диске, например
| brand/cld-<ulid>.<ext>, — ровно так же, как в products.image. Публичный
| адрес строит Settings::logoUrl(), поэтому в базе нет ни URL, ни абсолютных
| путей.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('logo')->nullable()->after('short_description');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn('logo');
        });
    }
};
