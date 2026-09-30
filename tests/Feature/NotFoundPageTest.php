<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Страница ошибки 404 собственной вёрстки.
 *
 * До появления resources/views/errors/404.blade.php Laravel отдавал
 * vendor-шаблон minimal.blade.php: там <html lang="en"> зашит литералом, а
 * текст приходит из __('Not Found'), ключа для которого в lang/ru нет, и
 * подставляется сама английская строка. Посетитель видел чужой язык и слово
 * «Laravel» на странице, которой он не выбирал.
 *
 * Проверки опираются на то, что видит посетитель: код ответа, язык
 * документа, единственный h1, русский текст, рабочие ссылки и отсутствие
 * технических следов. Никаких данных заказчика тест не требует: проверяется
 * отсутствие заглушек, а не их конкретные значения, поэтому тест останется
 * верным и после подстановки SITE_NAME.
 *
 * RefreshDatabase работает на SQLite :memory: из phpunit.xml, поэтому
 * тесты не трогают database/database.sqlite.
 */
final class NotFoundPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Адрес, которого на сайте заведомо нет.
     */
    private const MISSING_PATH = '/definitely-not-existing-page';

    private function missingPage(string $path = self::MISSING_PATH)
    {
        return $this->get($path);
    }

    public function test_an_unknown_url_answers_with_a_real_404(): void
    {
        $this->missingPage()->assertNotFound();
    }

    public function test_the_page_is_written_in_russian(): void
    {
        $this->missingPage()
            ->assertNotFound()
            ->assertSee('<html lang="ru"', escape: false);
    }

    public function test_the_page_has_exactly_one_heading(): void
    {
        $response = $this->missingPage();

        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $response->assertSee('<h1', escape: false);
    }

    public function test_the_page_says_in_russian_that_the_page_is_not_found(): void
    {
        $this->missingPage()
            ->assertSee('Страница не найдена')
            ->assertSee('Проверьте адрес');
    }

    public function test_the_page_offers_a_way_back_to_the_home_page(): void
    {
        $this->missingPage()
            ->assertSee('На главную')
            ->assertSee('href="'.route('home').'"', escape: false);
    }

    public function test_the_page_offers_a_way_to_the_catalog(): void
    {
        // Ссылка на каталог полезна посетителю, который искал товар, но
        // она не должна превращаться в 404, если маршрут каталога позже
        // исчезнет: кнопка гасится, а не ведёт в никуда.
        if (Route::has('products.index')) {
            $this->missingPage()->assertSee('href="'.route('products.index').'"', escape: false);
        }

        $this->assertTrue(true);
    }

    public function test_the_page_does_not_show_the_english_laravel_wording(): void
    {
        // Точная причина прежнего поведения: в vendor-шаблоне
        // minimal.blade.php стоит <html lang="en"> литералом, а заголовок
        // и текст — __('Not Found'), для которого нет ключа в lang/ru.
        $this->missingPage()->assertDontSee('Not Found');
    }

    public function test_the_page_does_not_mention_laravel(): void
    {
        $this->missingPage()->assertDontSee('Laravel');
    }

    public function test_the_page_does_not_leak_technical_details(): void
    {
        $content = $this->missingPage()->getContent();

        $this->assertStringNotContainsString('vendor/laravel', $content);
        $this->assertStringNotContainsString('Illuminate\\', $content);
        $this->assertStringNotContainsString('Stack trace', $content);
    }

    public function test_the_page_shows_no_unconfirmed_company_data(): void
    {
        // На странице ошибки нет шапки и подвала, поэтому подставленные в
        // них заглушки не должны появляться: [НАЗВАНИЕ КОМПАНИИ] на
        // странице ошибки выглядит как поломка.
        $this->missingPage()
            ->assertDontSee('[НАЗВАНИЕ КОМПАНИИ]')
            ->assertDontSee('[ТЕЛЕФОН]')
            ->assertDontSee('[EMAIL]')
            ->assertDontSee('[АДРЕС]')
            ->assertDontSee('[РЕЖИМ РАБОТЫ]');
    }

    public function test_the_page_is_kept_out_of_the_index(): void
    {
        // Ошибка не должна индексироваться: иначе в поисковой выдаче
        // появятся дубли страниц с кодом 404.
        $this->missingPage()->assertSee('noindex', escape: false);
    }

    public function test_the_page_uses_the_site_stylesheet(): void
    {
        // Оформление должно совпадать с публичным сайтом, а не быть
        // отдельным приглазительным экраном.
        $this->missingPage()->assertSee('/build/assets/', escape: false);
    }

    public function test_an_unknown_product_category_uses_the_same_page(): void
    {
        // Тот же обработчик обслуживает и несуществующие категории:
        // посетитель не должен получать для них отдельную страницу.
        $this->missingPage('/products/definitely-not-a-category')
            ->assertNotFound()
            ->assertSee('Страница не найдена');
    }
}
