<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Приём публичной формы обратной связи и пересылка заявки в Web3Forms.
 *
 * КАК УХОДИТ ЗАЯВКА
 *
 * Проверенные данные уходят POST-запросом в сервис Web3Forms
 * (https://api.web3forms.com/submit), который доставляет их письмом
 * владельцу сайта. Письмо собирает сам сервис, поэтому приложению не нужны
 * ни SMTP, ни своя сборка HTML. Заявка нигде не сохраняется: ни в базу,
 * ни в файлы, ни в лог.
 *
 * КЛЮЧ ACCESS KEY
 *
 * Access Key берётся из конфигурации services.web3forms (переменная
 * окружения WEB3FORMS_ACCESS_KEY), а не из кода, и уходит только полем
 * access_key в теле запроса. В лог он не попадает ни при каких
 * обстоятельствах: ни тело запроса, ни заголовки не логируются.
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
 * Web3Forms может отказать (неверный ключ, превышение лимита) или оказаться
 * недоступным. Успехом считается только ответ с флагом success=true, все
 * остальные ответы и исключения — сбой. Причина ни в коем случае не
 * показывается посетителю в сыром виде: ему достаётся понятное сообщение из
 * config/content.php, а в серверный лог пишется только HTTP-статус и
 * безопасное описание — без access key и без адресов.
 *
 * @see StoreContactRequest
 */
final class ContactMessageController extends Controller
{
    /**
     * Конечная точка Web3Forms для пересылки заявки.
     */
    private const WEB3FORMS_ENDPOINT = 'https://api.web3forms.com/submit';

    /**
     * Тема письма, которое владельцу отправит Web3Forms.
     */
    public const SUBJECT = 'Новая заявка с сайта Chicken site';

    /**
     * Поле from_name для Web3Forms — «имя отправителя» письма.
     */
    public const FROM_NAME = 'Chicken site';

    /**
     * Обработать отправку формы.
     *
     * Проверка полей уже выполнена: если форма заполнена неверно, до
     * метода управление не дойдёт, и Laravel сам вернёт посетителя на
     * /contacts с ошибками и введёнными значениями. Заявка в этом случае
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
     * Переслать заявку в Web3Forms.
     *
     * Возвращает true, если API подтвердил приём (success=true), и false во
     * всех остальных случаях. Ложь здесь — не программная ошибка, а штатный
     * сценарий (пустой access key, отказ или недоступность API), поэтому
     * метод не бросает исключений наверх: страница не должна превращаться
     * в 500 из-за почтового сервиса.
     */
    private function deliver(StoreContactRequest $request): bool
    {
        $accessKey = (string) config('services.web3forms.access_key');

        // Ключ задаётся окружением. Пустое значение означает сбой настройки,
        // а не повод слать заявку «в никуда» или показывать посетителю, чего
        // именно не хватает. Подробности — только в лог.
        if ($accessKey === '') {
            Log::warning('contact_form: web3forms is not configured (WEB3FORMS_ACCESS_KEY is empty)');

            return false;
        }

        try {
            $response = Http::asForm()->post(self::WEB3FORMS_ENDPOINT, [
                'access_key' => $accessKey,
                'subject' => self::SUBJECT,
                'name' => (string) $request->input('name'),
                'phone' => (string) $request->input('phone'),
                'message' => (string) $request->input('message'),
                'from_name' => self::FROM_NAME,
            ]);

            if ($response->successful() && $response->json('success') === true) {
                return true;
            }

            // В лог кладём только статус и текст, который прислал Web3Forms.
            // Ключа здесь нет: он живёт в теле запроса и в лог не попадает.
            Log::warning('contact_form: web3forms rejected the submission', [
                'status' => $response->status(),
                'error' => (string) $response->json('message', ''),
            ]);

            return false;
        } catch (Throwable $exception) {
            // Перехватываются и сетевые сбои (ConnectionException), и любые
            // неожиданные ошибки клиента. Класс и текст исключения нужны,
            // чтобы найти причину на сервере, но в них нет access key.
            Log::warning('contact_form: web3forms request failed', [
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
