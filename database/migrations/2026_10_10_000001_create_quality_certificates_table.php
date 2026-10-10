<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Таблица quality_certificates
|--------------------------------------------------------------------------
|
| Документы о качестве, которые владелец загружает сам из админки и которые
| публикуются на странице «Качество» (/quality). Это изображения (скан
| сертификата) или PDF — оба типа принимает форма.
|
| title — подпись документа, которую видит посетитель («Сертификат
| соответствия», «Декларация о соответствии» и т.п.). Текст задаёт владелец,
| а не код: выдумывать названия сертификатов нельзя.
|
| file — относительный путь на облачном диске, например
| certificates/cld-<ulid>.pdf. Публичный адрес строит модель
| QualityCertificate::fileUrl(), поэтому в базе нет ни URL, ни абсолютных
| путей. Колонка nullable по той же причине, что products.image: запись без
| файла не роняет страницу, а просто не выводится.
|
| is_active и sort_order позволяют скрыть документ и задать его место в
| списке, не удаляя запись.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_certificates', function (Blueprint $table): void {
            $table->id();

            $table->string('title');
            $table->string('file')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_certificates');
    }
};
