<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * Аварийный предохранитель рабочей SQLite.
 *
 * ЗАЧЕМ. Тесты обязаны работать на эфемерной базе. Опасный сценарий
 * реален и уже случился: запуск PHPUnit с --no-configuration игнорирует
 * phpunit.xml, поэтому переменные DB_CONNECTION/DB_DATABASE из него не
 * попадают в окружение, и config/database.php откатывается к значению по
 * умолчанию — database/database.sqlite, то есть к РАБОЧЕЙ базе. Там
 * RefreshDatabase выполняет migrate:fresh и стирает её содержимое.
 *
 * ПОЧЕМУ НЕ ЧЕРЕЗ APP_ENV. Проверка app()->environment('testing') не годится:
 * именно из-за отсутствия phpunit.xml приложение поднимается с APP_ENV=local
 * из .env, и такая проверка молча разрешила бы опасный запуск. Решение о
 * тестовом контексте принимает место вызова (см. Tests\TestCase), а не
 * переменная окружения: этот класс достижим только из PHPUnit-прогона.
 *
 * ПРОВЕРКА КОНТЕКСТА ОТДЕЛЬНО ОТ ПРОВЕРКИ БАЗЫ. Здесь решается лишь одно:
 * указывает ли текущая конфигурация на файл вместо :memory:. Никакой
 * production-код этот класс не вызывает.
 *
 * ПОЛИТИКА — fail-closed: разрешена ровно одна конфигурация, эфемерная
 * база :memory:. Любое другое значение (в том числе пустое, при котором
 * конфиг берёт рабочий файл по умолчанию) считается опасным и обрывает
 * прогон. Так защита не зависит от того, откуда взялось значение.
 */
final class WorkingDatabaseGuard
{
    /**
     * Единственная разрешённая в тестах база.
     */
    private const ALLOWED_DATABASE = ':memory:';

    /**
     * Опасен ли указанный в конфигурации источник базы.
     *
     * Пустая строка и null опасны намеренно: env('DB_DATABASE',
     * database_path('database.sqlite')) при отсутствии переменной
     * подставляет именно рабочий файл.
     */
    public static function isDangerousDatabase(?string $database): bool
    {
        return mb_strtolower(trim((string) $database)) !== self::ALLOWED_DATABASE;
    }

    /**
     * Значение DB_DATABASE в текущем окружении процесса.
     *
     * Читаются те же источники, из которых Laravel берёт переменные:
     * сначала $_ENV/$_SERVER (их выставляет PHPUnit из phpunit.xml), затем
     * getenv(). Значение из файла .env здесь ещё не подгружено — оно
     * появится только при бутстрапе приложения, до которого мы намеренно
     * не доходим.
     */
    public static function currentDatabaseValue(): ?string
    {
        foreach ([$_ENV, $_SERVER] as $bag) {
            if (isset($bag['DB_DATABASE']) && is_string($bag['DB_DATABASE'])) {
                return $bag['DB_DATABASE'];
            }
        }

        $fromEnv = getenv('DB_DATABASE');

        return $fromEnv === false ? null : $fromEnv;
    }

    /**
     * Обрывает прогон, если тесты направлены не в :memory:.
     *
     * DB_URL проверяется отдельно: при непустом значении sqlite-соединение
     * берёт адрес оттуда, и одной проверки DB_DATABASE было бы недостаточно.
     *
     * @throws RuntimeException
     */
    public static function assertSafeTestDatabase(): void
    {
        /*
         * DB_URL проверяется ДО DB_DATABASE и без раннего выхода: при
         * непустом значении sqlite-соединение берёт адрес оттуда, поэтому
         * пара «DB_DATABASE=:memory: + DB_URL=…/database.sqlite» иначе
         * прошла бы как безопасная и указала бы на рабочий файл.
         */
        $fromUrl = self::valueOf('DB_URL');

        if (filled($fromUrl)) {
            throw self::failure(
                'В тестовом окружении задан DB_URL, поэтому база не может быть проверена по DB_DATABASE.',
                $fromUrl,
            );
        }

        $database = self::currentDatabaseValue();

        if (! self::isDangerousDatabase($database)) {
            return;
        }

        throw self::failure(
            'Тесты не запущены на эфемерной базе :memory:.',
            $database,
        );
    }

    private static function valueOf(string $name): ?string
    {
        foreach ([$_ENV, $_SERVER] as $bag) {
            if (isset($bag[$name]) && is_string($bag[$name])) {
                return $bag[$name];
            }
        }

        $fromEnv = getenv($name);

        return $fromEnv === false ? null : $fromEnv;
    }

    private static function failure(string $reason, ?string $value): RuntimeException
    {
        return new RuntimeException(sprintf(
            '%s Защита рабочей базы: DB_DATABASE=%s. Ожидалось %s, например из phpunit.xml. '
            .'Запуск с --no-configuration игнорирует phpunit.xml и подставляет файл database/database.sqlite, '
            .'где RefreshDatabase выполняет migrate:fresh. Файл не изменён, тесты прерваны до обращения к базе.',
            $reason,
            var_export($value, true),
            self::ALLOWED_DATABASE,
        ));
    }
}
