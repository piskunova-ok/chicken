<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Mail\ContactFormMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Приём публичной формы обратной связи и отправка заявки письмом.
 *
 * КАК УХОДИТ ЗАЯВКА
 *
 * Проверенные данные кладутся в JSON-запрос к HTTPS API Resend
 * (POST api.resend.com/emails) и нигде не сохраняются: ни в базу, ни в
 * файлы, ни в лог. SMTP тут не участвует и участвовать не будет: хостинг
 * блокирует исходящие SMTP-порты, а это вызов завершался бы таймаутом.
 *
 * Ключ API, отправитель и получатель приходят из конфигурации
 * services.resend (переменные RESEND_API_KEY, RESEND_FROM_EMAIL,
 * CONTACT_MAIL_TO), а не из кода. Ключ уходит только в заголовок
 * Authorization запроса и в лог не попадает ни при каких обстоятельствах.
 *
 * ПОЧЕМУ ЛОВУШКА ПРОВЕРЯЕТСЯ ИМЕННО ЗДЕСЬ
 *
 * Сработавшая ловушка выглядит для посетителя ровно так же, как успешная
 * отправка: тот же редирект и то же сообщение. Различать их по ответу
 * нельзя, иначе бот поймёт, что его отсеяли. Поэтому ловушка обрабатывается
 * до вызова deliver() и ведёт себя как обычный успех.
 *
 * ЧТО УВИДИТ ПОСЕТИТЕЛЬ ПРИ СБОЕ
 *
 * Отправка может упасть по многим причинам: API недоступен, ключ неверный,
 * получатель не задан. Ни одна из них не должна показать посетителю
 * технические подробности — адреса API, ключа, текста ответа. Поэтому все
 * исключения и не-2xx ответы перехватываются здесь, возвращается понятное
 * сообщение из config/content.php, а в серверный лог пишутся только HTTP
 * статус и безопасное описание ошибки. Ключ API в код запроса не попадает
 * и логироваться не может.
 *
 * @see StoreContactRequest
 * @see ContactFormMail
 */
final class ContactMessageController extends Controller
{
    /**
     * Конечная точка API Resend для отправки письма.
     */
    private const RESEND_ENDPOINT = 'https://api.resend.com/emails';

    /**
     * Обработать отправку формы.
     *
     * Проверка полей уже выполнена: если форма заполнена неверно, до
     * метода управление не дойдёт, и Laravel сам вернёт посетителя на
     * /contacts с ошибками и введёнными значениями. Письмо в этом случае
     * не отправляется — валидация стоит раньше контроллера.
     */
    public function store(StoreContactRequest $request): RedirectResponse
    {
        if (! $request->honeypotIsFilled() && ! $this->deliver($request)) {
            return redirect()
                ->route('contacts')
                ->with(StoreContactRequest::ERROR_KEY, (string) config('content.contact_form.mail_failed'));
        }

        return redirect()
            ->route('contacts')
            ->with(StoreContactRequest::STATUS_KEY, (string) config('content.contact_form.flash'));
    }

    /**
     * Отправить заявку письмом через API Resend.
     *
     * Возвращает true, если API принял письмо, и false во всех остальных
     * случаях. Ложь здесь — не программная ошибка, а штатный сценарий
     * (пустой ключ, недоступный API, отклонённый запрос), поэтому метод не
     * бросает исключений наверх: страница не должна превращаться в 500
     * из-за почтового сервиса.
     */
    private function deliver(StoreContactRequest $request): bool
    {
        $key = (string) config('services.resend.key');
        $from = (string) config('services.resend.from');
        $to = (string) config('services.resend.to');

        // Ключ и адреса задаются окружением. Пустое значение означает сбой
        // настройки, а не повод писать письмо «в никуда» или показывать
        // посетителю, чего именно не хватает. Подробности — только в лог.
        if ($key === '' || $from === '' || $to === '') {
            Log::warning('contact_form: resend is not configured (RESEND_API_KEY, RESEND_FROM_EMAIL or CONTACT_MAIL_TO is empty)');

            return false;
        }

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->asJson()
                ->post(self::RESEND_ENDPOINT, [
                    'from' => $from,
                    'to' => [$to],
                    'subject' => ContactFormMail::SUBJECT,
                    'html' => (new ContactFormMail(
                        name: (string) $request->input('name'),
                        phone: (string) $request->input('phone'),
                        email: $request->filled('email') ? (string) $request->input('email') : null,
                        message: (string) $request->input('message'),
                    ))->html(),
                ]);

            if ($response->successful()) {
                return true;
            }

            // В лог кладём только статус и текст ошибки, присланный Resend.
            // Ключ API в запрос и в ответ не попадает, а потому и в лог —
            // тем более: ни тело запроса, ни заголовки не логируются.
            Log::warning('contact_form: resend api rejected the email', [
                'status' => $response->status(),
                'error' => $response->json('message'),
            ]);

            return false;
        } catch (Throwable $exception) {
            // Перехватываются и сетевые сбои (ConnectionException), и любые
            // неожиданные ошибки клиента. Класс и текст исключения нужны,
            // чтобы найти причину на сервере, но в них нет ключа API.
            Log::warning('contact_form: resend api request failed', [
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
