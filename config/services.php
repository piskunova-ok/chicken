<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Web3Forms
    |--------------------------------------------------------------------------
    |
    | Заявка с формы обратной связи (/contacts) уходит POST-запросом прямо
    | из браузера посетителя в Web3Forms (https://api.web3forms.com/submit),
    | который доставляет её письмом владельцу сайта. SMTP и собственный
    | почтовый транспорт приложению не нужны: письмо собирает сам сервис.
    |
    | Access Key живёт только в окружении (на Render в панели) и задаётся
    | переменной WEB3FORMS_ACCESS_KEY. В коде и в Git ключа нет: Blade
    | читает его отсюда и кладёт скрытым полем access_key в HTML формы, а
    | сервер Laravel больше ничего с ним не делает. Для Web3Forms это не
    | секрет, а идентификатор формы: без него сервис даже не примет заявку,
    | а бесплатный тариф работает только с запросами из браузера.
    |
    */

    'web3forms' => [
        'access_key' => env('WEB3FORMS_ACCESS_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudinary
    |--------------------------------------------------------------------------
    |
    | Облачное хранилище фотографий, загружаемых из админки. Те же переменные
    | окружения, что и в config/filesystems.php (диск "cloudinary"). На Render
    | задаются в панели; локально — в .env.
    |
    */

    'cloudinary' => [
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY'),
        'api_secret' => env('CLOUDINARY_API_SECRET'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
