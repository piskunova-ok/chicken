<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\ContactMessageController;
use App\Http\Requests\StoreContactRequest;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Публичная форма обратной связи на /contacts.
 *
 * ЧТО ПРОВЕРЯЕТ ЭТОТ ФАЙЛ
 *
 * 1. Форма действительно существует в разметке: это страница, на которой
 *    посетитель что-то отправляет, поэтому «форма есть» — факт, который
 *    должен подтверждаться тестом, а не предполагаться.
 * 2. Серверная проверка работает: обязательные поля, необязательная почта,
 *    формат адреса, согласие и пределы длины.
 * 3. Введённые значения переживают ошибку и возвращаются в форму.
 * 4. Ошибки показываются по-русски, с понятным названием поля.
 * 5. Защита от спама работает и не выдаёт себя: ловушка для ботов
 *    выглядит снаружи как успешная отправка, а превышение лимита даёт
 *    честный 429.
 * 6. Форма доступна с клавиатуры и экранным диктором: обязательные поля
 *    помечены, а результат отправки — успех или ошибка — получает фокус.
 * 7. CSRF не сломан: POST-маршрут остаётся в группе web вместе со
 *    стандартным middleware Laravel.
 * 8. Заявка уходит ровно одним POST-запросом к API Web3Forms: access key
 *    берётся из конфигурации, тема и имя отправителя фиксированы, поля
 *    формы (имя, телефон, сообщение) передаются дословно, а неверно
 *    заполненная форма не делает запроса.
 * 9. Сбой API (отказ или недоступность) показывает посетителю понятное
 *    сообщение и ни в коем случае не технические подробности.
 * 10. Письмо не оставляет следа в базе: ни новой таблицы, ни новой записи.
 *
 * ЧТО ЗДЕСЬ НЕ ПРОВЕРЯЕТСЯ
 *
 * Реальный API Web3Forms тестом не вызывается: Http::fake() подменяет
 * встроенный HTTP-клиент, и по сети ничего не уходит. Проверяется то, что
 * контроллер сформировал и направил правильный HTTPS-запрос — адрес,
 * метод, access key и поля заявки в теле. Доставка письма владельцу
 * происходит внутри аккаунта Web3Forms и остаётся за пределами теста.
 *
 * Само срабатывание CSRF тоже не проверяется запросом: Laravel пропускает
 * его во время runningUnitTests(), поэтому тест на middleware-конфигурацию
 * честнее, чем тест на 419, который прошёл бы на любой конфигурации.
 *
 * RefreshDatabase работает на SQLite :memory: из phpunit.xml, поэтому тесты
 * не трогают рабочую базу database/database.sqlite.
 */
final class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Сколько отправок с одного IP проходит до 429.
     *
     * Число повторяет throttle:5,1 в routes/web.php. Расхождение поймает
     * сам тест превышения лимита, поэтому молча разъехаться они не смогут.
     */
    private const RATE_LIMIT = 5;

    /**
     * Эндпоинт API Web3Forms.
     */
    private const WEB3FORMS_ENDPOINT = 'https://api.web3forms.com/submit';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProductCatalogSeeder::class);

        // «Лишний» HTTP-запрос без заглушки — ошибка теста, а не поход в
        // сеть. Тесты, которым нужен успешный ответ API, подменяют клиент
        // сами, поэтому глобальная заглушка здесь не ставится: она
        // перехватывала бы и заглушки отдельных тестов (Laravel добавляет
        // фабрики заглушек в одну очередь, и первая выигрывает).
        Http::preventStrayRequests();
    }

    // ------------------------------------------------------------------
    // 1. Страница и разметка формы
    // ------------------------------------------------------------------

    public function test_the_contacts_page_responds_with_200(): void
    {
        $this->get('/contacts')->assertOk();
    }

    public function test_the_contacts_page_really_renders_a_form_posting_to_the_store_route(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('action="'.route('contacts.store').'"', $html);
        $this->assertStringContainsString('method="POST"', $html);

        // Ровно одна форма: подвал и шапка своих форм не добавляют, иначе
        // тест «форма есть» проходил бы вовсе не про ту форму.
        $this->assertSame(1, substr_count($html, '<form'));
    }

    public function test_the_form_carries_a_csrf_token(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        // @csrf печатает скрытое поле с токеном. Проверяется именно разметка,
        // а не факт сессии: без поля форма отклонилась бы с 419.
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('type="hidden"', $html);
    }

    public function test_the_store_route_stays_inside_the_web_middleware_group(): void
    {
        $route = app(Router::class)->getRoutes()->getByName('contacts.store');

        $this->assertNotNull($route, 'Маршрут contacts.store должен существовать.');

        // Почему проверяется конфигурация, а не сам отказ: Laravel пропускает
        // проверку CSRF во время runningUnitTests(), поэтому Feature-тест с
        // POST без токена прошёл бы и на полностью незащищённой форме. Такой
        // тест ничего не доказывал бы. А вот если POST-маршрут вынесли из
        // группы web — падение будет настоящим.
        $this->assertContains(
            'web',
            $route->gatherMiddleware(),
            'POST /contacts обязан оставаться в группе web, иначе пропадёт CSRF-защита.',
        );
    }

    public function test_the_web_group_still_contains_the_standard_csrf_middleware(): void
    {
        $webGroup = app(Router::class)->getMiddlewareGroups()['web'] ?? [];

        // is_a с третьим аргументом ловит и подклассы: PreventRequestForgery
        // в Laravel имеет устаревшие псевдонимы ValidateCsrfToken и
        // VerifyCsrfToken, и любой из них защищает форму так же.
        $forgeryMiddleware = array_values(array_filter(
            $webGroup,
            static fn (string $middleware): bool => is_string($middleware)
                && is_a($middleware, PreventRequestForgery::class, true),
        ));

        $this->assertNotEmpty(
            $forgeryMiddleware,
            'В группе web должен оставаться стандартный CSRF middleware Laravel. Ничего своего не создаём и не отключаем.',
        );
    }

    public function test_the_mandatory_fields_are_marked_as_required(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        // required и aria-required="true" нужны программам доступности: без них
        // экранный диктор узнаёт об обязательности поля только после неудачной
        // отправки. Саму форму они не проверяют — у формы стоит novalidate, и
        // источником истины остаётся серверная валидация.
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

        // Почта объявлена необязательной в подсказке и в правиле nullable.
        // Обязательность в разметке противоречила бы и тому, и другому.
        $this->assertTagLacksAttribute($tag, 'required');
        $this->assertTagLacksAttribute($tag, 'aria-required="true"');
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
    }

    public function test_the_form_does_not_ask_for_commercial_details(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        // Поля, которых заказчик не просил. Форма обратной связи — не анкета:
        // требовать компанию, ИНН или цену без данных о заказчике значило бы
        // придумать лишние требования к посетителю.
        foreach ([
            'company', 'inn', 'position', 'order', 'volume',
            'category', 'price', 'quantity', 'comment',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                'name="'.$forbidden.'"',
                $html,
                'Форма не должна содержать поле '.$forbidden,
            );
        }
    }

    public function test_the_form_keeps_exactly_one_h1_on_the_page(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        // Заголовок формы — h2, поэтому единственный h1 остаётся в hero.
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_the_consent_label_is_plain_text_without_a_legal_link(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        $this->assertStringContainsString('Я согласен(на) на обработку персональных данных', $html);

        // Страницы политики конфиденциальности у проекта нет. Ссылка на
        // несуществующий адрес привела бы посетителя на 404, а придумывать
        // юридический документ нельзя, поэтому подпись остаётся текстом.
        $this->assertStringNotContainsString('/legal', $html);
        $this->assertStringNotContainsString('/privacy', $html);
    }

    public function test_every_configured_form_text_really_reaches_the_page(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        $config = (array) config('content.contact_form', []);

        // flash и error_summary проверяются в конце теста: они появляются
        // только после отправки, а на обычном GET их нет. Всё остальное
        // обязано быть на странице: иначе в конфиге остаётся мёртвый ключ,
        // который написан, но никому не показывается. Так однажды в этой же
        // форме остались невыведенные placeholder-ы, и тест на это не смотрел.
        $expected = [
            $config['eyebrow'] ?? null,
            $config['title'] ?? null,
            $config['lead'] ?? null,
            $config['honeypot_label'] ?? null,
            $config['consent'] ?? null,
            $config['submit'] ?? null,
            $config['note'] ?? null,
        ];

        foreach ((array) ($config['fields'] ?? []) as $field) {
            $field = (array) $field;
            $expected[] = $field['label'] ?? null;
            $expected[] = $field['placeholder'] ?? null;
            $expected[] = $field['hint'] ?? null;
        }

        foreach (array_filter($expected, static fn (?string $text): bool => filled($text)) as $text) {
            $this->assertStringContainsString(
                e($text),
                $html,
                'Текст из config/content.php не попал в разметку: '.$text,
            );
        }

        // Сообщение об успехе видно после правильно заполненной формы.
        $this->fakeSuccessfulSubmission();

        $this->from('/contacts')
            ->followingRedirects()
            ->post('/contacts', $this->validPayload())
            ->assertOk()
            ->assertSee((string) ($config['flash'] ?? ''));

        // Заголовок сводки ошибок — после формы с ошибками.
        $this->from('/contacts')
            ->followingRedirects()
            ->post('/contacts', $this->validPayload(['message' => '']))
            ->assertOk()
            ->assertSee((string) ($config['error_summary'] ?? ''));
    }

    // ------------------------------------------------------------------
    // 2. Успешная отправка
    // ------------------------------------------------------------------

    public function test_a_correct_submission_redirects_back_to_the_contacts_page(): void
    {
        $response = $this->post('/contacts', $this->validPayload());

        $response->assertRedirect(route('contacts'));
        $response->assertSessionHasNoErrors();
    }

    public function test_a_correct_submission_shows_the_success_message(): void
    {
        $this->fakeSuccessfulSubmission();

        $response = $this->post('/contacts', $this->validPayload());

        $response->assertSessionHas(StoreContactRequest::STATUS_KEY);

        $expected = (string) config('content.contact_form.flash');
        $this->assertNotSame('', $expected);
        $response->assertSessionHas(StoreContactRequest::STATUS_KEY, $expected);

        // Сообщение действительно показывается на странице, а не просто
        // лежит в сессии.
        $this->get('/contacts')->assertOk()->assertSee($expected);
    }

    public function test_the_status_message_states_that_the_message_was_sent_without_any_technical_details(): void
    {
        $this->fakeSuccessfulSubmission();

        $response = $this->post('/contacts', $this->validPayload());

        $message = (string) session(StoreContactRequest::STATUS_KEY);

        $this->assertMatchesRegularExpression('/[а-яё]/iu', $message);

        // Письмо уходит по-настоящему, поэтому сообщение имеет полное
        // право сказать это прямо — но только это. Показывать посетителю
        // адрес сервера, почту отправителя или текст ошибки транспорта
        // незачем: ему достаточно знать, что сообщение ушло.
        $this->assertStringContainsString('отправлено', mb_strtolower($message));

        foreach (['smtp', '@', 'mail.ru', 'exception', 'password', '465'] as $technical) {
            $this->assertStringNotContainsString(
                mb_strtolower($technical),
                mb_strtolower($message),
                'Сообщение об успехе не должно содержать технических подробностей: '.$technical,
            );
        }

        $response->assertSessionHasNoErrors();
    }

    public function test_the_submitted_values_are_not_reflected_back_after_a_correct_submission(): void
    {
        $this->fakeSuccessfulSubmission();

        $this->post('/contacts', $this->validPayload());

        // Форма очистилась: значения вернулись бы только при ошибке.
        $this->get('/contacts')->assertOk()->assertDontSee('Подскажите, какие позиции есть в наличии.');
    }

    // ------------------------------------------------------------------
    // 3. Серверная валидация
    // ------------------------------------------------------------------

    public function test_the_name_is_required(): void
    {
        $this->post('/contacts', $this->validPayload(['name' => '']))
            ->assertSessionHasErrors('name');
    }

    public function test_the_phone_is_required(): void
    {
        $this->post('/contacts', $this->validPayload(['phone' => '']))
            ->assertSessionHasErrors('phone');
    }

    public function test_the_message_is_required(): void
    {
        $this->post('/contacts', $this->validPayload(['message' => '']))
            ->assertSessionHasErrors('message');
    }

    public function test_the_email_is_optional(): void
    {
        // Пустая почта проходит: правило nullable, а не required.
        $this->post('/contacts', $this->validPayload(['email' => '']))
            ->assertSessionHasNoErrors();
    }

    public function test_a_malformed_email_is_rejected(): void
    {
        $this->post('/contacts', $this->validPayload(['email' => 'не адрес']))
            ->assertSessionHasErrors('email');
    }

    public function test_the_consent_is_required(): void
    {
        $this->post('/contacts', $this->validPayload(['consent' => null]))
            ->assertSessionHasErrors('consent');
    }

    public function test_the_field_lengths_are_limited(): void
    {
        $this->post('/contacts', $this->validPayload([
            'name' => str_repeat('я', StoreContactRequest::limitFor('name') + 1),
            'phone' => str_repeat('9', StoreContactRequest::limitFor('phone') + 1),
            'email' => str_repeat('a', StoreContactRequest::limitFor('email')).'@example.org',
            'message' => str_repeat('щ', StoreContactRequest::limitFor('message') + 1),
        ]))->assertSessionHasErrors(['name', 'phone', 'email', 'message']);
    }

    public function test_the_length_limits_match_the_rules(): void
    {
        // Предел в разметке и в правилах обязан совпадать, иначе браузер
        // упрётся в свой maxlength раньше сервера, и посетитель получит
        // ошибку, которую невозможно объяснить.
        //
        // Ожидаемые значения берутся из StoreContactRequest::limitFor() —
        // того же источника, которым пользуется сама форма. Числа не
        // продублированы, поэтому смена лимита, например name со 100 на 120,
        // не требует правки теста: он проверяет согласованность, а не число.
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        foreach (['name', 'phone', 'email', 'message'] as $field) {
            $limit = StoreContactRequest::limitFor($field);

            $this->assertNotNull($limit, 'Для поля '.$field.' должен быть задан предел длины.');

            // Привязка к конкретному полю, а не к странице целиком: иначе
            // maxlength="100" от одного поля удовлетворил бы проверку для
            // всех остальных.
            $this->assertMatchesRegularExpression(
                '/<(?:input|textarea)\b[^>]*name="'.$field.'"[^>]*maxlength="'.$limit.'"/',
                $html,
                'maxlength в разметке поля '.$field.' должен совпадать с правилом валидации.',
            );
        }
    }

    public function test_the_entered_values_survive_a_validation_error(): void
    {
        $payload = $this->validPayload([
            'name' => 'Иван Петров',
            'phone' => '+7 900 111-22-33',
            'message' => '',
        ]);

        // from('/contacts') повторяет то, что делает браузер: при ошибке
        // Laravel возвращает посетителя «назад». Без предыдущего GET
        // «назад» — это главная, и проверять было бы не ту страницу.
        $this->from('/contacts')
            ->post('/contacts', $payload)
            ->assertRedirect(route('contacts'))
            ->assertSessionHasErrors('message')
            ->assertSessionHasInput('name', 'Иван Петров')
            ->assertSessionHasInput('phone', '+7 900 111-22-33');

        // Значения возвращаются и обратно в форму: посетителю не приходится
        // набирать заново то, что он уже ввёл.
        $this->from('/contacts')
            ->followingRedirects()
            ->post('/contacts', $payload)
            ->assertOk()
            ->assertSee('Иван Петров')
            ->assertSee('+7 900 111-22-33');
    }

    public function test_the_consent_stays_checked_after_a_validation_error(): void
    {
        $this->from('/contacts')
            ->followingRedirects()
            ->post('/contacts', $this->validPayload(['message' => '']))
            ->assertOk()
            ->assertSee('checked', false);
    }

    public function test_the_validation_errors_are_written_in_russian_with_readable_field_names(): void
    {
        $payload = $this->validPayload([
            'name' => '',
            'phone' => '',
            'email' => 'не адрес',
            'message' => '',
            'consent' => null,
        ]);

        $this->from('/contacts')
            ->post('/contacts', $payload)
            ->assertSessionHasErrors(['name', 'phone', 'email', 'message', 'consent']);

        // Названия полей берутся из секции validation.attributes: посетитель
        // видит «имя», а не «name».
        $this->from('/contacts')
            ->followingRedirects()
            ->post('/contacts', $payload)
            ->assertOk()
            ->assertSee('Поле «имя» обязательно для заполнения.')
            ->assertSee('Поле «телефон» обязательно для заполнения.')
            ->assertSee('Поле «сообщение» обязательно для заполнения.')
            ->assertSee('Поле «электронный адрес» должно быть действительным электронным адресом.')
            ->assertSee('Поле «согласие на обработку персональных данных» должно быть принято.');
    }

    // ------------------------------------------------------------------
    // 4. Доступность результата отправки
    // ------------------------------------------------------------------

    public function test_a_plain_visit_leaves_the_focus_where_the_browser_puts_it(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        // Метка data-form-result есть только у результата отправки. На
        // обычном открытии страницы её нет — значит, скрипту нечего
        // фокусировать, и фокус никто не перехватывает.
        $this->assertStringNotContainsString('data-form-result', $html);
    }

    public function test_the_success_message_can_receive_the_focus(): void
    {
        $this->fakeSuccessfulSubmission();

        $html = (string) $this->from('/contacts')
            ->followingRedirects()
            ->post('/contacts', $this->validPayload())
            ->assertOk()
            ->getContent();

        // Одного role="status" недостаточно: результат приходит с новой
        // загрузкой страницы, а такую live-область экранный диктор обычно не
        // озвучивает. tabindex="-1" вместе с меткой data-form-result позволяет
        // перенести на блок фокус, и это срабатывает всегда.
        $this->assertMatchesRegularExpression(
            '/<p[^>]*id="contact-form-status"[^>]*data-form-result[^>]*role="status"[^>]*tabindex="-1"/',
            $html,
            'Сообщение об успехе должно получать фокус после отправки.',
        );
    }

    public function test_the_error_summary_appears_and_can_receive_the_focus(): void
    {
        $html = (string) $this->from('/contacts')
            ->followingRedirects()
            ->post('/contacts', $this->validPayload(['name' => '', 'message' => '']))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<div[^>]*id="contact-form-errors"[^>]*data-form-result[^>]*role="alert"[^>]*tabindex="-1"/',
            $html,
            'Сводка ошибок должна получать фокус после неудачной отправки.',
        );

        // Ссылки ведут прямо на поля с ошибками: после переноса фокуса
        // посетитель может перейти к нужному полю с клавиатуры.
        $this->assertStringContainsString('href="#contact-name"', $html);
        $this->assertStringContainsString('href="#contact-message"', $html);

        // Названия полей берутся из validation.attributes, а не из имён полей,
        // поэтому в списке «имя», а не «name».
        $this->assertMatchesRegularExpression('/>\s*имя\s*<\/a>/u', $html);
        $this->assertMatchesRegularExpression('/>\s*сообщение\s*<\/a>/u', $html);

        // Связи «поле — ошибка», которые были в разметке до этого этапа,
        // не сломаны.
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('aria-describedby="contact-name-error"', $html);
        $this->assertStringContainsString('aria-describedby="contact-message-error"', $html);
    }

    // ------------------------------------------------------------------
    // 5. Защита от спама
    // ------------------------------------------------------------------

    public function test_the_honeypot_is_hidden_from_people_and_removed_from_the_tab_order(): void
    {
        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        $this->assertStringContainsString('name="website"', $html);

        // Поле-ловушка не должно быть доступно ни посетителю, ни программам
        // доступности, ни клавиатуре: иначе оно мешало бы заполнению формы.
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringContainsString('left-[-9999px]', $html);

        // Именно скрытое текстовое поле, а не type="hidden": поля типа
        // hidden боты не заполняют, и ловушка перестала бы работать.
        $this->assertDoesNotMatchRegularExpression(
            '/<input[^>]*type="hidden"[^>]*name="website"/',
            $html,
        );
    }

    public function test_a_filled_honeypot_is_indistinguishable_from_a_successful_submission(): void
    {
        $before = $this->databaseSnapshot();

        $response = $this->post('/contacts', [
            'name' => 'Спамер',
            'phone' => '',
            'email' => 'не адрес',
            'message' => '',
            'consent' => null,
            'website' => 'http://spam.example',
        ]);

        // Тот же редирект и то же сообщение, что и у человека с правильной
        // формой. Никаких ошибок валидации: они выдали бы боту, какие поля
        // считаются настоящими, и ловушка раскрылась бы.
        $response->assertRedirect(route('contacts'));
        $response->assertSessionHas(StoreContactRequest::STATUS_KEY, (string) config('content.contact_form.flash'));
        $response->assertSessionHasNoErrors();

        $this->assertSame($before, $this->databaseSnapshot());
        Http::assertNothingSent();
    }

    public function test_an_empty_honeypot_does_not_interfere_with_a_real_submission(): void
    {
        $this->fakeSuccessfulSubmission();

        $this->post('/contacts', $this->validPayload(['website' => '']))
            ->assertRedirect(route('contacts'))
            ->assertSessionHasNoErrors();
    }

    public function test_the_rate_limit_answers_429_after_five_submissions(): void
    {
        $this->fakeSuccessfulSubmission();

        for ($attempt = 1; $attempt <= self::RATE_LIMIT; $attempt++) {
            $this->post('/contacts', $this->validPayload())
                ->assertRedirect(route('contacts'));
        }

        // Шестая отправка получает честный отказ, а не редирект с
        // сообщением об успехе: притворяться, что заявка принята, было бы
        // враньём посетителю.
        $this->post('/contacts', $this->validPayload())->assertStatus(429);
    }

    public function test_the_rate_limit_is_shared_by_the_whole_contact_form(): void
    {
        $this->fakeSuccessfulSubmission();
        // Лимит считается по адресу маршрута, а не по странице просмотра:
        // открытие /contacts не расходует попытки отправки.
        $this->get('/contacts')->assertOk();
        $this->get('/contacts')->assertOk();

        for ($attempt = 1; $attempt <= self::RATE_LIMIT; $attempt++) {
            $this->post('/contacts', $this->validPayload())->assertRedirect(route('contacts'));
        }

        $this->post('/contacts', $this->validPayload())->assertStatus(429);
    }

    // ------------------------------------------------------------------
    // 6. Пересылка через Web3Forms и база данных
    // ------------------------------------------------------------------

    public function test_a_submission_creates_no_table_and_writes_no_record(): void
    {
        $this->fakeSuccessfulSubmission();

        $before = $this->databaseSnapshot();

        $this->post('/contacts', $this->validPayload())->assertRedirect(route('contacts'));

        $this->assertSame(
            $before,
            $this->databaseSnapshot(),
            'Отправка формы не должна создавать таблицы и добавлять записи.',
        );
    }

    public function test_a_submission_does_not_create_a_requests_table(): void
    {
        $this->fakeSuccessfulSubmission();

        $tables = array_map(strtolower(...), Schema::getTableListing());

        foreach (['contact', 'request', 'message', 'lead', 'enquiry', 'callback', 'form'] as $fragment) {
            $this->assertSame(
                [],
                array_values(array_filter(
                    $tables,
                    static fn (string $table): bool => str_contains($table, $fragment),
                )),
                'Не должно появиться таблица заявок, а в названии не должно быть «'.$fragment.'».',
            );
        }
    }

    public function test_a_correct_submission_posts_exactly_one_submission_to_web3forms(): void
    {
        $this->fakeSuccessfulSubmission();

        $this->post('/contacts', $this->validPayload())
            ->assertRedirect(route('contacts'))
            ->assertSessionHas(StoreContactRequest::STATUS_KEY, (string) config('content.contact_form.flash'))
            ->assertSessionHasNoErrors();

        $requests = Http::recorded();

        $this->assertCount(1, $requests, 'Корректная отправка должна дать ровно один запрос к API.');

        /** @var Request $request */
        $request = $requests[0][0];

        $this->assertSame('POST', $request->method());
        $this->assertSame('https://api.web3forms.com/submit', $request->url());

        // Access key живёт в теле запроса и берётся из той же конфигурации
        // services.web3forms, что и в контроллере. Тест не хранит его
        // отдельно: иначе смена ключа в окружении здесь бы не заметилась.
        $payload = $request->data();

        $this->assertNotSame('', (string) config('services.web3forms.access_key'), 'Access key берётся из WEB3FORMS_ACCESS_KEY.');
        $this->assertSame((string) config('services.web3forms.access_key'), $payload['access_key']);
        $this->assertSame(ContactMessageController::SUBJECT, $payload['subject']);
        $this->assertSame(ContactMessageController::FROM_NAME, $payload['from_name']);

        // Поля формы передаются дословно: письмо собирает сам сервис.
        $this->assertSame('Иван Петров', $payload['name']);
        $this->assertSame('+7 900 000-00-00', $payload['phone']);
        $this->assertSame('Подскажите, какие позиции есть в наличии.', $payload['message']);
    }

    public function test_a_correct_submission_passes_user_input_verbatim_to_the_api(): void
    {
        $this->fakeSuccessfulSubmission();

        $this->post('/contacts', $this->validPayload([
            'name' => 'Иван <b>Петров</b>',
            'message' => 'Зайти «сегодня» & посчитать <5 позиций',
        ]));

        $payload = Http::recorded()[0][0]->data();

        // Web3Forms собирает письмо сам, поэтому приложение передаёт текст
        // как есть — разметки оно не создаёт и ничего не экранирует.
        $this->assertSame('Иван <b>Петров</b>', $payload['name']);
        $this->assertSame('Зайти «сегодня» & посчитать <5 позиций', $payload['message']);
    }

    public function test_an_invalid_submission_posts_nothing_and_reports_validation_errors(): void
    {
        $response = $this->post('/contacts', $this->validPayload([
            'name' => '',
            'phone' => '',
            'message' => '',
        ]));

        // Ошибки валидации, а не ошибка отправки: до deliver() дело не
        // доходит, поэтому сообщение из content.contact_form.mail_failed
        // здесь неуместно — это не сбой API, а неверно заполненная форма.
        $response->assertSessionHasErrors(['name', 'phone', 'message']);
        $response->assertSessionMissing(StoreContactRequest::ERROR_KEY);

        Http::assertNothingSent();
    }

    public function test_a_rejected_response_from_the_api_shows_a_plain_error_instead_of_a_success(): void
    {
        // API отвечает отказом — так же, как это случилось бы при неверном
        // access key или превышении лимита запросов. Сырой ответ не должен
        // дойти до посетителя.
        config(['services.web3forms.access_key' => 're_web3forms_secret_marker']);

        Http::fake([
            'https://api.web3forms.com/submit' => Http::response([
                'success' => false,
                'message' => 'Access key is invalid.',
            ], 403),
        ]);

        $response = $this->post('/contacts', $this->validPayload());

        $response->assertRedirect(route('contacts'));
        $response->assertSessionHas(
            StoreContactRequest::ERROR_KEY,
            (string) config('content.contact_form.mail_failed'),
        );

        // Успех и сбой никогда не показываются вместе: противоречие
        // посетитель прочитал бы как «письмо всё-таки ушло».
        $response->assertSessionMissing(StoreContactRequest::STATUS_KEY);

        $html = (string) $this->get('/contacts')->assertOk()->getContent();

        $expected = (string) config('content.contact_form.mail_failed');
        $this->assertNotSame('', $expected);
        $this->assertStringContainsString($expected, $html);

        // Сообщение об ошибке — тоже результат отправки, поэтому оно
        // получает метку и роль для экранного диктора, как и успех.
        $this->assertMatchesRegularExpression(
            '/<p[^>]*id="contact-form-mail-error"[^>]*data-form-result[^>]*role="alert"[^>]*tabindex="-1"/',
            $html,
            'Ошибка отправки должна получать фокус после отправки.',
        );

        // Ни адреса API, ни текста ответа, ни Access Key на странице нет:
        // всё это относится к серверу, а не к посетителю.
        foreach (['api.web3forms.com', 'Access key is invalid', 're_web3forms_secret_marker'] as $secret) {
            $this->assertStringNotContainsString($secret, $html, 'Страница не должна показывать технические подробности.');
        }

        // Запрос к API всё же ушёл — услуга ответила отказом.
        Http::assertSentCount(1);
    }

    public function test_a_response_without_success_true_is_treated_as_a_failure_even_on_http_200(): void
    {
        // Успехом считается только success=true, поэтому решает флаг, а не
        // код ответа: HTTP 200 без подтверждения — такой же сбой.
        Http::fake([
            'https://api.web3forms.com/submit' => Http::response([
                'success' => false,
                'message' => 'The selected "email" is invalid.',
            ]),
        ]);

        $this->post('/contacts', $this->validPayload())
            ->assertRedirect(route('contacts'))
            ->assertSessionHas(StoreContactRequest::ERROR_KEY, (string) config('content.contact_form.mail_failed'))
            ->assertSessionMissing(StoreContactRequest::STATUS_KEY);
    }

    public function test_a_connection_error_to_the_api_shows_a_plain_error_instead_of_a_success(): void
    {
        // Сетевой сбой — API недоступен. Посетитель видит ту же понятную
        // ошибку, что и при HTTP-отказе: причины делятся на серверные, а не
        // на «HTTP против сети».
        Http::fake([
            'https://api.web3forms.com/submit' => static function (): never {
                throw new ConnectionException('Connection to api.web3forms.com failed.');
            },
        ]);

        $this->post('/contacts', $this->validPayload())
            ->assertRedirect(route('contacts'))
            ->assertSessionHas(StoreContactRequest::ERROR_KEY, (string) config('content.contact_form.mail_failed'))
            ->assertSessionMissing(StoreContactRequest::STATUS_KEY);
    }

    public function test_a_delivery_failure_never_writes_the_access_key_to_the_log(): void
    {
        // Access key берётся из окружения. Кладём в него метку и смотрим,
        // не утащил ли кто-нибудь её в лог вместе с текстом ошибки.
        config(['services.web3forms.access_key' => 're_web3forms_secret_metka_kotoruyu_nelzya_logirovat']);

        $logged = [];

        Log::listen(static function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        Http::fake([
            'https://api.web3forms.com/submit' => Http::response([
                'success' => false,
                'message' => 'Access key is invalid.',
            ], 403),
        ]);

        $this->post('/contacts', $this->validPayload());

        $this->assertNotEmpty($logged, 'Сбой отправки должен попадать в серверный лог.');

        foreach ($logged as $entry) {
            $haystack = $entry->message.' '.json_encode($entry->context, JSON_UNESCAPED_UNICODE);

            $this->assertStringNotContainsString(
                're_web3forms_secret_metka_kotoruyu_nelzya_logirovat',
                $haystack,
                'Лог не должен содержать access key.',
            );
        }

        // Лог всё же полезен без ключа: статус и безопасное описание нужны
        // оператору, чтобы найти причину.
        $failures = array_values(array_filter(
            $logged,
            static fn (MessageLogged $event): bool => $event->message === 'contact_form: web3forms rejected the submission',
        ));

        $this->assertCount(1, $failures);
        $this->assertSame(403, $failures[0]->context['status'] ?? null);
        $this->assertSame('Access key is invalid.', $failures[0]->context['error'] ?? null);
    }

    public function test_a_rejected_submission_posts_nothing(): void
    {
        $this->post('/contacts', $this->validPayload(['email' => 'не адрес']));

        Http::assertNothingSent();
    }

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
     * Заглушка успешного ответа Web3Forms.
     *
     * Голый Http::fake() отвечает пустым 200, а успехом контроллер считает
     * только success=true в теле ответа — без него все «успешные» тесты
     * уходили бы в ветку ошибки. Конкретный ответ говорит тесту настоящую
     * интонацию API: «заявка принята».
     */
    private function fakeSuccessfulSubmission(): void
    {
        Http::fake([
            self::WEB3FORMS_ENDPOINT => Http::response(['success' => true]),
        ]);
    }

    /**
     * Корректно заполненная форма с возможностью точечной правки.
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Иван Петров',
            'phone' => '+7 900 000-00-00',
            'email' => 'ivan@example.org',
            'message' => 'Подскажите, какие позиции есть в наличии.',
            'consent' => '1',
            'website' => '',
        ], $overrides);
    }

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
     * Снимок снимается целиком, а не только по products: заявка могла бы
     * осесть в любой новой таблице, а персональные данные — в сессионной.
     * Сравнение снимков до и после отправки ловит и то, и другое.
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
