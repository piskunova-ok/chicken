<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Настройки сайта: страница панели и строка site_settings (id = 1).
 *
 * КАК ПРОВЕРЯЕТСЯ ФОРМА
 *
 * Форма — это Livewire-компонент самой страницы, а не обычный POST-маршрут:
 * у страницы панели нет POST-эндпоинта, сохранение идёт методом save()
 * Livewire-компонента. Поэтому сохранение и валидация проверяются через
 * Livewire::test(SiteSettings::class), как и формы ресурсов, — а не HTTP-POST
 * на адрес страницы (тот отвечает 405 Method Not Allowed).
 *
 * ДОСТУП В ПАНЕЛЬ
 *
 * Панель закрыта списком адресов (config/admin.php). Пользователь фабрики
 * получает случайный адрес и в список не входит, поэтому тестового
 * администратора создаём с разрешённым адресом, а список задаём здесь, а не
 * берём из .env: тест не должен зависеть от настроек машины.
 */
final class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Адрес, которому вход в панель открыт.
     */
    private const ALLOWED_EMAIL = 'admin@local.test';

    /**
     * Название-заглушка из config/site.php для проверки фолбэка.
     */
    private const CONFIG_COMPANY_NAME = 'Chicken Farm';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('admin.panel_access_emails', [self::ALLOWED_EMAIL]);

        // Значение фолбэка фиксируется явно: проверка «пусто в базе →
        // config/site.php» не должна зависеть от SITE_NAME в .env.
        config()->set('site.name', self::CONFIG_COMPANY_NAME);

        // Livewire-компоненту страницы нужна «текущая» панель.
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Settings::flush();
    }

    protected function tearDown(): void
    {
        Settings::flush();

        parent::tearDown();
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['email' => self::ALLOWED_EMAIL]);

        $this->actingAs($admin);

        return $admin;
    }

    public function test_the_site_settings_page_is_available_for_an_admin_user(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/site-settings')->assertOk();
    }

    public function test_the_site_settings_page_is_not_accessible_for_a_guest(): void
    {
        $this->get('/admin/site-settings')->assertRedirect('/admin/login');
    }

    public function test_the_form_shows_raw_values_from_the_database_on_mount(): void
    {
        SiteSetting::query()->updateOrCreate(['id' => 1], [
            'company_name' => 'ООО «Курица»',
            'email' => 'info@example.com',
            'phone' => '+7 (900) 123-45-67',
        ]);

        Settings::flush();

        $this->actingAsAdmin();

        Livewire::test(SiteSettings::class)
            ->assertSet('data.company_name', 'ООО «Курица»')
            ->assertSet('data.email', 'info@example.com')
            ->assertSet('data.phone', '+7 (900) 123-45-67');
    }

    public function test_saving_site_settings_writes_a_row_with_id_one(): void
    {
        $this->actingAsAdmin();

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'company_name' => 'Новый заголовок',
                'short_description' => 'Короткое описание',
                'phone' => '+7 (999) 111-22-33',
                'email' => 'new@example.com',
                'address' => 'Улица 1',
                'schedule' => 'Пн–Пт',
                'telegram' => '@new',
                'whatsapp' => '+79991112233',
                'vk' => 'vk',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('site_settings', [
            'id' => 1,
            'company_name' => 'Новый заголовок',
            'email' => 'new@example.com',
        ]);
    }

    public function test_saving_empty_settings_keeps_the_row_and_uses_config_fallbacks(): void
    {
        SiteSetting::query()->updateOrCreate(['id' => 1], [
            'company_name' => 'Старое',
        ]);

        Settings::flush();

        $this->actingAsAdmin();

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'company_name' => '',
                'short_description' => null,
                'phone' => null,
                'phone_secondary' => null,
                'email' => null,
                'address' => null,
                'schedule' => null,
                'telegram' => null,
                'whatsapp' => null,
                'vk' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('site_settings', 1);
        $this->assertDatabaseHas('site_settings', ['id' => 1]);

        Settings::flush();
        $this->assertSame(self::CONFIG_COMPANY_NAME, Settings::value('company_name'));
    }

    public function test_email_validation_rejects_invalid_values(): void
    {
        $this->actingAsAdmin();

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'company_name' => 'Тест',
                'email' => 'not-an-email',
            ])
            ->call('save')
            ->assertHasFormErrors(['email']);
    }

    public function test_a_successful_save_sends_a_notification(): void
    {
        $this->actingAsAdmin();

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'company_name' => 'Тест',
                'email' => 'test@example.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified();
    }
}
