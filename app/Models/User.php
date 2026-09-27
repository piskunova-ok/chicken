<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Определяет, может ли пользователь открыть панель.
     *
     * Реализация интерфейса FilamentUser обязательна: без неё Filament
     * считает, что панель открыта любому, кто просто прошёл аутентификацию.
     * В базе есть служебная учётная запись из DatabaseSeeder, и при таком
     * поведении она стала бы администратором сайта.
     *
     * Здесь нет ролей и прав — только проверка адреса по списку из
     * config/admin.php. Список берётся из переменной окружения и по
     * умолчанию пуст, то есть панель закрыта, пока её не открыли явно.
     *
     * Сравнение регистронезависимое, потому что адрес почты по стандарту
     * нечувствителен к регистру, а вводит его человек.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        /** @var list<string> $allowed */
        $allowed = config('admin.panel_access_emails', []);

        $email = mb_strtolower(trim($this->email));

        foreach ($allowed as $candidate) {
            if (mb_strtolower(trim($candidate)) === $email) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
