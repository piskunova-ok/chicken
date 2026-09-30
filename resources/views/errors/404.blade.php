{{--
    Страница ошибки 404.

    ЗАЧЕМ СОБСТВЕННЫЙ ШАБЛОН, А НЕ ШТАТНЫЙ LARAVEL

    Штатный errors::404 разворачивает vendor-шаблон minimal.blade.php, в
    котором <html lang="en"> зашит литералом, а заголовок и текст берутся
    из __('Not Found'). Ключа 'Not Found' в lang/ru нет, поэтому
    __('Not Found') возвращает саму английскую строку. Итог был одинаковым
    для посетителя и для программиста: страница на чужом языке, со словом
    «Laravel» в <title> и в теле, в оформлении, которого нет на сайте.

    Стандартный способ переопределения — положить сюда файл с именем
    статуса: Illuminate\Foundation\Exceptions\RegisterErrorViewPaths
    подставляет resources/views/errors первым в namespace 'errors', и
    Handler::getHttpExceptionView() находит errors::404 здесь, а не в
    vendor. Никакой правки vendor/ для этого не нужно.

    ПОЧЕМУ СТРАНИЦА САМОСТОЯТЕЛЬНАЯ, А НЕ ЧЕРЕЗ components.layouts.app

    Обычная вёрстка подставляет <x-site.header /> и <x-site.footer />, а
    они печатают название компании, телефон, адрес и режим работы. Пока
    заказчик не передал эти данные, на них стоят заглушки [НАЗВАНИЕ
    КОМПАНИИ], [ТЕЛЕФОН], [EMAIL], [АДРЕС] и [РЕЖИМ РАБОТЫ]. На странице
    ошибки они не нужны и выглядели бы как поломка, поэтому шапка и подвал
    здесь не выводятся. Оформление то же самое теми же токенами темы и
    той же собранной таблицей стилей, что и на публичных страницах.

    Никаких технических деталей здесь нет: ни стектрейса, ни имён
    классов, ни версии фреймворка, ни адресов внутренних маршрутов. При
    APP_DEBUG=true вместо этого шаблона посетитель увидел бы отчёт об
    ошибке с исходным кодом.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-pt-20">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Заголовок намеренно без названия компании: config('site.meta.title')
         содержит [НАЗВАНИЕ КОМПАНИИ], пока заказчик не передал SITE_NAME, а
         на странице ошибки это выглядело бы как поломка. --}}
    <title>{{ __('Страница не найдена') }}</title>
    <meta name="robots" content="noindex, follow">
    <meta name="description" content="{{ __('Запрошенная страница не найдена. Проверьте адрес или вернитесь на главную.') }}">

    <link rel="canonical" href="{{ url()->current() }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    <main id="main" tabindex="-1" class="flex-1 focus:outline-none">
        <x-section tone="canvas" spacing="loose">
            <div class="mx-auto max-w-2xl text-center">
                <x-eyebrow :text="__('Ошибка 404')" />

                <h1 class="mt-3 text-display text-ink">
                    {{ __('Страница не найдена') }}
                </h1>

                <p class="mt-5 text-lead text-ink-muted">
                    {{ __('Возможно, адрес введён с опечаткой или материал был перемещён. Проверьте адрес страницы или вернитесь на главную.') }}
                </p>

                <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <x-button
                        :label="__('На главную')"
                        :href="route('home')"
                        variant="primary"
                    />

                    <x-button
                        :label="__('Продукция')"
                        :href="Route::has('products.index') ? route('products.index') : null"
                        variant="secondary"
                    />
                </div>
            </div>
        </x-section>
    </main>
</body>
</html>
