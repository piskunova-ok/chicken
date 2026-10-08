<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Публичная форма обратной связи на /contacts.
 *
 * ЧТО ПРОВЕРЯЕТ ЭТОТ ФАЙЛ
 *
 * 1. Форма существует в разметке и уходит POST-запросом НАПРЯМУЮ в
 *    Web3Forms (https://api.web3forms.com/submit): маршрута contacts.store
 *    нет, сервер Laravel в отправке не участвует, CSRF-токен не нужен.
 * 2. Access key и служебные поля (subject, from_name) лежат в скрытых
 *    input. Access key приходит из config/services.php (env
 *    WEB3FORMS_ACCESS_KEY), а не зашит в Git: тест подменяет конфиг меткой
 *    и проверяет, что в HTML оказалась именно она.
 * 3. Поля формы — name, phone, email, message и согласие — с правильной
 *    обязательностью и пределами длины. Ловушка для ботов убрана: её больше
 *    некому проверять на сервере, а антиспам формы теперь отвечает Web3Forms.
 * 4. Результат отправки рендерится заранее скрытым и готов принимать фокус:
 *    клиентский скрипт лишь снимает hidden и фокусирует блок. На обычном
 *    открытии страницы ничего не показывается.
 * 5. Страница ничего не пишет в базу, а другие страницы и админка не
 *    задеты.
 *
 * ЧТО ЗДЕСЬ НЕ ПРОВЕРЯЕТСЯ
 *
 * Сам HTTP-запрос к Web3Forms выполняет JavaScript в браузере посетителя
 * (resources/js/app.js), и PHPUnit его не исполняет. Проверяется то, что
 * разметка и конфигурация гарантируют такой запрос: правильный endpoint в
 * action, скрытый access key из окружения, служебные поля и видимые поля
 * с нужными именами. Приём заявки Web3Forms отвечает флагом success=true в
 * JSON — этот протокол описан в конфиге и в комментариях компонента.
 *
 * RefreshDatabase работает на SQLite :memory: из phpunit.xml, поэтому тесты
 * не трогают рабочую базу database/database.sqlite.
 */
final class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductCatalogSeeder::class);
    }

    // ------------------------------------------------------------------
    // 1. Страница и разметка формы
    // ------------------------------------------------------------------

    public function test_the_contacts_page_responds_with_200(): void
    {
        $this->get('/contacts')->assertOk();
    }

    public function test_the_contacts_page_really_renders_a_form_posting_to_web3forms(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('method="POST"', $html);
        $this->assertStringContainsString('action="https://api.web3forms.com/submit"', $html);
        $this->assertStringContainsString('data-contact-form', $html);

        // Ровно одна форма: подвал и шапка своих форм не добавляют, иначе
        // тест «форма есть» проходил бы вовсе не про ту форму.
        $this->assertSame(1, substr_count($html, '<form'));
    }

    public function test_the_form_has_no_csrf_token_and_needs_none(): void
    {
        // Форма постится прямо в Web3Forms: у нашего приложения маршрута
        // нет, значит, и токена для него не нужно.
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        $this->assertStringNotContainsString('name="_token"', $html);

        // novalidate убран: до отправки браузер сам проверит обязательные
        // поля, адрес почты и пределы длины.
        $this->assertStringNotContainsString('novalidate', $html);
    }

    public function test_the_form_offers_exactly_the_agreed_fields(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        foreach (['name', 'phone', 'email', 'message', 'consent'] as $field) {
            $this->assertStringContainsString(
                'name="'.$field.'"',
                $html,
                'В форме должно быть поле '.$field,
            );
        }

        // Ловушка для ботов убрана: проверять её больше некому — заявка
        // уходит из браузера, а не через сервер.
        $this->assertStringNotContainsString('name="website"', $html);
    }

    public function test_the_mandatory_fields_are_marked_as_required(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        // required и aria-required="true" нужны браузеру и программам
        // доступности: без них форма отправится с пустыми полями.
        foreach (['name', 'phone', 'message', 'consent'] as $field) {
            $tag = $this->openingTagFor($field, $html);

            $this->assertTagHasAttribute($tag, 'required');
            $this->assertTagHasAttribute($tag, 'aria-required="true"');
        }
    }

    public function test_the_optional_email_is_not_marked_as_required(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        $tag = $this->openingTagFor('email', $html);

        $this->assertTagLacksAttribute($tag, 'required');
        $this->assertTagLacksAttribute($tag, 'aria-required="true"');
    }

    public function test_maxlength_attributes_match_the_configured_limits(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();
        $fields = (array) config('content.contact_form.fields', []);

        foreach (['name', 'phone', 'email', 'message'] as $field) {
            $limit = (string) ($fields[$field]['maxlength'] ?? '');

            $this->assertNotSame(
                '',
                $limit,
                'У поля '.$field.' должен быть maxlength в config/content.php.',
            );

            $tag = $this->openingTagFor($field, $html);

            $this->assertStringContainsString(
                'maxlength="'.$limit.'"',
                $tag,
                'maxlength в разметке поля '.$field.' должен совпадать с конфигом.',
            );
        }
    }

    // ------------------------------------------------------------------
    // 2. Служебные скрытые поля Web3Forms
    // ------------------------------------------------------------------

    public function test_the_access_key_comes_from_the_configuration_and_is_rendered_once(): void
    {
        // Метка вместо настоящего ключа: тест не хранит секрета, но
        // доказывает, что значение взято из окружения, а не зашито в Git.
        config(['services.web3forms.access_key' => 're_web3forms_key_iz_okruzheniya']);

        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        $expected = '<input type="hidden" name="access_key" value="re_web3forms_key_iz_okruzheniya">';

        $this->assertSame(1, substr_count($html, $expected));
        $this->assertStringNotContainsString('WEB3FORMS_ACCESS_KEY', $html);
    }

    public function test_the_access_key_is_not_empty_in_the_test_environment(): void
    {
        // Реальный ключ в тестах не нужен, но пустота в окружении — это
        // сбой настройки: форма уехала бы в Web3Forms без идентификатора.
        $this->assertNotSame('', (string) config('services.web3forms.access_key'));
    }

    public function test_the_hidden_web3forms_fields_carry_the_fixed_values(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        // Тема и «имя отправителя» письма фиксированы — сервис соберёт из
        // них письмо владельцу сайта.
        $this->assertStringContainsString(
            '<input type="hidden" name="subject" value="Новая заявка с сайта Chicken site">',
            $html,
        );
        $this->assertStringContainsString(
            '<input type="hidden" name="from_name" value="Chicken site">',
            $html,
        );
    }

    // ------------------------------------------------------------------
    // 3. Результат отправки
    // ------------------------------------------------------------------

    public function test_the_result_blocks_are_rendered_hidden_for_the_client_script(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        $text = (array) config('content.contact_form', []);

        // Успех и сбой живут в двух отдельных блоках с разными ролями:
        // status для «получилось», alert для «требуется реакция».
        $this->assertMatchesRegularExpression(
            '/<p[^>]*id="contact-form-status"[^>]*data-form-result[^>]*role="status"[^>]*tabindex="-1"[^>]*hidden/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/<p[^>]*id="contact-form-mail-error"[^>]*data-form-result[^>]*role="alert"[^>]*tabindex="-1"[^>]*hidden/',
            $html,
        );

        // Тексты приходят из конфига: клиентский скрипт лишь показывает
        // нужный блок, а источник строк остаётся один.
        $this->assertStringContainsString((string) ($text['flash'] ?? ''), $html);
        $this->assertStringContainsString((string) ($text['mail_failed'] ?? ''), $html);

        // На обычном открытии страницы ни одно из сообщений не видно.
        $this->assertMatchesRegularExpression(
            '/id="contact-form-status"[^>]*hidden/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/id="contact-form-mail-error"[^>]*hidden/',
            $html,
        );
    }

    public function test_the_error_block_never_shows_technical_details(): void
    {
        // Текст ошибки берётся из config/content.php (mail_failed) и не
        // содержит адреса API, ключа или текста ответа — сбои остаются на
        // стороне Web3Forms, посетителю говорить о них нечего.
        $message = (string) config('content.contact_form.mail_failed');

        foreach (['api.web3forms.com', '403', 'access_key'] as $technical) {
            $this->assertStringNotContainsString(
                $technical,
                $message,
                'Сообщение об ошибке не должно содержать технических подробностей: '.$technical,
            );
        }
    }

    // ------------------------------------------------------------------
    // 4. Отсутствие серверной стороны
    // ------------------------------------------------------------------

    public function test_there_is_no_server_route_for_the_form_submission(): void
    {
        // Заявка уходит из браузера: некуда и незачем постить данные
        // серверу, а значит, и принимать их некому.
        $this->assertFalse(Route::has('contacts.store'));
        $this->assertNull(app(Router::class)->getRoutes()->getByName('contacts.store'));
    }

    public function test_posting_to_the_contacts_address_returns_405(): void
    {
        // POST на /contacts отдаёт «метод не поддерживается», а не молча
        // глотает заявку: у приложения это страница только для чтения.
        $this->post('/contacts', [
            'name' => 'Иван Петров',
            'phone' => '+7 900 000-00-00',
            'message' => 'Подскажите, какие позиции есть в наличии.',
            'consent' => '1',
        ])->assertStatus(405);
    }

    public function test_opening_the_contacts_page_writes_nothing_to_the_database(): void
    {
        $before = $this->databaseSnapshot();

        $this->get('/contacts')->assertOk();

        $this->assertSame(
            $before,
            $this->databaseSnapshot(),
            'Страница формы не должна создавать таблицы и добавлять записи.',
        );
    }

    // ------------------------------------------------------------------
    // 5. Остальной сайт
    // ------------------------------------------------------------------

    public function test_the_public_pages_still_work_after_the_form_was_added(): void
    {
        foreach (['/products', '/products/eggs', '/products/chicken', '/about', '/quality', '/contacts'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_the_admin_panel_is_still_closed_to_guests(): void
    {
        // Форма публичная и не должна была ослабить вход в панель.
        $this->get('/admin')->assertRedirectContains('/admin/login');
    }

    // ------------------------------------------------------------------
    // Вспомогательное
    // ------------------------------------------------------------------

    /**
     * Открывающий тег конкретного поля формы.
     *
     * Нужен, чтобы проверять атрибут у нужного поля, а не у страницы. В
     * разметке несколько тегов с tabindex="-1" и с обязательностью, поэтому
     * поиск по всему документу не доказывал бы ничего.
     */
    private function openingTagFor(string $field, string $html): string
    {
        $pattern = '/<(?:input|textarea)\b[^>]*\bname="'.preg_quote($field, '/').'"[^>]*>/';

        $this->assertMatchesRegularExpression(
            $pattern,
            $html,
            'В разметке должно быть поле '.$field.'.',
        );

        preg_match($pattern, $html, $matches);

        return $matches[0];
    }

    /**
     * Атрибут есть именно в этом теге.
     *
     * Обязательный пробел перед именем — чтобы aria-required="true" не
     * прошёл проверку на required: без него искомое слово нашлось бы внутри
     * имени другого атрибута.
     */
    private function assertTagHasAttribute(string $tag, string $attribute): void
    {
        $this->assertMatchesRegularExpression(
            '/\s'.preg_quote($attribute, '/').'(?=\s|\/|>)/',
            $tag,
            'В теге должен быть атрибут '.$attribute.'.',
        );
    }

    /**
     * Атрибута в этом теге нет.
     */
    private function assertTagLacksAttribute(string $tag, string $attribute): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/\s'.preg_quote($attribute, '/').'(?=\s|\/|>)/',
            $tag,
            'В теге не должно быть атрибута '.$attribute.'.',
        );
    }

    /**
     * Список таблиц и число строк в каждой.
     *
     * Снимок снимается целиком: открытие страницы не должно ни создавать
     * новые таблицы, ни писать в существующие.
     *
     * @return array<string, int>
     */
    private function databaseSnapshot(): array
    {
        $snapshot = [];

        foreach (Schema::getTableListing() as $table) {
            $snapshot[$table] = DB::table($table)->count();
        }

        ksort($snapshot);

        return $snapshot;
    }
}