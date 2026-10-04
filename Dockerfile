# syntax=docker/dockerfile:1

# Одно изображение — один Web Service на Render.
#
# Архитектура: nginx + php-fpm под supervisord в одном контейнере.
# Причины выбора:
#   * Render отдаёт один публичный порт из одного контейнера, поэтому nginx
#     и php-fpm обязаны жить вместе. Отдельный контейнер с nginx Render не
#     развернёт как один сервис.
#   * php-fpm держит opcache в памяти между запросами. artisan serve и
#     встроенный PHP-сервер компилируют файлы на каждый запрос и opcache
#     не используют — для продакшена это неприемлемо.
#   * nginx отдаёт /images/products/*.jpg и /build/* прямо с диска, не
#     пропуская фотографии через PHP.
#   * Octane/FrankenPHP потребовали бы добавить пакеты в composer.json,
#     а composer.lock менять запрещено.
#   * supervisord перезапускает php-fpm, если он упал, и корректно
#     обрабатывает сигналы остановки контейнера.

# Тег плавающий: 8.4.x. composer.lock требует PHP >= 8.4.1
# (symfony/* => >=8.4.1), поэтому 8.3 не подходит. Плавающий тег гарантирует
# существование патча на Docker Hub и при этом остаётся в нужной ветке.
ARG PHP_VERSION=8.4


# ─────────────────────────── Stage 1: frontend ───────────────────────────
# node_modules не попадает в итоговый образ: на нём выполняется только
# сборка, результат копируется в public/build.
FROM node:22-bookworm-slim AS assets

WORKDIR /build

# Сначала только манифесты: слой с npm ci кешируется и не пересобирается,
# пока не меняются зависимости.
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

# Tailwind 4 определяет источники классов автоматически, обходя проект от
# корня и уважая .gitignore. Значит набор файлов должен совпадать с тем, что
# видит локальная сборка, иначе CSS в образе отличается от локального.
#
# Раньше здесь копировались только .gitignore, resources/ и app/. Этого
# оказалось мало: локальная сборка находит ещё и классы, встречающиеся
# только в database/, config/ или routes/ — например утилиты container,
# table, filter, lowercase из имён вроде Schema::table. В образе они
# терялись, и CSS был на 1616 байт меньше локального.
#
# Решение — копировать весь контекст. .dockerignore уже исключает
# node_modules, vendor, storage, .env и public/build, поэтому в стадию
# попадает ровно то, что нужно для сканирования, и ничего лишнего.
# public/build исключён намеренно: иначе локальная устаревшая сборка
# попала бы в образ и npm --force перезаписал бы её.
COPY . ./

# Каталог public/ нужен плагину laravel-vite как точка вывода.
RUN mkdir -p public && npm run build


# ────────────────────────── Stage 2: composer ────────────────────────────
# Зависимости ставятся без скриптов и без автозагрузчика.
#
# Это принципиально: в composer.json есть post-autoload-dump, который
# вызывает artisan (package:discover, filament:upgrade). На этой стадии
# исходников приложения в образе ещё нет, и artisan упал бы. Скрипты
# запускаются вручную на следующей стадии, когда проект уже скопирован.
#
# --ignore-platform-req=ext-intl: образ composer:2 собран без intl, а lock
# требует его для filament/support. Расширение проверяется отдельно в
# runtime-стадии (RUN с проверкой всех 19 ext-* валит сборку при отсутствии),
# поэтому игнорировать его здесь безопасно: composer лишь разрешает
# зависимости по lock, а не меняет его. Флаг точечный — остальные
# расширения по-прежнему участвуют в проверке.
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --no-progress \
        --ignore-platform-req=ext-intl \
        --prefer-dist


# ────────────────────────── Stage 3: runtime ─────────────────────────────
FROM php:${PHP_VERSION}-fpm-bookworm AS runtime

# ext-* из composer.lock: dom, fileinfo, filter, hash, iconv, intl, json,
# libxml, mbstring, openssl, pcre, session, tokenizer, xmlreader, zip,
# ctype. В официальном образе php они уже собраны, поэтому ставятся только
# отсутствующие:
#   intl        — обязателен lock'ом (symfony/intl), нужен Laravel Number
#   zip         — обязателен lock'ом, ускоряет установку dist-пакетов
#   pdo_pgsql   — Render отдаёт PostgreSQL
#   opcache     — продакшн-производительность
#   bcmath      — не требуется lock'ом; включён как дешёвая страховка для
#                 арифметики Filament и будущих зависимостей
# Пакет nginx по умолчанию поднимает сайт на 80-м порту. Он не нужен и
# мешает однозначности: единственный сайт должен слушать $PORT.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        gettext-base \
        libicu-dev \
        libpq-dev \
        libzip-dev \
        nginx \
        supervisor; \
    docker-php-ext-configure intl; \
    docker-php-ext-install -j"$(nproc)" bcmath intl opcache pdo_pgsql zip; \
    rm -rf /var/lib/apt/lists/*; \
    rm -f /etc/nginx/sites-enabled/default

# Сборочная проверка: падение на отсутствующем расширении происходит здесь,
# на этапе сборки, а не как 500-я ошибка на Render после деплоя.
#
# opcache проверяется как "Zend OPcache": это Zend-расширение, и
# extension_loaded('opcache') для него всегда возвращает false.
RUN php -r '\
$required = ["bcmath","ctype","dom","fileinfo","filter","hash","iconv","intl","json","libxml","mbstring","openssl","Zend OPcache","pcre","pdo_pgsql","session","tokenizer","xmlreader","zip"]; \
$missing = array_values(array_filter($required, static fn ($e) => ! extension_loaded($e))); \
if ($missing !== []) { fwrite(STDERR, "MISSING EXTENSIONS: ".implode(", ", $missing).PHP_EOL); exit(1); } \
echo "all required extensions present".PHP_EOL;'

WORKDIR /var/www/html

# Зависимости и манифесты — до исходников, но без запуска скриптов.
COPY --from=vendor /app/vendor ./vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./

# Сборка фронтенда из stage 1. Каталог public/build исключён в
# .dockerignore, поэтому локальная устаревшая сборка сюда не попадёт.
COPY --from=assets /build/public/build ./public/build

COPY app/ ./app/
COPY bootstrap/ ./bootstrap/
COPY config/ ./config/
COPY database/ ./database/
COPY public/ ./public/
COPY resources/ ./resources/
COPY routes/ ./routes/
COPY artisan ./artisan

# Каталоги, которые Laravel пишет в рантайме. Копировать из контекста
# нечего: локальные логи и кэш не должны попасть в образ, поэтому
# создаются сразу с правильным владельцем.
# storage/app/public в образе пуст — девять фотографий каталога лежат в
# public/images/products и едут в Git, поэтому volume для них не нужен.
# Владелец — www-data: php-fpm по умолчанию исполняет запросы от него.
RUN set -eux; \
    mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs; \
    chown -R www-data:www-data bootstrap/cache storage; \
    chmod -R 775 bootstrap/cache storage

# Скрипты composer, которые были отключены на стадии vendor.
# Теперь исходники на месте, поэтому artisan можно запускать.
# filament:assets публикует ассеты панели в public/ — они исключены
# .gitignore и в образ иначе не попали бы, а админка осталась бы без CSS.
RUN set -eux; \
    composer dump-autoload --no-dev --optimize --no-interaction --no-scripts; \
    php artisan package:discover --ansi --no-interaction; \
    php artisan filament:assets --ansi --no-interaction

COPY docker/nginx/default.conf.template /etc/nginx/templates/default.conf.template
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD []