<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\ProductCatalogSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Фундамент админ-панели: доступ по списку адресов и независимость
 * публичного сайта от панели.
 *
 * Проверки опираются на коды ответа, редиректы и состояние БД, а не на
 * внутреннюю разметку Filament. Разметка панели меняется от версии к
 * версии, и тест, привязанный к ней, ломался бы без всякой смены
 * поведения: красная строка ничего не сказала бы о доступе.
 */
class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Адрес, которому вход в панель открыт.
     */
    private const ALLOWED_EMAIL = 'allowed@local.test';

    /**
     * Адрес, которому вход закрыт, хотя аутентификация пройдена.
     */
    private const DENIED_EMAIL = 'denied@local.test';

    protected function setUp(): void
    {
        parent::setUp();

        // Список доступа задаётся прямо здесь, а не читается из .env:
        // тест не должен зависеть от того, что настроено на машине.
        config()->set('admin.panel_access_emails', [self::ALLOWED_EMAIL]);

        $this->seed(ProductCatalogSeeder::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Публичный сайт
    |--------------------------------------------------------------------------
    */

    public function test_the_home_page_is_open_to_guests(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_the_eggs_page_is_open_to_guests(): void
    {
        $this->get('/products/eggs')->assertOk();
    }

    public function test_the_chicken_page_is_open_to_guests(): void
    {
        $this->get('/products/chicken')->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Гость
    |--------------------------------------------------------------------------
    */

    public function test_a_guest_is_sent_from_the_panel_to_the_login_page(): void
    {
        $this->get('/admin')
            ->assertStatus(302)
            ->assertRedirectContains('/admin/login');
    }

    public function test_the_login_page_is_available_to_a_guest(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | Авторизованный пользователь
    |--------------------------------------------------------------------------
    */

    public function test_an_allowed_user_can_open_the_panel(): void
    {
        $user = User::factory()->create(['email' => self::ALLOWED_EMAIL]);

        $this->actingAs($user)->get('/admin')->assertOk();
    }

    public function test_an_authenticated_user_outside_the_allow_list_is_refused(): void
    {
        $user = User::factory()->create(['email' => self::DENIED_EMAIL]);

        // Не редирект, а именно отказ: пользователь аутентифицирован, и
        // молча перебросить его на страницу входа значило бы скрыть, что
        // вход удался, а прав всё равно нет.
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_the_allow_list_is_matched_regardless_of_case(): void
    {
        $user = User::factory()->create(['email' => 'ALLOWED@LOCAL.TEST']);

        $this->actingAs($user)->get('/admin')->assertOk();
    }

    public function test_removing_the_address_from_the_allow_list_closes_access(): void
    {
        $user = User::factory()->create(['email' => self::ALLOWED_EMAIL]);

        $this->actingAs($user)->get('/admin')->assertOk();

        // Проверка идёт по актуальному значению конфига, а не по тому,
        // что было прочитано при входе: список должен действовать сразу.
        config()->set('admin.panel_access_emails', []);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_the_panel_is_empty_by_default_so_access_closes_itself(): void
    {
        // Панель не должна открываться «по умолчанию», пока список пуст:
        // иначе служебная учётная запись стала бы администратором.
        config()->set('admin.panel_access_emails', []);

        $user = User::factory()->create(['email' => self::ALLOWED_EMAIL]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Сессия и выход
    |--------------------------------------------------------------------------
    */

    public function test_the_panel_stays_closed_once_the_session_is_gone(): void
    {
        $user = User::factory()->create(['email' => self::ALLOWED_EMAIL]);

        $this->actingAs($user)->get('/admin')->assertOk();

        // Выход сбрасывает аутентификацию и сессию. Проверяем именно это,
        // а не форму кнопки: форма — часть вёрстки Filament и может
        // измениться, а защита обязана остаться.
        $this->app['auth']->guard()->logout();
        $this->flushSession();

        $this->get('/admin')
            ->assertStatus(302)
            ->assertRedirectContains('/admin/login');
    }

    /*
    |--------------------------------------------------------------------------
    | Конфигурация панели
    |--------------------------------------------------------------------------
    */

    public function test_the_project_has_exactly_one_panel(): void
    {
        $this->assertCount(1, Filament::getPanels());
    }

    public function test_the_panel_lives_at_the_admin_path(): void
    {
        // id указан явно: вне запроса к панели «текущей» панели нет, и
        // getPanel() без аргумента вернул бы null вместо панели.
        $panel = Filament::getPanel('admin');

        $this->assertSame('admin', $panel->getId());
        $this->assertSame('admin', $panel->getPath());
    }

    public function test_there_is_no_public_registration(): void
    {
        // Публичной регистрации быть не должно: и маршрута, ни страницы.
        $this->assertNull(Route::getRoutes()->getByName('register'));
        $this->get('/register')->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Каталог
    |--------------------------------------------------------------------------
    */

    public function test_the_catalog_data_is_intact_after_installing_filament(): void
    {
        $this->assertSame(2, ProductCategory::count());
        $this->assertSame(10, Product::count());
    }
}
