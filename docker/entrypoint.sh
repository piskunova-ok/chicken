#!/bin/sh
# Стартовый скрипт контейнера.
#
# Чего этот скрипт НЕ делает намеренно:
#   * не генерирует APP_KEY — ключ приходит из переменной окружения
#     (проверка ниже только убеждается, что он задан и по длине подходит
#     cipher'у, и останавливает контейнер с внятной ошибкой иначе);
#   * не запускает миграции и сидеры;
#   * не создаёт администратора;
#   * не выполняет migrate:fresh, db:wipe и прочие разрушительные операции.
#
# Первый деплой — это отдельные шаги: php artisan migrate --force, затем
# php artisan db:seed --class=ProductCatalogSeeder --force.

set -eu

# Render передаёт порт в PORT. Значение по умолчанию нужно для локального
# docker run, где переменную никто не задаёт: без неё конфиг nginx был бы
# синтаксически невалиден и контейнер не стартовал бы.
PORT="${PORT:-10000}"
export PORT

# Каталог приложения. Задан явно, чтобы скрипт не зависел от того, откуда
# его вызвали.
APP_DIR=/var/www/html

# ── Проверка APP_KEY ──────────────────────────────────────────────────────
# Без ключа шифрование не работает: ломаются сессии и cookie. Генерировать
# ключ на старте нельзя — каждый новый старт дал бы новый ключ и обесценил
# все зашифрованные данные. Поэтому контейнер останавливается и требует
# задать переменную.
#
# Проверка длины обязательна, а не подстраховка. Laravel создаёт Encrypter
# с ключом, полученным из APP_KEY, и падает уже во время запроса:
#   RuntimeException: Unsupported cipher or incorrect key length.
# Контейнер при этом запускается и выглядит здоровым, поэтому ошибка
# обнаруживается уже на Render — как 500 на каждой странице. Проверка на
# старте превращает её в понятный отказ контейнера.
#
# Логика на PHP, а не на shell: разбор префикса base64:, нестрогий
# base64_decode и соответствие длине cipher'у должны вести себя ровно так
# же, как в Illuminate\Encryption\Encrypter. Расхождение в shell-логике
# дало бы проверку, которая пропускает нерабочий ключ.
#
# Секрет наружу не выводится: в сообщениях только длина ключа и имя cipher.
php <<'PHP'
<?php

$fail = static function (string $message): never {
    fwrite(STDERR, "ОШИБКА APP_KEY: {$message}\n");
    fwrite(STDERR, "Сгенерировать корректный ключ: php artisan key:generate --show\n");
    exit(1);
};

$key = (string) getenv('APP_KEY');

// APP_CIPHER в config/app.php по умолчанию AES-256-CBC.
$cipher = strtoupper((string) (getenv('APP_CIPHER') ?: 'AES-256-CBC'));

if (trim($key) === '') {
    $fail('переменная окружения APP_KEY не задана. Задайте её в настройках Web Service на Render.');
}

// Требуемая длина выводится из cipher — тот же расчёт, что в Encrypter.
$required = match ($cipher) {
    'AES-128-CBC', 'AES-128-GCM' => 16,
    'AES-192-CBC', 'AES-192-GCM' => 24,
    'AES-256-CBC', 'AES-256-GCM' => 32,
    default => null,
};

// base64-ключ декодируется один раз, без strict-режима: Laravel тоже
// использует нестрогий base64_decode, и строгая проверка отвергла бы ключ,
// с которым приложение на самом деле работает.
if (str_starts_with($key, 'base64:')) {
    $decoded = base64_decode(substr($key, 7));

    if ($decoded === '') {
        $fail('значение после префикса base64: пустое или не декодируется.');
    }
} else {
    $decoded = $key;
}

$length = strlen($decoded);

if ($required === null) {
    printf("APP_KEY: длина %d байт. Проверка пропущена: APP_CIPHER=%s не распознан.\n", $length, $cipher);
    exit(0);
}

if ($length !== $required) {
    $fail(sprintf(
        'несовместим с APP_CIPHER=%s: длина ключа %d байт, требуется ровно %d.',
        $cipher,
        $length,
        $required
    ));
}

printf("APP_KEY: длина %d байт, соответствует APP_CIPHER=%s.\n", $length, $cipher);
PHP

# ── Конфиг nginx из шаблона ───────────────────────────────────────────────
# Подставляется РОВНО одна переменная — PORT. Список переменных обязателен:
# в шаблоне есть $uri, $document_root, $fastcgi_script_name, и обычный
# envsubst без списка подставил бы и их, сломав конфиг.
envsubst '${PORT}' \
    < /etc/nginx/templates/default.conf.template \
    > /etc/nginx/conf.d/default.conf

# ── Каталоги, которые Laravel пишет в рантайме ───────────────────────────
# image: ставится на этапе сборки. Права проверяются заново при каждом
# старте, потому что это единственное место, где можно убедиться, что
# каталоги доступны пользователю www-data, от которого работает php-fpm.
for dir in \
    "$APP_DIR/bootstrap/cache" \
    "$APP_DIR/storage/app/private" \
    "$APP_DIR/storage/app/public" \
    "$APP_DIR/storage/framework/cache/data" \
    "$APP_DIR/storage/framework/sessions" \
    "$APP_DIR/storage/framework/views" \
    "$APP_DIR/storage/logs"
do
    mkdir -p "$dir"
done

# Отдельная точка для файлов сессий и кэша данных: symlink переживает
# переустановку пакета, но в этом образе не используется — каталоги
# создаются напрямую.
chown -R www-data:www-data \
    "$APP_DIR/bootstrap/cache" \
    "$APP_DIR/storage"

chmod -R 775 \
    "$APP_DIR/bootstrap/cache" \
    "$APP_DIR/storage"

# ── Запуск ────────────────────────────────────────────────────────────────
# exec заменяет процесс оболочки на supervisord, поэтому PID 1 получает
# сигналы остановки напрямую, а Render видит честный код завершения.
exec /usr/bin/supervisord -c /etc/supervisord.conf