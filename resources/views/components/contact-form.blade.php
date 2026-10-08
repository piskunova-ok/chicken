{{--
    Публичная форма обратной связи для /contacts.

    Все тексты берутся из config/content.php (раздел contact_form), поэтому
    разметка не содержит ни одной собственной строки, которую пришлось бы
    искать по шаблону.

    КАК УХОДИТ ЗАЯВКА

    Форма постится POST-запросом прямо из браузера в Web3Forms
    (https://api.web3forms.com/submit): сервер Laravel в отправке не
    участвует, поэтому CSRF-токен не нужен. Access key и служебные поля
    лежат в скрытых input. Access key — это идентификатор формы Web3Forms,
    а не секретный ключ: бесплатный тариф принимает только запросы с
    origin браузера, и ключ должен быть виден в HTML. В Git он не
    хранится — значение подставляет Blade из config/services.php
    (env WEB3FORMS_ACCESS_KEY) при рендере.

    ВАЛИДАЦИЯ

    Валидация клиентская, браузерная: у формы НЕТ novalidate, поэтому
    перед отправкой браузер сам проверит required, type="email" и
    maxlength и покажет своё сообщение. Источником правил остаётся
    разметка, а числа maxlength лежат рядом с полями в config/content.php.

    КНОПКА — НАТИВНАЯ, А НЕ <x-button>

    Компонент button.blade.php жёстко выводит type="button" и не имеет
    параметра type. Передать type="submit" ему нельзя: атрибут попал бы в
    $attributes и оказался бы в разметке вторым, а браузер берёт первый —
    форма не отправилась бы. Правка x-button затронула бы двадцать
    существующих вызовов в шаблонах, поэтому здесь используется обычный
    <button type="submit"> с теми же классами primary-варианта. Когда у
    компонента появится параметр type, этот блок можно будет заменить.

    ОТПРАВКА И РЕЗУЛЬТАТ

    resources/js/app.js перехватывает submit (data-contact-form), шлёт
    FormData формы на endpoint из action и по ответу API показывает либо
    сообщение об успехе (success=true в ответе), либо понятную ошибку.
    Оба блока результата рендерятся заранее, скрытые, а JS лишь снимает
    hidden и переносит на показанный блок фокус. Сообщение об ошибке
    никогда не содержит причину сбоя: адрес сервиса и текст ответа
    посетителю не нужны и не должны просачиваться на страницу.
--}}
@php
    $text = (array) config('content.contact_form', []);
    $fields = (array) ($text['fields'] ?? []);
    $accessKey = (string) config('services.web3forms.access_key');
    $endpoint = 'https://api.web3forms.com/submit';

    $controlBase = 'w-full rounded-control border bg-surface px-4 py-3 text-body text-ink '
        .'transition-colors duration-200 ease-soft placeholder:text-ink-muted/70 '
        .'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
    $controlOk = $controlBase.' border-line-strong';

    /*
     * Текстовые поля идут циклом, а не тремя копиями одного блока:
     * подпись и подсказка при смене дизайна правятся в одном месте.
     * У каждого поля своя id, потому что label связан с input через for —
     * без него подпись не кликается и не читается программами доступности.
     */
    $textFields = [
        'name' => ['type' => 'text', 'autocomplete' => 'name', 'required' => true],
        'phone' => ['type' => 'tel', 'autocomplete' => 'tel', 'required' => true],
        'email' => ['type' => 'email', 'autocomplete' => 'email', 'required' => false],
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-card border border-line bg-canvas p-6 shadow-soft lg:p-8']) }}>
    <x-section-heading
        :eyebrow="$text['eyebrow'] ?? null"
        :title="$text['title'] ?? null"
        :lead="$text['lead'] ?? null"
        level="h2"
    />

    {{--
        РЕЗУЛЬТАТ ОТПРАВКИ — ДЛЯ КЛАВИАТУРЫ И ЭКРАННОГО ДИКТОРА

        Одного role="status" мало: результат появляется на той же странице
        без перезагрузки, а живое объявление live-области при добавлении
        контента диктор озвучивает не всегда. Поэтому оба блока дополнительно:

          * получают tabindex="-1", чтобы их можно было programmatic
            сфокусировать — фокус озвучивается всегда;
          * помечены data-form-result, а resources/js/app.js либо убирает
            с показанного блока hidden (при работе без фреймворков), либо
            переносит на него фокус.

        Блоки рендерятся всегда, но скрыты атрибутом hidden: обычный
        GET /contacts их не показывает и фокус никто не перехватывает
        (initFormResultFocus игнорирует скрытые элементы).
    --}}
    <p
        id="contact-form-status"
        data-form-result
        role="status"
        tabindex="-1"
        hidden
        class="mt-6 rounded-control border border-line-strong bg-surface px-4 py-3 text-small text-ink"
    >{{ $text['flash'] ?? '' }}</p>

    <p
        id="contact-form-mail-error"
        data-form-result
        role="alert"
        tabindex="-1"
        hidden
        class="mt-6 rounded-control border border-danger bg-surface px-4 py-3 text-small text-danger"
    >{{ $text['mail_failed'] ?? '' }}</p>

    <form
        method="POST"
        action="{{ $endpoint }}"
        data-contact-form
        class="relative mt-8"
    >
        {{--
            Служебные скрытые поля Web3Forms.

            access_key — идентификатор формы, значение берётся из окружения
            при рендере, а не из Git. subject и from_name фиксированы и задают
            тему и «имя отправителя» письма, которое владельцу соберёт сервис.
        --}}
        <input type="hidden" name="access_key" value="{{ $accessKey }}">
        <input type="hidden" name="subject" value="Новая заявка с сайта Chicken site">
        <input type="hidden" name="from_name" value="Chicken site">

        <div class="grid gap-5">
            @foreach ($textFields as $key => $meta)
                @php
                    $field = (array) ($fields[$key] ?? []);
                @endphp

                <div>
                    <label for="contact-{{ $key }}" class="block text-small font-semibold text-ink">
                        {{ $field['label'] ?? $key }}

                        @if (filled($field['hint'] ?? null))
                            <span class="font-normal text-ink-muted">{{ $field['hint'] }}</span>
                        @endif
                    </label>

                    <input
                        type="{{ $meta['type'] }}"
                        id="contact-{{ $key }}"
                        name="{{ $key }}"
                        placeholder="{{ $field['placeholder'] ?? '' }}"
                        maxlength="{{ $field['maxlength'] ?? '' }}"
                        autocomplete="{{ $meta['autocomplete'] }}"
                        class="{{ $controlOk }}"
                        @if ($meta['required'] ?? false) required aria-required="true" @endif
                    >
                </div>
            @endforeach

            <div>
                <label for="contact-message" class="block text-small font-semibold text-ink">
                    {{ $fields['message']['label'] ?? 'message' }}
                </label>

                <textarea
                    id="contact-message"
                    name="message"
                    rows="5"
                    placeholder="{{ $fields['message']['placeholder'] ?? '' }}"
                    maxlength="{{ $fields['message']['maxlength'] ?? '' }}"
                    required
                    aria-required="true"
                    class="{{ $controlOk }}"
                ></textarea>
            </div>

            {{--
                Согласие — обычный текст без ссылки: страницы политики
                конфиденциальности у проекта нет, и вести на несуществующий
                адрес нельзя. Подпись связана с чекбоксом через for, чтобы её
                можно было нажать. Поле обязательное, поэтому у него есть
                required и aria-required="true". Отмеченное значение уходит в
                Web3Forms полем consent.
            --}}
            <div>
                <div class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        id="contact-consent"
                        name="consent"
                        value="1"
                        required
                        aria-required="true"
                        class="mt-0.5 h-5 w-5 shrink-0 rounded-control accent-primary border-line-strong"
                    >

                    <label for="contact-consent" class="text-small text-ink-muted">
                        {{ $text['consent'] ?? '' }}
                    </label>
                </div>
            </div>

            {{--
                Классы повторяют primary-вариант components/button.blade.php,
                потому что сам компонент умеет только type="button".
            --}}
            <div>
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-control bg-primary px-6 py-3.5 text-body font-semibold text-ink-inverse transition-colors duration-200 ease-soft hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark sm:w-auto"
                >{{ $text['submit'] ?? 'Отправить сообщение' }}</button>

                <p class="mt-4 text-small text-ink-muted">{{ $text['note'] ?? '' }}</p>
            </div>
        </div>
    </form>
</div>
