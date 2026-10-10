<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\QualityCertificate;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Публичный блок «Сертификаты и документы» на странице /quality.
 *
 * КЛЮЧЕВОЕ ПРАВИЛО
 *
 * На пустой базе блок не выводится вообще: до загрузки документов у компании
 * их нет, и заголовок «Сертификаты» на странице был бы неправдой. Это же
 * требование охраняет уже существующий тест
 * PublicPagesTest::test_the_quality_page_makes_no_unverified_product_claims.
 *
 * Показываются только активные документы, порядок задаётся полем sort_order.
 * Скан рисуется картинкой, PDF — плиткой со ссылкой.
 */
final class QualityPageCertificatesTest extends TestCase
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

    private function certificate(array $attributes = []): QualityCertificate
    {
        return QualityCertificate::create(array_merge([
            'title' => 'Сертификат соответствия',
            'file' => 'certificates/cld-example.pdf',
            'is_active' => true,
            'sort_order' => 0,
        ], $attributes));
    }

    private function useCloudinaryDelivery(): void
    {
        config(['filesystems.disks.cloudinary.cloud_name' => 'test-cloud']);
        Storage::forgetDisk('cloudinary');
    }

    public function test_the_page_shows_no_certificate_section_without_certificates(): void
    {
        $this->get('/quality')
            ->assertOk()
            ->assertDontSee('Подтверждено документами')
            ->assertDontSee('Сертификаты и документы');
    }

    public function test_active_certificates_are_shown(): void
    {
        $this->certificate(['title' => 'Сертификат ISO 22000']);

        $this->get('/quality')
            ->assertOk()
            ->assertSee('Подтверждено документами')
            ->assertSee('Сертификаты и документы')
            ->assertSee('Сертификат ISO 22000')
            ->assertSee('Открыть документ');
    }

    public function test_inactive_certificates_are_hidden(): void
    {
        $this->certificate(['title' => 'Черновик сертификата', 'is_active' => false]);

        $this->get('/quality')
            ->assertOk()
            ->assertDontSee('Черновик сертификата')
            ->assertDontSee('Сертификаты и документы');
    }

    public function test_certificates_follow_the_sort_order(): void
    {
        $this->certificate(['title' => 'Второй документ', 'sort_order' => 2]);
        $this->certificate(['title' => 'Первый документ', 'sort_order' => 1]);

        $this->get('/quality')
            ->assertOk()
            ->assertSeeInOrder(['Первый документ', 'Второй документ']);
    }

    public function test_an_image_certificate_is_rendered_as_a_picture(): void
    {
        $this->useCloudinaryDelivery();

        $this->certificate([
            'title' => 'Скан сертификата',
            'file' => 'certificates/cld-scan.jpg',
        ]);

        $this->get('/quality')
            ->assertOk()
            ->assertSee(
                '<img',
                false,
            )
            ->assertSee(
                'src="https://res.cloudinary.com/test-cloud/image/upload/certificates/cld-scan.jpg"',
                false,
            );
    }

    public function test_a_pdf_certificate_is_rendered_as_a_linked_tile(): void
    {
        $this->useCloudinaryDelivery();

        $this->certificate([
            'title' => 'Декларация о соответствии',
            'file' => 'certificates/cld-declaration.pdf',
        ]);

        $this->get('/quality')
            ->assertOk()
            ->assertSee('PDF')
            ->assertSee(
                'href="https://res.cloudinary.com/test-cloud/image/upload/certificates/cld-declaration.pdf"',
                false,
            );
    }
}
