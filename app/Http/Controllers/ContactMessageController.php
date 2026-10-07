<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Mail\ContactFormMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Приём публичной формы обратной связи и отправка заявки письмом.
 *
 * ЧТО ПРОИСХОДИТ ПРИ ОТПРАВКЕ
 *
 * Проверенные данные уходят письмом на адрес из env CONTACT_MAIL_TO
 * (config('mail.to')) и нигде не сохраняются: ни в базу, ни в файлы, ни в
 * лог. База данных для формы не используется — заявка живёт ровно до тех
 * пор, пока не уйдёт письмом.
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
 * Отправка может упасть по многим причинам: недоступен SMTP, отклонена
 * авторизация, не задан получатель. Ни одна из них не должна показать
 * посетителю технические подробности — адрес сервера, логин, текст ошибки
 * транспорта. Поэтому все исключения перехватываются здесь, возвращается
 * понятное сообщение из config/content.php, а подробности пишутся только в
 * серверный лог и без каких-либо учётных данных: пароль SMTP в код не
 * передаётся и в лог не попадает.
 *
 * @see StoreContactRequest
 * @see ContactFormMail
 */
final class ContactMessageController extends Controller
{
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
     * Отправить заявку письмом.
     *
     * Возвращает true, если письмо ушло, и false во всех остальных
     * случаях. Ложь здесь — не программная ошибка, а штатный сценарий
     * (нет получателя, недоступен SMTP), поэтому метод не бросает
     * исключений наверх: страница не должна превращаться в 500 из-за
     * почтового сервера.
     */
    private function deliver(StoreContactRequest $request): bool
    {
        $to = (string) config('mail.to');

        // Получатель настроен окружением. Пустое значение означает, что
        // отправлять некуда, — это сбой настройки, а не повод писать письмо
        // «в никуда» или показывать посетителю, чего не хватает.
        if ($to === '') {
            Log::warning('contact_form: recipient CONTACT_MAIL_TO is not configured');

            return false;
        }

        try {
            Mail::to($to)->send(new ContactFormMail(
                name: (string) $request->input('name'),
                phone: (string) $request->input('phone'),
                email: $request->filled('email') ? (string) $request->input('email') : null,
                message: (string) $request->input('message'),
            ));

            return true;
        } catch (Throwable $exception) {
            // Пишем только то, что помогает найти причину на сервере, и
            // только то, чего не может быть в сообщении об ошибке пароля:
            // исключение транспорта называет хост, порт и причину отказа,
            // но не содержит пароль — он не передаётся в исключения и не
            // берётся из конфигурации этим кодом.
            Log::warning('contact_form: mail delivery failed', [
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
