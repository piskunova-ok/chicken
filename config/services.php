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
    | Resend
    |--------------------------------------------------------------------------
    |
    | Почта для формы обратной связи (/contacts) уходит через HTTPS API
    | сервиса Resend, а не через SMTP: хостинг блокирует исходящие SMTP-
    | порты, а HTTPS-запрос к api.resend.com работает всегда.
    |
    | Ключ и адреса живут только в окружении (на Render в панели). Адрес
    | отправителя — домен (@resend.dev), через который Resend разрешает
    | слать письма новым аккаунтам; адрес получателя — владелец сайта.
    |
    | Пароля здесь нет и не будет: Resend доверяет ключу, а ключ задаётся
    | переменной RESEND_API_KEY и не попадает ни в код, ни в лог.
    |
    */

    'resend' => [
        'key' => env('RESEND_API_KEY'),
        'from' => env('RESEND_FROM_EMAIL'),
        'to' => env('CONTACT_MAIL_TO'),
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
