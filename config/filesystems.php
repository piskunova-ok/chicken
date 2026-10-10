<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
        |--------------------------------------------------------------------------
        | Cloudinary Disk
        |--------------------------------------------------------------------------
        |
        | Фотографии товаров, загружаемые из админки, хранятся в облаке
        | Cloudinary (CLOUDINARY_CLOUD_NAME / API_KEY / API_SECRET). Пока
        | переменные не заданы, диск существует, но любая операция записи
        | вернёт понятную ошибку — локальные пути products/* продолжают
        | отдаваться со старого public-диска.
        |
        | throw => true — НАМЕРЕННО, а не по умолчанию.
        |
        | Со значением false (как у local/public/s3) Laravel перехватывает
        | UnableToWriteFile, пишет лог только при report => true и
        | возвращает false. Тогда сбой загрузки в Cloudinary становится
        | невидимым: Livewire TemporaryUploadedFile::storeAs() (вендор)
        | игнорирует возврат put() и всё равно отдаёт путь, Filament пишет
        | «products/cld-…» в products.image, а в Cloudinary файла нет. Ровно
        | это и выглядело как «фото выбрано, товар сохранён, но в облаке
        | ничего нет и в логах пусто».
        |
        | С throw => true ошибка записи выбрасывается наружу: сохранение
        | формы падает с понятной ошибкой, путь в БД НЕ попадает, а причина
        | (не задан CLOUDINARY_CLOUD_NAME, отказ API, сеть) видна в логах.
        */

        'cloudinary' => [
            'driver' => 'cloudinary',
            'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
            'api_key' => env('CLOUDINARY_API_KEY'),
            'api_secret' => env('CLOUDINARY_API_SECRET'),
            'url' => empty(env('CLOUDINARY_CLOUD_NAME'))
                ? null
                : rtrim('https://res.cloudinary.com/'.env('CLOUDINARY_CLOUD_NAME'), '/').'/image/upload',
            'throw' => true,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
