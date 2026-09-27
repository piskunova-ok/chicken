<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call(ProductCatalogSeeder::class);

        // Тестовый пользователь из стандартной заготовки Laravel. Проверка
        // существования обязательна: users.email уникален, поэтому повторный
        // `php artisan db:seed` падал бы на вставке второй копии с тем же
        // адресом. Именно из-за этой строки нельзя было повторно запустить
        // сидер, хотя сам каталог к повторным запускам готов.
        //
        // exists() вместо firstOrCreate: пароль создаёт фабрика, а
        // firstOrCreate([...], ['name' => ...]) его бы не задал — колонка
        // password обязательна.
        if (! User::where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }
    }
}
