<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Тесты публичных страниц верхнего уровня: /products, /about, /quality,
 * /contacts и их появления в навигации.
 *
 * Проверяется ровно то, что нужно для показа заказчику: страницы
 * отвечают 200, обзорная страница продукции перечисляет обе категории и
 * ведёт на них, а пункты шапки и подвала стали ссылками. Отдельно
 * проверяется, что выдуманных данных на страницах нет: заглушки
 * [НАЗВАНИЕ КОМПАНИИ], [ТЕЛЕФОН] и [EMAIL] обязаны остаться на месте, а
 * не исчезнуть вместе с новыми страницами.
 *
 * RefreshDatabase работает на SQLite :memory: из phpunit.xml, поэтому тесты
 * не трогают рабочую базу database/database.sqlite.
 */
final class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductCatalogSeeder::class);
    }

    /**
     * Видимый текст страницы без разметки.
     *
     * Нужна для проверок «на странице нет выдуманного факта». Поиск по
     * исходному HTML даёт ложные срабатывания: имена CSS-классов вроде
     * duration-200 содержат цифры, которые ищутся как даты, а атрибуты alt
     * и title — это текст, который посетитель не читает как абзац. Поэтому
     * теги вырезаются, а сравнение идёт в нижнем регистре.
     */
    private function visibleText(TestResponse $response): string
    {
        $html = (string) $response->getContent();

        // Скрипты и стили убираем целиком: их содержимое не видит посетитель.
        $html = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;

        // Оставшиеся теги и HTML-сущности заменяем пробелом, чтобы слова
        // из соседних элементов не склеились в одно.
        $text = preg_replace('/<[^>]+>/', ' ', $html) ?? $html;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_strtolower($text);
    }

    // ------------------------------------------------------------------
    // 1. Страницы отвечают 200
    // ------------------------------------------------------------------

    public function test_the_public_pages_respond_with_200(): void
    {
        $this->get('/products')->assertOk();
        $this->get('/about')->assertOk();
        $this->get('/quality')->assertOk();
        $this->get('/contacts')->assertOk();
    }

    public function test_an_unknown_public_page_still_returns_404(): void
    {
        // Страницы верхнего уровня не должны превращать раздел в
        // catch-all: неизвестный путь по-прежнему 404.
        $this->get('/net-takoy-stranicy')->assertNotFound();
    }

    // ------------------------------------------------------------------
    // 2. /products: категории и ссылки на них
    // ------------------------------------------------------------------

    public function test_the_products_page_lists_both_categories(): void
    {
        $response = $this->get('/products')->assertOk();

        $response->assertSee('Яйца кур');
        $response->assertSee('Мясо кур');
    }

    public function test_the_products_page_links_to_both_category_pages(): void
    {
        $response = $this->get('/products')->assertOk();

        $response->assertSee(route('products.eggs'), false);
        $response->assertSee(route('products.chicken'), false);
    }

    public function test_the_products_page_shows_no_third_category(): void
    {
        // Категория добавлена в базу, но не описана в config/site.php.
        // Показывать её нечем, поэтому на обзорной странице её быть не
        // должно: молчаливый третий пункт был бы выдумкой интерфейса.
        ProductCategory::create([
            'name' => 'Категория без описания',
            'slug' => 'net-takoy',
            'is_active' => true,
            'sort_order' => 9,
        ]);

        $this->get('/products')
            ->assertOk()
            ->assertDontSee('Категория без описания');
    }

    public function test_an_inactive_category_disappears_from_the_products_page(): void
    {
        ProductCategory::where('slug', 'eggs')->update(['is_active' => false]);

        $response = $this->get('/products')->assertOk();

        $response->assertSee('Мясо кур');
        $response->assertDontSee('Яйца кур');

        // При этом адрес самой категории продолжает работать по старым
        // правилам: он зарегистрирован и отдаёт 404, а не ведёт в никуда.
        $this->get('/products/eggs')->assertNotFound();
    }

    public function test_the_products_page_keeps_listing_a_category_whose_products_are_hidden(): void
    {
        // Категория активна, но все её товары выключены. Обзорная страница
        // перечисляет категории, а не позиции, поэтому «Яйца кур» обязаны
        // остаться: исчезновение категории выглядело бы как удаление
        // раздела, а его никто не удалял.
        $eggsId = ProductCategory::where('slug', 'eggs')->value('id');
        $this->assertGreaterThan(0, Product::where('product_category_id', $eggsId)->update(['is_active' => false]));

        $response = $this->get('/products')->assertOk();

        $response->assertSee('Яйца кур');
        $response->assertSee(route('products.eggs'), false);
        $response->assertSee('Мясо кур');

        // И ссылка ведёт на живую страницу, а не на 404.
        $this->get('/products/eggs')->assertOk();
    }

    public function test_the_products_page_has_exactly_one_h1(): void
    {
        $response = $this->get('/products')->assertOk();

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_the_products_page_shows_a_product_count_not_a_stub(): void
    {
        // На странице обзора нет карточек товаров: ассортимент живёт на
        // страницах категорий. Проверяем, что страница не подменяет обзор
        // случайным набором позиций.
        $this->get('/products')
            ->assertOk()
            ->assertDontSee('Яйцо куриное C0')
            ->assertDontSee('Тушка курицы');
    }

    // ------------------------------------------------------------------
    // 3. Навигация: пункты шапки и подвала стали ссылками
    // ------------------------------------------------------------------

    public function test_the_header_and_footer_link_to_the_new_pages(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (['products.index', 'about', 'quality', 'contacts'] as $routeName) {
            $response->assertSee(route($routeName), false);
        }
    }

    public function test_no_main_navigation_item_stays_pending(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        /*
         | В шапке неактивных пунктов остаться не должно: маршруты
         | products.index, about, quality и contacts созданы, и все четыре
         | пункта navigation нашлись по Route::has().
         |
         | В подвале неактивными остаются ровно два пункта — юридические
         | документы. Их текстов заказчик не передавал, поэтому страницы под
         | них не создаётся и выдумывать её нельзя. Проверяем именно шапку,
         | а подвал отдельно: иначе тест рушился бы по необходимым
         | неактивным пунктам и перестал бы что-либо проверять.
         */
        [$header] = explode('<footer', $html, 2);

        $this->assertStringNotContainsString('data-nav-pending', $header);

        $this->assertSame(2, substr_count($html, 'data-nav-pending'));
    }

    public function test_the_contact_ctas_point_to_the_contacts_page(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee(route('contacts'), false);
    }

    public function test_the_product_ctas_point_to_the_products_page_or_a_category(): void
    {
        $response = $this->get('/')->assertOk();

        // Карточки категорий на главной ведут на страницы категорий,
        // а не на обзорную страницу продукции.
        $response->assertSee(route('products.eggs'), false);
        $response->assertSee(route('products.chicken'), false);
    }

    public function test_legal_links_stay_inactive_without_a_real_page(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // На маршруты юридических документов подставляться нечего: их
        // текстов заказчик не передавал. В подвале остаются только сами
        // названия документов, без ссылок.
        $this->assertStringNotContainsString('/legal', $html);
        $this->assertStringContainsString('Политика конфиденциальности', $html);
        $this->assertStringContainsString('Согласие на обработку персональных данных', $html);
    }

    public function test_the_gazprombank_link_stays_inactive_without_a_real_url(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // URL площадки задаётся переменной SITE_GAZPROMBANK_URL. Пока она
        // не задана, пункт остаётся неактивным, а не указывает на
        // придуманный адрес.
        $this->assertStringContainsString('data-partner-pending', $html);
        $this->assertStringNotContainsString('https://', $html);
    }

    public function test_the_navigation_marks_the_current_page(): void
    {
        $this->get('/about')->assertOk()->assertSee('aria-current="page"', false);
    }

    // ------------------------------------------------------------------
    // 4. Текстовые страницы: контент и SEO
    // ------------------------------------------------------------------

    public function test_the_about_page_shows_the_approved_text(): void
    {
        $response = $this->get('/about')->assertOk();

        $response->assertSee('О компании');
        $response->assertSee('Продукты, которым хочется доверять');
    }

    public function test_the_about_page_invents_no_company_facts(): void
    {
        // Проверяется видимый текст, а не исходный HTML: в разметке есть
        // служебные строки вроде duration-200, и поиск по сырому ответу
        // находил бы «200» в имени CSS-класса.
        $text = $this->visibleText($this->get('/about')->assertOk());

        // Ни одного выдуманного факта о компании: ни дат основания, ни
        // объёмов, ни численности сотрудников, ни географии, ни наград,
        // ни сертификатов.
        $forbidden = [
            'основан',
            'основана',
            'лет назад',
            'сотрудник',
            'человек работает',
            'тыс.',
            'тысяч',
            'млн',
            'сертификат',
            'награжд',
            'поставки в',
            'география',
        ];

        foreach ($forbidden as $word) {
            $this->assertStringNotContainsString(
                $word,
                $text,
                'Страница «О компании» не должна содержать неподтверждённый факт: '.$word,
            );
        }
    }

    public function test_the_quality_page_shows_the_four_principles(): void
    {
        $response = $this->get('/quality')->assertOk();

        $response->assertSee('Качество');
        $response->assertSee('Контроль');
        $response->assertSee('Безопасность');
        $response->assertSee('Свежесть');
        $response->assertSee('Стабильность');
    }

    public function test_the_quality_page_makes_no_unverified_product_claims(): void
    {
        $text = $this->visibleText($this->get('/quality')->assertOk());

        // Запрещённые формулировки: обещаний, которых нет в исходных
        // данных заказчика, на странице быть не должно.
        $forbidden = [
            'гост',
            'iso ',
            'органическая',
            'органической',
            'без антибиотиков',
            'лабораторн',
            'сертификат',
            'награжд',
            'eco',
        ];

        foreach ($forbidden as $word) {
            $this->assertStringNotContainsString(
                $word,
                $text,
                'Страница «Качество» не должна содержать неподтверждённое утверждение: '.$word,
            );
        }
    }

    public function test_the_contacts_page_keeps_placeholder_contacts_as_text(): void
    {
        $response = $this->get('/contacts')->assertOk();

        $response->assertSee('Контакты');

        // Пока SITE_PHONE и SITE_EMAIL не заданы, выводятся заглушки из
        // конфига, и компонент показывает их текстом, без tel:/mailto:.
        $response->assertSee('[ТЕЛЕФОН]');
        $response->assertSee('[EMAIL]');
        $response->assertDontSee('tel:[ТЕЛЕФОН]', false);
        $response->assertDontSee('mailto:[EMAIL]', false);
    }

    public function test_the_contacts_page_creates_working_links_once_contacts_are_known(): void
    {
        config([
            'site.contacts.phone' => '+7 900 000-00-00',
            'site.contacts.email' => 'zakaz@example.org',
            'site.contacts.address' => 'г. Пример, ул. Примерная, 1',
            'site.contacts.schedule' => 'Пн–Пт, 9:00–18:00',
        ]);

        $response = $this->get('/contacts')->assertOk();

        $response->assertSee('tel:+79000000000', false);
        $response->assertSee('mailto:zakaz@example.org', false);
        $response->assertSee('г. Пример, ул. Примерная, 1');
        $response->assertSee('Пн–Пт, 9:00–18:00');
    }

    public function test_the_contacts_page_shows_a_real_form_instead_of_pretending_to_one(): void
    {
        // Раньше здесь стоял обратный тест — test_no_page_promises_a_form_
        // that_does_not_exist: он требовал, чтобы на странице вообще не
        // было <form>, потому что формы ещё не существовало. Форма
        // появилась (этап обратной связи), и требование сменилось на
        // противоположное: раз страница показывает форму, она обязана
        // отправляться по настоящему адресу, а не быть декорацией.
        $html = $this->get('/contacts')->assertOk()->getContent();

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('action="'.route('contacts.store').'"', $html);

        // При этом форма обычная, без Livewire: публичный сайт не тянет
        // клиентский JS, и обещать интерактивную отправку было бы враньём.
        $this->assertStringNotContainsString('wire:submit', $html);
    }

    // ------------------------------------------------------------------
    // 5. SEO: title, description, canonical, один h1
    // ------------------------------------------------------------------

    public function test_every_new_page_has_its_own_title(): void
    {
        $expected = [
            '/products' => 'Продукция',
            '/about' => 'О компании',
            '/quality' => 'Качество',
            '/contacts' => 'Контакты',
        ];

        foreach ($expected as $url => $title) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<title>'.$title.' — ', false);
        }
    }

    public function test_every_new_page_has_a_description_and_a_canonical(): void
    {
        foreach (['/products', '/about', '/quality', '/contacts'] as $url) {
            $response = $this->get($url)->assertOk();

            $response->assertSee('<meta name="description" content="', false);
            $response->assertSee('rel="canonical" href="'.url($url).'"', false);
        }
    }

    public function test_every_new_page_has_exactly_one_h1(): void
    {
        foreach (['/products', '/about', '/quality', '/contacts'] as $url) {
            $response = $this->get($url)->assertOk();

            $this->assertSame(
                1,
                substr_count($response->getContent(), '<h1'),
                'На странице '.$url.' должен быть ровно один h1',
            );
        }
    }

    public function test_the_header_reports_the_new_routes_as_registered(): void
    {
        // Страница строится на SiteLinks, который опирается на Route::has().
        // Если маршрут забыть, пункт молча останется неактивным, и тесты
        // навигации выше поймают это по data-nav-pending, а этот тест —
        // по самому списку маршрутов.
        foreach (['products.index', 'about', 'quality', 'contacts'] as $routeName) {
            $this->assertTrue(Route::has($routeName));
        }
    }

    // ------------------------------------------------------------------
    // 6. Данные базы страницы не трогают
    // ------------------------------------------------------------------

    public function test_the_public_pages_do_not_change_the_catalog(): void
    {
        $before = [
            'categories' => ProductCategory::count(),
            'products' => Product::count(),
            'with_image' => Product::whereNotNull('image')->count(),
        ];

        foreach (['/products', '/about', '/quality', '/contacts'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->assertSame($before['categories'], ProductCategory::count());
        $this->assertSame($before['products'], Product::count());
        $this->assertSame($before['with_image'], Product::whereNotNull('image')->count());
    }
}
