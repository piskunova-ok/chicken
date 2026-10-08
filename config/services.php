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
    | Заявка с формы обратной связи (/contacts) пересылается сервису
    | Web3Forms (https://api.web3forms.com/submit), который доставляет её
    | письмом владельцу сайта. SMTP и собственный почтовый транспорт
    | приложению не нужны: письмо собирает сам сервис.
    |
    | Access Key живёт только в окружении (на Render в панели) и задаётся
    | переменной WEB3FORMS_ACCESS_KEY. В коде ключа нет и не будет: он
    | уходит лишь полем access_key в теле запроса и в лог не попадает.
    |
    */

    'web3forms' => [
        'access_key' => env('WEB3FORMS_ACCESS_KEY'),
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
