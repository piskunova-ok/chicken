<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\StoreContactRequest;
use Database\Seeders\ProductCatalogSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
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
 *    помечены, а результат отправки — успех или ошибки — получает фокус.
 * 7. CSRF не сломан: POST-маршрут остаётся в группе web вместе со
 *    стандартным middleware Laravel.
 * 8. Этап технический: ни письма, ни записи в базу, ни новой таблицы.
 *
 * ЧТО ЗДЕСЬ НЕ ПРОВЕРЯЕТСЯ
 *
 * Реальной отправки ещё нет, поэтому тест не может утверждать, что
 * посетитель получил письмо. Наоборот, пункт 8 специально следит, чтобы
 * письмо не ушло и данные не залегли в базу: пока канала связи нет,
 * обещание отправки было бы неправдой.
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

    public function test_a_correct_submission_shows_the_neutral_status_message(): void
    {
        $response = $this->post('/contacts', $this->validPayload());

        $response->assertSessionHas(StoreContactRequest::STATUS_KEY);

        $expected = (string) config('content.contact_form.flash');
        $this->assertNotSame('', $expected);
        $response->assertSessionHas(StoreContactRequest::STATUS_KEY, $expected);

        // Сообщение действительно показывается на странице, а не просто
        // лежит в сессии.
        $this->get('/contacts')->assertOk()->assertSee($expected);
    }

    public function test_the_status_message_never_claims_the_request_was_sent(): void
    {
        $response = $this->post('/contacts', $this->validPayload());

        $message = (string) session(StoreContactRequest::STATUS_KEY);

        $this->assertMatchesRegularExpression('/[а-яё]/iu', $message);

        // Главная осторожность этого этапа: формулировка не должна
        // обещать отправку, которой ещё не происходит.
        foreach ([
            'заявка отправлена',
            'отправлено',
            'мы отправили',
            'получили вашу',
            'ответим в течение',
            'свяжемся с вами',
        ] as $falsePromise) {
            $this->assertStringNotContainsString(
                mb_strtolower($falsePromise),
                mb_strtolower($message),
                'Сообщение не должно утверждать, что заявка отправлена: '.$falsePromise,
            );
        }

        $response->assertSessionHasNoErrors();
    }

    public function test_the_submitted_values_are_not_reflected_back_after_a_correct_submission(): void
    {
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
        $this->assertNoMailWasSent();
    }

    public function test_an_empty_honeypot_does_not_interfere_with_a_real_submission(): void
    {
        $this->post('/contacts', $this->validPayload(['website' => '']))
            ->assertRedirect(route('contacts'))
            ->assertSessionHasNoErrors();
    }

    public function test_the_rate_limit_answers_429_after_five_submissions(): void
    {
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
    // 6. Этап технический: ни письма, ни записи в базу
    // ------------------------------------------------------------------

    public function test_a_submission_creates_no_table_and_writes_no_record(): void
    {
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

    public function test_a_submission_sends_no_mail(): void
    {
        $this->post('/contacts', $this->validPayload())->assertRedirect(route('contacts'));

        $this->assertNoMailWasSent();
    }

    public function test_a_rejected_submission_sends_no_mail(): void
    {
        $this->post('/contacts', $this->validPayload(['email' => 'не адрес']));

        $this->assertNoMailWasSent();
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

    /**
     * Через реальный array-транспорт, а не через Mail::fake().
     *
     * Подмена почты обошла бы проверку: она ничего не отправляет по
     * определению, и тест прошёл бы даже при случайно появившемся
     * письме. Транспорт из phpunit.xml складывает письма в память, поэтому
     * здесь видно всё, что приложение действительно попыталось отправить.
     */
    private function assertNoMailWasSent(): void
    {
        $transport = Mail::mailer('array')->getSymfonyTransport();

        $this->assertInstanceOf(ArrayTransport::class, $transport);
        $this->assertCount(0, $transport->messages());
    }
}
