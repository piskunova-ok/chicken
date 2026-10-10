<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Таблица site_settings
|--------------------------------------------------------------------------
|
| Настройки сайта, которые владелец меняет сам из админки, без Git и кода:
| название компании, краткое описание, контакты, телефон, режим работы и
| ссылки на соцсети. Это «слои» поверх config/site.php: пока ячейка не
| заполнена, сайт берёт значение из конфига (переменные окружения на
| Render), после сохранения формы — из базы.
|
| В таблице всегда ровно одна строка с id = 1. Она создаётся (insertOrIgnore,
| чтобы миграция оставалась идемпотентной) со всеми NULL-значениями: никаких
| копий конфига, только явный маркер «настройка не менялась».
|
| Каждое поле nullable: пустая ячейка означает «использовать значение по
| умолчанию из config/site.php». phone_secondary, telegram, whatsapp и vk
| зарезервированы под будущее разделение контактов, но в конфиге у них
| нет запасного значения — их пустота означает просто отсутствие.
|
*/

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();

            $table->string('company_name')->nullable();
            $table->text('short_description')->nullable();

            $table->string('phone')->nullable();
            $table->string('phone_secondary')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('schedule')->nullable();

            $table->string('telegram')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('vk')->nullable();

            $table->timestamps();
        });

        DB::table('site_settings')->insertOrIgnore([
            'id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};