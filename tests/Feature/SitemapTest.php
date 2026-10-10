<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ProductCategory;
use Database\Seeders\ProductCatalogSeeder;
use DOMDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Тесты карты сайта /sitemap.xml и правил для роботов public/robots.txt.
 *
 * Ключевая проверка файла — test_every_address_in_the_sitemap_really_responds_with_200:
 * карта обещает роботу адреса, поэтому каждый её адрес обязан открываться.
 * Тест ловит расхождение между картой и сайтом в обе стороны — лишний адрес
 * и несуществующий одновременно, потому что идёт от одних и тех же данных.
 *
 * Второй по важности — test_the_sitemap_uses_the_configured_app_url_rather_than_the_request_host:
 * домен в карте обязан быть тем, что назван в APP_URL, а не тем, по которому
 * пришёл запрос. Иначе робот, зашедший на localhost или на IP-адрес, увёл бы
 * в карту несуществующие адреса.
 *
 * APP_URL в setUp задан явно, чтобы тесты не зависели от APP_URL в .env
 * разработчика: проверка домена, зависящая от локального файла, проверяла бы
 * не код, а настройку машины.
 *
 * RefreshDatabase работает на SQLite :memory: из phpunit.xml, поэтому тесты
 * не трогают рабочую базу database/database.sqlite.
 */
final class SitemapTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Домен, подставленный в тесты вместо локального APP_URL.
     */
    private const SITE_URL = 'https://example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductCatalogSeeder::class);

        config(['app.url' => self::SITE_URL]);
    }

    // ------------------------------------------------------------------
    // 1. Карта отдаётся как XML
    // ------------------------------------------------------------------

    public function test_the_sitemap_is_served_as_xml(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString(
            'application/xml',
            (string) $response->headers->get('Content-Type'),
        );
    }

    public function test_the_sitemap_is_a_well_formed_xml_document(): void
    {
        /*
         * Проверка не косметическая: объявление XML, если перед ним остался
         * перевод строки, делает документ невалидным, и робот отвергнет всю
         * карту целиком. Поэтому XML именно разбирается, а не ищется глазами.
         */
        $xml = $this->document($this->sitemap());

        $this->assertSame('urlset', $xml->documentElement?->nodeName);
        $this->assertSame(
            'http://www.sitemaps.org/schemas/sitemap/0.9',
            $xml->documentElement?->namespaceURI,
        );
    }

    public function test_the_sitemap_declares_its_encoding(): void
    {
        // Без явного UTF-8 робот может иначе истолковать кириллицу в адресах.
        $this->assertStringStartsWith(
            '<?xml version="1.0" encoding="UTF-8"?>',
            $this->sitemap(),
        );
    }

    // ------------------------------------------------------------------
    // 2. Состав карты
    // ------------------------------------------------------------------

    public function test_the_sitemap_lists_every_public_page(): void
    {
        $locations = $this->locations();

        // Постоянные страницы — из навигации config/site.php.
        $this->assertContains(self::SITE_URL.'/', $locations);
        $this->assertContains(self::SITE_URL.'/products', $locations);
        $this->assertContains(self::SITE_URL.'/quality', $locations);
        $this->assertContains(self::SITE_URL.'/about', $locations);
        $this->assertContains(self::SITE_URL.'/contacts', $locations);

        // Страницы категорий — из активных категорий в базе.
        $this->assertContains(self::SITE_URL.'/products/eggs', $locations);
        $this->assertContains(self::SITE_URL.'/products/chicken', $locations);

        $this->assertCount(7, $locations);
    }

    public function test_every_address_in_the_sitemap_really_responds_with_200(): void
    {
        foreach ($this->locations() as $location) {
            $path = parse_url($location, PHP_URL_PATH);

            $this->get($path)->assertOk();
        }
    }

    public function test_the_sitemap_holds_no_duplicate_addresses(): void
    {
        $locations = $this->locations();

        $this->assertSame(count($locations), count(array_unique($locations)));
    }

    public function test_every_address_is_absolute(): void
    {
        foreach ($this->locations() as $location) {
            $this->assertNotFalse(filter_var($location, FILTER_VALIDATE_URL), $location);
        }
    }

    // ------------------------------------------------------------------
    // 3. Домен берётся из APP_URL, а не из запроса
    // ------------------------------------------------------------------

    public function test_the_sitemap_uses_the_configured_app_url_rather_than_the_request_host(): void
    {
        config(['app.url' => 'https://production.example']);

        // Запрос с чужого хоста не должен менять домен в карте.
        $response = $this->get('http://127.0.0.1:8899/sitemap.xml');

        $response->assertOk();

        $locations = $this->locations($response);

        $this->assertNotEmpty($locations);

        foreach ($locations as $location) {
            $this->assertStringStartsWith('https://production.example/', $location);
            $this->assertStringNotContainsString('127.0.0.1', $location);
            $this->assertStringNotContainsString('localhost', $location);
        }
    }

    public function test_the_sitemap_has_no_double_slashes_in_addresses(): void
    {
        // APP_URL с завершающим слэшем — обычная конфигурация, и лишний слэш
        // в адресе робот трактует как другой адрес.
        config(['app.url' => 'https://example.test/']);

        foreach ($this->locations() as $location) {
            $this->assertStringNotContainsString('//', substr($location, strlen('https://')), $location);
        }
    }

    // ------------------------------------------------------------------
    // 4. Чего в карте быть не должно
    // ------------------------------------------------------------------

    public function test_the_sitemap_does_not_advertise_the_admin_panel(): void
    {
        $content = $this->sitemap();

        $this->assertStringNotContainsString('/admin', $content);
    }

    public function test_the_sitemap_does_not_advertise_pages_without_a_route(): void
    {
        /*
         * В config/site.php есть пункт legal_links, но маршрутов legal.*
         * пока нет. Проверка охраняет от карты, в которой такой пункт всё же
         * появился бы: робот обошёл бы адрес и получил 404.
         */
        $this->assertStringNotContainsString('/privacy', $this->sitemap());
        $this->assertStringNotContainsString('personal-data', $this->sitemap());
    }

    public function test_the_sitemap_does_not_advertise_an_inactive_category(): void
    {
        ProductCategory::where('slug', 'eggs')->update(['is_active' => false]);

        $locations = $this->locations();

        $this->assertNotContains(self::SITE_URL.'/products/eggs', $locations);

        // Остальные страницы от выключенной категории не пострадали.
        $this->assertContains(self::SITE_URL.'/products/chicken', $locations);
        $this->assertContains(self::SITE_URL.'/', $locations);
    }

    public function test_the_sitemap_lists_a_category_added_in_the_admin_panel(): void
    {
        ProductCategory::create([
            'name' => 'Перепелиные яйца',
            'description' => 'Небольшая партия перепелиных яиц.',
            'slug' => 'iz-bazy',
            'is_active' => true,
            'sort_order' => 9,
        ]);

        $locations = $this->locations();

        // Категория живёт по общему маршруту products.category, а не в
        // config/site.php, но в карте она обязана появиться: её страница
        // открывается, значит робот вправе её узнать.
        $this->assertContains(self::SITE_URL.'/products/iz-bazy', $locations);
        $this->assertCount(8, $locations);

        $this->get('/products/iz-bazy')->assertOk();
    }

    public function test_the_sitemap_does_not_advertise_an_inactive_admin_category(): void
    {
        ProductCategory::create([
            'name' => 'Перепелиные яйца',
            'description' => 'Небольшая партия перепелиных яиц.',
            'slug' => 'iz-bazy',
            'is_active' => false,
            'sort_order' => 9,
        ]);

        $locations = $this->locations();

        $this->assertNotContains(self::SITE_URL.'/products/iz-bazy', $locations);
        $this->assertCount(7, $locations);
    }

    public function test_the_sitemap_does_not_list_the_contact_form_as_a_separate_address(): void
    {
        // POST /contacts — адрес формы, а не страница: GET-версии у него нет.
        $this->assertSame(
            1,
            count(array_filter($this->locations(), static fn (string $l): bool => str_ends_with($l, '/contacts'))),
        );
    }

    public function test_the_sitemap_does_not_publish_a_lastmod_date(): void
    {
        // Не подтверждённая дата изменения, а придуманная из времени коммита.
        $this->assertStringNotContainsString('<lastmod>', $this->sitemap());
    }

    public function test_the_sitemap_does_not_leak_unconfirmed_company_data(): void
    {
        $content = $this->sitemap();

        foreach (['[НАЗВАНИЕ КОМПАНИИ]', '[ТЕЛЕФОН]', '[EMAIL]', '[АДРЕС]'] as $placeholder) {
            $this->assertStringNotContainsString($placeholder, $content);
        }
    }

    public function test_the_sitemap_contains_no_html_markup(): void
    {
        $content = $this->sitemap();

        $this->assertStringNotContainsString('<!DOCTYPE html', $content);
        $this->assertStringNotContainsString('<html', $content);
    }

    // ------------------------------------------------------------------
    // 5. Правила для роботов
    // ------------------------------------------------------------------

    public function test_robots_txt_closes_the_admin_panel(): void
    {
        $this->assertStringContainsString('Disallow: /admin', $this->robots());
    }

    public function test_robots_txt_does_not_block_the_whole_site(): void
    {
        // Префиксное правило по умолчанию «Disallow:» без значения закрыло бы
        // сайт целиком — это обратная ошибка.
        $this->assertStringNotContainsString("Disallow:\n", $this->robots());
        $this->assertStringContainsString('User-agent: *', $this->robots());
    }

    public function test_robots_txt_does_not_block_the_sitemap(): void
    {
        // Запрет на сам sitemap.xml закрыл бы роботу карту целиком.
        $this->assertDoesNotMatchRegularExpression(
            '/^Disallow:\s*\/sitemap\.xml\s*$/m',
            $this->robots(),
        );
    }

    public function test_robots_txt_does_not_block_public_pages_or_images(): void
    {
        foreach (['/products', '/about', '/quality', '/contacts', '/storage', '/images'] as $path) {
            $this->assertDoesNotMatchRegularExpression(
                '/^Disallow:\s*'.preg_quote($path, '/').'\s*$/m',
                $this->robots(),
            );
        }
    }

    public function test_robots_txt_does_not_publish_an_invented_domain(): void
    {
        /*
         * Домен заказчиком не подтверждён. Незакомментированная директива
         * Sitemap с выдуманным адресом хуже, чем её отсутствие: робот
         * пойдёт по несуществующему адресу. Директива поэтому остаётся в
         * комментарии и включается вместе с настоящим APP_URL на сервере.
         */
        $this->assertDoesNotMatchRegularExpression(
            '/^Sitemap:\s*\S/m',
            $this->robots(),
        );
    }

    // ------------------------------------------------------------------
    // Вспомогательное
    // ------------------------------------------------------------------

    private function sitemap(): string
    {
        return (string) $this->get('/sitemap.xml')->getContent();
    }

    private function robots(): string
    {
        $path = public_path('robots.txt');

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function document(string $xml): DOMDocument
    {
        $document = new DOMDocument;

        // LIBXML_NONET — карта не должна тянуть сеть при разборе.
        $this->assertTrue($document->loadXML($xml, LIBXML_NONET), 'XML не разбирается.');

        return $document;
    }

    /**
     * Адреса из карты в виде обычного массива.
     *
     * @return list<string>
     */
    private function locations(?TestResponse $response = null): array
    {
        $response ??= $this->get('/sitemap.xml');

        $xml = $this->document((string) $response->getContent());

        $locations = [];

        foreach ($xml->getElementsByTagName('loc') as $node) {
            $locations[] = (string) $node->textContent;
        }

        return $locations;
    }
}
