<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Логотип компании: загрузка из админки, хранение и показ на сайте.
 *
 * ЧТО ЗДЕСЬ ПРОВЕРЯЕТСЯ
 *
 *  - поле logo есть на странице «Настройки сайта» и грузит файл на облачный
 *    диск 'cloudinary' (каталог brand) с именем «brand/cld-<ulid>.<ext>»;
 *  - в site_settings.logo попадает только относительный путь;
 *  - замена и очистка логотипа убирают старый файл, только после успешной
 *    записи;
 *  - Settings::logoUrl() строит публичный адрес, а без логотипа возвращает
 *    null;
 *  - шапка и подвал показывают логотип, когда он задан, и возвращаются к
 *    названию компании, когда его нет.
 *
 * STORAGE::FAKE
 *
 * Облачный диск подменяется на Storage::fake('cloudinary') в тестах
 * загрузки/удаления: файлы не уходят в сеть, реальный Cloudinary не
 * затрагивается. В тестах показа адрес строится настоящим URL-ом адаптера
 * (config cloud_name + Storage::forgetDisk), потому что Storage::fake()
 * подменил бы и CDN-адаптер.
 *
 * RefreshDatabase работает на SQLite :memory: из phpunit.xml, поэтому рабочая
 * база не затрагивается.
 */
final class SiteLogoTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED_EMAIL = 'admin@local.test';

    private const CONFIG_COMPANY_NAME = 'Chicken Farm';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('admin.panel_access_emails', [self::ALLOWED_EMAIL]);
        config()->set('site.name', self::CONFIG_COMPANY_NAME);

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

    private function fakePng(string $name = 'logo.png'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 10, 'image/png');
    }

    private function setLogoInDatabase(?string $path): void
    {
        SiteSetting::query()->updateOrCreate(['id' => 1], ['logo' => $path]);

        Settings::flush();
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Поле формы и загрузка
    |--------------------------------------------------------------------------
    */

    public function test_the_settings_page_has_a_logo_field(): void
    {
        $this->actingAsAdmin();

        Livewire::test(SiteSettings::class)
            ->assertSuccessful()
            ->assertFormFieldExists('logo');
    }

    public function test_uploading_a_logo_stores_a_cloudinary_path_and_writes_the_database(): void
    {
        Storage::fake('cloudinary');

        $this->actingAsAdmin();

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'company_name' => 'ООО «Курица»',
                'logo' => $this->fakePng(),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $logo = SiteSetting::query()->findOrFail(1)->logo;

        $this->assertIsString($logo);
        $this->assertStringStartsWith('brand/cld-', $logo);
        $this->assertTrue(
            Storage::disk('cloudinary')->exists($logo),
            'Файл логотипа должен лежать на диске cloudinary.',
        );
    }

    public function test_replacing_a_logo_deletes_the_old_managed_file(): void
    {
        config(['filesystems.disks.cloudinary.cloud_name' => 'test-cloud']);
        Storage::fake('cloudinary');

        Storage::disk('cloudinary')->put('brand/cld-old.png', 'старые байты');
        $this->setLogoInDatabase('brand/cld-old.png');

        $this->actingAsAdmin();

        Livewire::test(SiteSettings::class)
            ->set('data.logo', null)
            ->fillForm([
                'logo' => ['new-key' => 'brand/cld-new.png'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('brand/cld-new.png', SiteSetting::query()->findOrFail(1)->logo);
        Storage::disk('cloudinary')->assertMissing('brand/cld-old.png');
    }

    public function test_clearing_a_logo_deletes_the_file_and_sets_null(): void
    {
        config(['filesystems.disks.cloudinary.cloud_name' => 'test-cloud']);
        Storage::fake('cloudinary');

        Storage::disk('cloudinary')->put('brand/cld-old.png', 'старые байты');
        $this->setLogoInDatabase('brand/cld-old.png');

        $this->actingAsAdmin();

        Livewire::test(SiteSettings::class)
            ->set('data.logo', null)
            ->fillForm(['logo' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(SiteSetting::query()->findOrFail(1)->logo);
        Storage::disk('cloudinary')->assertMissing('brand/cld-old.png');
    }

    public function test_editing_other_fields_leaves_the_logo_untouched(): void
    {
        config(['filesystems.disks.cloudinary.cloud_name' => 'test-cloud']);
        Storage::fake('cloudinary');

        Storage::disk('cloudinary')->put('brand/cld-keep.png', 'байты');
        $this->setLogoInDatabase('brand/cld-keep.png');

        $this->actingAsAdmin();

        Livewire::test(SiteSettings::class)
            ->fillForm(['company_name' => 'Переименованная компания'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('brand/cld-keep.png', SiteSetting::query()->findOrFail(1)->logo);
        Storage::disk('cloudinary')->assertExists('brand/cld-keep.png');
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Адрес логотипа
    |--------------------------------------------------------------------------
    */

    public function test_the_logo_url_is_null_without_a_logo(): void
    {
        $this->assertNull(Settings::logoUrl());
    }

    public function test_the_logo_url_is_built_from_the_cloudinary_cdn(): void
    {
        config(['filesystems.disks.cloudinary.cloud_name' => 'test-cloud']);
        Storage::forgetDisk('cloudinary');

        $this->setLogoInDatabase('brand/cld-abc.png');

        $this->assertSame(
            'https://res.cloudinary.com/test-cloud/image/upload/brand/cld-abc.png',
            Settings::logoUrl(),
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Показ на сайте
    |--------------------------------------------------------------------------
    */

    public function test_the_header_shows_the_logo_when_it_is_set(): void
    {
        config(['filesystems.disks.cloudinary.cloud_name' => 'test-cloud']);
        Storage::forgetDisk('cloudinary');

        $this->setLogoInDatabase('brand/cld-abc.png');

        $this->get('/')
            ->assertOk()
            ->assertSee('src="https://res.cloudinary.com/test-cloud/image/upload/brand/cld-abc.png"', false)
            ->assertSee('alt="'.self::CONFIG_COMPANY_NAME.'"', false);
    }

    public function test_the_footer_shows_the_logo_when_it_is_set(): void
    {
        config(['filesystems.disks.cloudinary.cloud_name' => 'test-cloud']);
        Storage::forgetDisk('cloudinary');

        $this->setLogoInDatabase('brand/cld-abc.png');

        $html = $this->get('/')->assertOk()->getContent();

        // Логотип показан и в шапке, и в подвале: две ссылки на CDN.
        $this->assertSame(
            2,
            substr_count($html, 'https://res.cloudinary.com/test-cloud/image/upload/brand/cld-abc.png'),
        );
    }

    public function test_the_header_falls_back_to_the_company_name_without_a_logo(): void
    {
        $this->assertNull(Settings::logoUrl());

        $this->get('/')
            ->assertOk()
            ->assertSee(self::CONFIG_COMPANY_NAME)
            ->assertDontSee('res.cloudinary.com/test-cloud/image/upload/brand/', false);
    }
}
