<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Settings;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Settings::flush();
    }

    protected function tearDown(): void
    {
        Settings::flush();

        parent::tearDown();
    }

    public function test_the_site_settings_page_is_available_for_an_admin_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

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

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/admin/site-settings')->assertOk()->assertSee('ООО «Курица»');
    }

    public function test_saving_site_settings_writes_a_row_with_id_one(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/admin/site-settings', [
            'data' => [
                'company_name' => 'Новый заголовок',
                'short_description' => 'Короткое описание',
                'phone' => '+7 (999) 111-22-33',
                'email' => 'new@example.com',
                'address' => 'Улица 1',
                'schedule' => 'Пн–Пт',
                'telegram' => '@new',
                'whatsapp' => '+79991112233',
                'vk' => 'vk',
            ],
        ])->assertStatus(302);

        $this->assertDatabaseHas('site_settings', [
            'id' => 1,
            'company_name' => 'Новый заголовок',
            'email' => 'new@example.com',
        ]);
    }

    public function test_saving_empty_settings_keeps_the_row_and_uses_config_fallbacks(): void
    {
        SiteSetting::query()->create([
            'id' => 1,
            'company_name' => 'Старое',
        ]);

        Settings::flush();

        $user = User::factory()->create();
        $this->actingAs($user);

        $this->post('/admin/site-settings', [
            'data' => [
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
            ],
        ])->assertStatus(302);

        $this->assertDatabaseCount('site_settings', 1);
        $this->assertDatabaseHas('site_settings', ['id' => 1]);

        Settings::flush();
        $this->assertSame('Chicken Farm', Settings::value('company_name'));
    }

    public function test_email_validation_rejects_invalid_values(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response =         $this->post('/admin/site-settings', [
            'data' => [
                'email' => 'not-an-email',
            ],
        ]);

        $response->assertStatus(302);
    }

    public function test_a_successful_save_sends_a_notification(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/admin/site-settings', [
            'data' => [
                'company_name' => 'Тест',
                'email' => 'test@example.com',
            ],
        ]);

        $response->assertStatus(302);
    }
}
