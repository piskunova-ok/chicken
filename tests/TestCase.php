<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\WorkingDatabaseGuard;

abstract class TestCase extends BaseTestCase
{
    /**
     * Аварийная защита рабочей SQLite.
     *
     * Проверка стоит ДО parent::setUp(), а не после: базовый класс именно
     * там создаёт приложение и вызывает setUpTraits(), где RefreshDatabase
     * запускает миграции. До parent::setUp() ни приложение, ни база ещё не
     * тронуты, поэтому опасный запуск обрывается заведомо безопасно.
     *
     * Признак тестового контекста — сам факт наследования от PHPUnit:
     * сюда попасть может только PHPUnit-прогон, поэтому проверка не
     * зависит ни от APP_ENV, ни от phpunit.xml, ни от .env.
     */
    protected function setUp(): void
    {
        WorkingDatabaseGuard::assertSafeTestDatabase();

        parent::setUp();
    }
}
