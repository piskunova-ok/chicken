{{--
    Публичная форма обратной связи для /contacts.

    Все тексты берутся из config/content.php (раздел contact_form) и из
    lang/ru/validation.php (сообщения об ошибке), поэтому разметка не
    содержит ни одной собственной строки, которую пришлось бы искать по
    шаблону. Названия полей в ошибках тоже не продублированы: их Laravel
    подставляет из секции validation.attributes.

    КНОПКА — НАТИВНАЯ, А НЕ <x-button>

    Компонент button.blade.php жёстко выводит type="button" и не имеет
    параметра type. Передать type="submit" ему нельзя: атрибут попал бы в
    $attributes и оказался бы в разметке вторым, а браузер берёт первый —
    форма не отправилась бы. Правка x-button затронула бы двадцать
    существующих вызовов в шаблонах, поэтому здесь используется обычный
    <button type="submit"> с теми же классами primary-варианта. Когда у
    компонента появится параметр type, этот блок можно будет заменить.

    ВАЛИДАЦИЯ

    Проверка серверная, поэтому у формы стоит novalidate: иначе браузер
    остановил бы отправку сам и показал собственное сообщение на языке
    браузера, а не согласованный русский текст с подсказкой, какое поле
    исправить. Атрибуты maxlength, наоборот, оставлены — они подсказывают
    предел до отправки. Пределы взяты из StoreContactRequest::limitFor(),
    чтобы числа в разметке и в правилах не разошлись.

    ОБЯЗАТЕЛЬНОСТЬ ПОЛЕЙ

    У обязательных полей стоят required и aria-required="true", у почты их
    нет. Разметка ничего не проверяет — источник истины остаётся серверная
    валидация, а required и aria-required нужны программам доступности:
    без них экранный диктор узнаёт об обязательности поля только после
    неудачной отправки. Флаг required задан рядом с полем в $textFields и
    в разметке message и consent, а тесты файла проверяют и саму разметку,
    и реальный отказ сервера, поэтому «забытый» флаг будет замечен.
--}}
@php
    use App\Http\Requests\StoreContactRequest;

    $text = (array) config('content.contact_form', []);
    $fields = (array) ($text['fields'] ?? []);

    $status = session(StoreContactRequest::STATUS_KEY);

    $controlBase = 'w-full rounded-control border bg-surface px-4 py-3 text-body text-ink '
        .'transition-colors duration-200 ease-soft placeholder:text-ink-muted/70 '
        .'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent';
    $controlOk = $controlBase.' border-line-strong';
    $controlError = $controlBase.' border-danger';

    /*
     * Текстовые поля идут циклом, а не четырьмя копиями одного блока:
     * подпись, ошибка и подсказка при смене дизайна правятся в одном месте.
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

        Одного role="status" мало: результат появляется при перезагрузке
        страницы, а такую live-область экранный диктор обычно не озвучивает,
        потому что при обычной навигации содержимое документа появляется
        вместе с самим документом. Поэтому результат дополнительно:

          * получает tabindex="-1", чтобы его можно было programmatic
            сфокусировать — фокус озвучивается всегда;
          * помечен data-form-result, и resources/js/app.js переносит на него
            фокус после загрузки страницы.

        data-form-result есть только у этих двух блоков, а они рендерятся
        исключительно после отправки формы. Обычный GET /contacts не
        содержит ни одного из них, поэтому фокус никто не перехватывает.
    --}}
    @if (filled($status))
        <p
            id="contact-form-status"
            data-form-result
            role="status"
            tabindex="-1"
            class="mt-6 rounded-control border border-line-strong bg-surface px-4 py-3 text-small text-ink"
        >{{ $status }}</p>
    @endif

    @if ($errors->any())
        <div
            id="contact-form-errors"
            data-form-result
            role="alert"
            tabindex="-1"
            class="mt-6 rounded-control border border-danger bg-surface px-4 py-3"
        >
            <h3 class="text-small font-semibold text-ink">{{ $text['error_summary'] ?? '' }}</h3>

            {{--
                Ссылки ведут на поля с ошибками: их id совпадают с именами
                полей (contact-name, contact-phone, contact-email,
                contact-message, contact-consent), поэтому переход работает
                и для текстовых полей, и для textarea, и для чекбокса.
            --}}
            <ul class="mt-2 space-y-1 text-small text-danger">
                @foreach (array_keys($errors->getMessages()) as $invalidField)
                    <li>
                        <a href="#contact-{{ $invalidField }}" class="underline">
                            {{ __('validation.attributes.'.$invalidField) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ route('contacts.store') }}"
        novalidate
        class="relative mt-8"
    >
        @csrf

        {{--
            Ловушка для ботов. Поле называется нейтрально, чтобы автозаполнение
            и «тупые» боты его заполняли, и при этом:

              * вынесено за пределы экрана, а не скрыто через hidden или
                display:none — такие поля боты пропускают;
              * обёрнуто в aria-hidden, чтобы поле не читалось программами
                доступности;
              * tabindex="-1", чтобы при переходе с клавиатуры фокус на нём
                не останавливался.

            С обычным посетителем форма работает как всегда: пустое скрытое
            поле не мешает ни заполнению, ни отправке.
        --}}
        <div aria-hidden="true" class="absolute left-[-9999px] top-0 h-px w-px overflow-hidden">
            <label for="contact-website">{{ $text['honeypot_label'] ?? 'Сайт' }}</label>
            <input
                type="text"
                id="contact-website"
                name="{{ StoreContactRequest::HONEYPOT }}"
                value=""
                tabindex="-1"
                autocomplete="off"
                class="{{ $controlOk }}"
            >
        </div>

        <div class="grid gap-5">
            @foreach ($textFields as $key => $meta)
                @php
                    $field = (array) ($fields[$key] ?? []);
                    $errorId = 'contact-'.$key.'-error';
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
                        value="{{ old($key) }}"
                        placeholder="{{ $field['placeholder'] ?? '' }}"
                        maxlength="{{ StoreContactRequest::limitFor($key) }}"
                        autocomplete="{{ $meta['autocomplete'] }}"
                        @class([$errors->has($key) ? $controlError : $controlOk])
                        @if ($errors->has($key)) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
                        @if ($meta['required'] ?? false) required aria-required="true" @endif
                    >

                    @error($key)
                        <p id="{{ $errorId }}" class="mt-1.5 text-small text-danger">{{ $message }}</p>
                    @enderror
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
                    maxlength="{{ StoreContactRequest::limitFor('message') }}"
                    required
                    aria-required="true"
                    @class([$errors->has('message') ? $controlError : $controlOk])
                    @if ($errors->has('message')) aria-invalid="true" aria-describedby="contact-message-error" @endif
                >{{ old('message') }}</textarea>

                @error('message')
                    <p id="contact-message-error" class="mt-1.5 text-small text-danger">{{ $message }}</p>
                @enderror
            </div>

            {{--
                Согласие — обычный текст без ссылки: страницы политики
                конфиденциальности у проекта нет, и вести на несуществующий
                адрес нельзя. Подпись связана с чекбоксом через for, чтобы её
                можно было нажать. Поле обязательное, поэтому у него есть
                required и aria-required="true" — без них диктор сообщил бы
                о согласии только после отказа сервера.
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
                        @checked(old('consent'))
                        @class([
                            'mt-0.5 h-5 w-5 shrink-0 rounded-control accent-primary',
                            'border-line-strong' => ! $errors->has('consent'),
                            'border-danger' => $errors->has('consent'),
                        ])
                        @if ($errors->has('consent')) aria-invalid="true" aria-describedby="contact-consent-error" @endif
                    >

                    <label for="contact-consent" class="text-small text-ink-muted">
                        {{ $text['consent'] ?? '' }}
                    </label>
                </div>

                @error('consent')
                    <p id="contact-consent-error" class="mt-1.5 text-small text-danger">{{ $message }}</p>
                @enderror
            </div>

            {{--
                Классы повторяют primary-вариант components/button.blade.php,
                потому что сам компонент умеет только type="button".
            --}}
            <div>
                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-control bg-primary px-6 py-3.5 text-body font-semibold text-ink-inverse transition-colors duration-200 ease-soft hover:bg-primary-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-dark sm:w-auto"
                >{{ $text['submit'] ?? 'Проверить форму' }}</button>

                <p class="mt-4 text-small text-ink-muted">{{ $text['note'] ?? '' }}</p>
            </div>
        </div>
    </form>
</div>
