<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\QualityCertificates\Pages\CreateQualityCertificate;
use App\Filament\Resources\QualityCertificates\Pages\EditQualityCertificate;
use App\Filament\Resources\QualityCertificates\Pages\ListQualityCertificates;
use App\Models\QualityCertificate;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * QualityCertificateResource: управление сертификатами в режиме
 * READ + CREATE + UPDATE + DELETE.
 *
 * Проверяется ровно то, что нужно заказчику: раздел закрыт для чужих, набор
 * страниц и действий на месте, создание и правка действительно попадают в
 * базу, файл уезжает на облачный диск и убирается вместе с записью или при
 * замене.
 *
 * Тесты работают на SQLite :memory: (phpunit.xml) и не касаются рабочей базы.
 */
final class QualityCertificateResourceTest extends TestCase
{
    use RefreshDatabase;

    private const ALLOWED_EMAIL = 'admin@local.test';

    private const DENIED_EMAIL = 'outsider@local.test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('admin.panel_access_emails', [self::ALLOWED_EMAIL]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['email' => self::ALLOWED_EMAIL]);

        $this->actingAs($admin);

        return $admin;
    }

    private function certificate(array $attributes = []): QualityCertificate
    {
        return QualityCertificate::create(array_merge([
            'title' => 'Сертификат соответствия',
            'file' => 'certificates/cld-example.pdf',
            'is_active' => true,
            'sort_order' => 0,
        ], $attributes));
    }

    private function fakePdf(string $name = 'certificate.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 10, 'application/pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Доступ к разделу
    |--------------------------------------------------------------------------
    */

    public function test_a_guest_cannot_open_the_certificate_resource(): void
    {
        $this->get('/admin/quality-certificates')
            ->assertStatus(302)
            ->assertRedirectContains('/admin/login');
    }

    public function test_a_user_outside_the_allow_list_cannot_open_the_certificate_resource(): void
    {
        $user = User::factory()->create(['email' => self::DENIED_EMAIL]);

        $this->actingAs($user)->get('/admin/quality-certificates')->assertForbidden();
    }

    public function test_an_allowed_admin_can_open_the_certificate_resource(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/quality-certificates')->assertOk();
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Страницы и список
    |--------------------------------------------------------------------------
    */

    public function test_the_resource_registers_create_and_edit_pages(): void
    {
        $this->assertNotNull(Route::getRoutes()->getByName('filament.admin.resources.quality-certificates.index'));
        $this->assertNotNull(Route::getRoutes()->getByName('filament.admin.resources.quality-certificates.create'));
        $this->assertNotNull(Route::getRoutes()->getByName('filament.admin.resources.quality-certificates.edit'));
    }

    public function test_the_list_shows_the_certificates(): void
    {
        $this->actingAsAdmin();

        $certificate = $this->certificate(['title' => 'Декларация о соответствии', 'sort_order' => 1]);

        Livewire::test(ListQualityCertificates::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$certificate])
            ->assertSee('Декларация о соответствии');
    }

    public function test_the_list_has_a_create_action(): void
    {
        $this->actingAsAdmin();

        Livewire::test(ListQualityCertificates::class)
            ->assertSuccessful()
            ->assertActionExists(CreateAction::class);
    }

    public function test_the_list_orders_certificates_by_sort_order(): void
    {
        $this->actingAsAdmin();

        $this->certificate(['title' => 'Второй', 'sort_order' => 2]);
        $this->certificate(['title' => 'Первый', 'sort_order' => 1]);

        Livewire::test(ListQualityCertificates::class)
            ->assertSuccessful()
            ->assertSeeInOrder(['Первый', 'Второй']);
    }

    public function test_an_admin_can_toggle_the_active_flag_right_in_the_list(): void
    {
        $this->actingAsAdmin();

        $certificate = $this->certificate(['is_active' => true]);

        Livewire::test(ListQualityCertificates::class)
            ->assertTableColumnStateSet('is_active', true, $certificate)
            ->call('updateTableColumnState', 'is_active', (string) $certificate->getKey(), false)
            ->assertHasNoActionErrors();

        $this->assertFalse($certificate->fresh()->is_active);
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Создание
    |--------------------------------------------------------------------------
    */

    public function test_an_admin_can_create_a_certificate_and_it_reaches_the_database(): void
    {
        Storage::fake('cloudinary');

        $this->actingAsAdmin();

        Livewire::test(CreateQualityCertificate::class)
            ->fillForm([
                'title' => 'Сертификат качества',
                'file' => $this->fakePdf(),
                'is_active' => true,
                'sort_order' => 3,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $certificate = QualityCertificate::query()->where('title', 'Сертификат качества')->firstOrFail();

        $this->assertStringStartsWith('certificates/cld-', $certificate->file);
        $this->assertSame(3, $certificate->sort_order);
        $this->assertTrue($certificate->is_active);
        $this->assertTrue(Storage::disk('cloudinary')->exists($certificate->file));
    }

    public function test_the_title_is_required(): void
    {
        $this->actingAsAdmin();

        Livewire::test(CreateQualityCertificate::class)
            ->fillForm([
                'title' => '',
                'file' => $this->fakePdf(),
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);
    }

    public function test_the_sort_order_refuses_a_negative_value(): void
    {
        Storage::fake('cloudinary');

        $this->actingAsAdmin();

        Livewire::test(CreateQualityCertificate::class)
            ->fillForm([
                'title' => 'Кривой порядок',
                'file' => $this->fakePdf(),
                'sort_order' => -1,
            ])
            ->call('create')
            ->assertHasFormErrors(['sort_order']);
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Правка и замена файла
    |--------------------------------------------------------------------------
    */

    public function test_an_admin_can_open_a_certificate_for_editing(): void
    {
        $this->actingAsAdmin();

        Livewire::test(EditQualityCertificate::class, ['record' => $this->certificate()->getKey()])
            ->assertSuccessful()
            ->assertFormFieldExists('title')
            ->assertFormFieldExists('file')
            ->assertFormFieldExists('is_active')
            ->assertFormFieldExists('sort_order');
    }

    public function test_an_admin_can_rename_a_certificate_and_the_change_reaches_the_database(): void
    {
        $this->actingAsAdmin();

        $certificate = $this->certificate();

        Livewire::test(EditQualityCertificate::class, ['record' => $certificate->getKey()])
            ->fillForm(['title' => 'Декларация о соответствии'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Декларация о соответствии', $certificate->fresh()->title);
    }

    public function test_replacing_the_file_deletes_the_old_managed_file(): void
    {
        config(['filesystems.disks.cloudinary.cloud_name' => 'test-cloud']);
        Storage::fake('cloudinary');

        Storage::disk('cloudinary')->put('certificates/cld-old.pdf', 'старые байты');

        $certificate = $this->certificate(['file' => 'certificates/cld-old.pdf']);

        $this->actingAsAdmin();

        Livewire::test(EditQualityCertificate::class, ['record' => $certificate->getKey()])
            ->set('data.file', null)
            ->fillForm([
                'file' => ['new-key' => 'certificates/cld-new.pdf'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('certificates/cld-new.pdf', $certificate->fresh()->file);
        Storage::disk('cloudinary')->assertMissing('certificates/cld-old.pdf');
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Удаление уносит файл
    |--------------------------------------------------------------------------
    */

    public function test_deleting_a_certificate_removes_its_file(): void
    {
        config(['filesystems.disks.cloudinary.cloud_name' => 'test-cloud']);
        Storage::fake('cloudinary');

        Storage::disk('cloudinary')->put('certificates/cld-delete-me.pdf', 'байты');

        $certificate = $this->certificate(['file' => 'certificates/cld-delete-me.pdf']);

        $this->actingAsAdmin();

        Livewire::test(EditQualityCertificate::class, ['record' => $certificate->getKey()])
            ->callAction(DeleteAction::class);

        Storage::disk('cloudinary')->assertMissing('certificates/cld-delete-me.pdf');
        $this->assertDatabaseMissing('quality_certificates', ['id' => $certificate->getKey()]);
    }
}
