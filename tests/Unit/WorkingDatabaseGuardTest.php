<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\WorkingDatabaseGuard;

/**
 * Регрессионный тест предохранителя рабочей SQLite.
 *
 * Наследует PHPUnit\Framework\TestCase, а не Tests\TestCase, намеренно:
 * проверка решения должна работать и тогда, когда окружение опасное, иначе
 * страхующий тест нельзя было бы запустить для доказательства.
 *
 * Тест не обращается к базе вообще — только чистая логика решения и
 * переменные окружения, которые он сам устанавливает и восстанавливает.
 */
class WorkingDatabaseGuardTest extends TestCase
{
    private string|false|null $originalEnv = false;

    private string|false|null $originalServer = false;

    private string|false|null $originalGetenv = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalEnv = $_ENV['DB_DATABASE'] ?? null;
        $this->originalServer = $_SERVER['DB_DATABASE'] ?? null;
        $this->originalGetenv = getenv('DB_DATABASE');

        unset($_ENV['DB_DATABASE'], $_SERVER['DB_DATABASE']);
    }

    protected function tearDown(): void
    {
        if ($this->originalEnv === null) {
            unset($_ENV['DB_DATABASE']);
        } else {
            $_ENV['DB_DATABASE'] = $this->originalEnv;
        }

        if ($this->originalServer === null) {
            unset($_SERVER['DB_DATABASE']);
        } else {
            $_SERVER['DB_DATABASE'] = $this->originalServer;
        }

        if ($this->originalGetenv === false) {
            putenv('DB_DATABASE');
        } else {
            putenv('DB_DATABASE='.$this->originalGetenv);
        }

        parent::tearDown();
    }

    private function useDatabaseValue(?string $value): void
    {
        if ($value === null) {
            unset($_ENV['DB_DATABASE'], $_SERVER['DB_DATABASE']);
            putenv('DB_DATABASE');

            return;
        }

        $_ENV['DB_DATABASE'] = $value;
    }

    public function test_the_ephemeral_memory_database_is_allowed(): void
    {
        $this->assertFalse(WorkingDatabaseGuard::isDangerousDatabase(':memory:'));
    }

    public function test_the_memory_database_is_allowed_regardless_of_case_and_spaces(): void
    {
        $this->assertFalse(WorkingDatabaseGuard::isDangerousDatabase(' :MEMORY: '));
    }

    public function test_the_working_database_file_is_forbidden(): void
    {
        $this->assertTrue(WorkingDatabaseGuard::isDangerousDatabase('database/database.sqlite'));
    }

    public function test_an_absolute_path_to_the_working_database_is_forbidden(): void
    {
        $this->assertTrue(WorkingDatabaseGuard::isDangerousDatabase(
            dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sqlite',
        ));
    }

    public function test_any_other_sqlite_file_is_forbidden(): void
    {
        $this->assertTrue(WorkingDatabaseGuard::isDangerousDatabase('database/testing.sqlite'));
    }

    public function test_a_missing_value_is_forbidden_because_the_config_default_is_the_working_file(): void
    {
        $this->assertTrue(WorkingDatabaseGuard::isDangerousDatabase(null));
        $this->assertTrue(WorkingDatabaseGuard::isDangerousDatabase(''));
    }

    public function test_the_guard_passes_on_the_memory_database(): void
    {
        $this->useDatabaseValue(':memory:');

        WorkingDatabaseGuard::assertSafeTestDatabase();

        $this->assertTrue(true, 'assertSafeTestDatabase() не прервал прогон на :memory:.');
    }

    public function test_the_guard_aborts_when_the_environment_points_at_the_working_file(): void
    {
        $this->useDatabaseValue('database/database.sqlite');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(':memory:');

        WorkingDatabaseGuard::assertSafeTestDatabase();
    }

    public function test_the_guard_aborts_when_the_environment_has_no_database_value_at_all(): void
    {
        $this->useDatabaseValue(null);

        $this->expectException(RuntimeException::class);

        WorkingDatabaseGuard::assertSafeTestDatabase();
    }

    public function test_the_guard_aborts_when_a_database_url_is_configured(): void
    {
        $this->useDatabaseValue(':memory:');
        $_ENV['DB_URL'] = 'sqlite:///database/database.sqlite';

        try {
            $this->expectException(RuntimeException::class);

            WorkingDatabaseGuard::assertSafeTestDatabase();
        } finally {
            unset($_ENV['DB_URL']);
        }
    }

    public function test_the_guard_is_not_referenced_by_the_application_code(): void
    {
        $referenced = [];

        foreach ($this->phpFilesIn(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'app') as $file) {
            if (str_contains((string) file_get_contents($file), 'WorkingDatabaseGuard')) {
                $referenced[] = $file;
            }
        }

        $this->assertSame(
            [],
            $referenced,
            'Production-код не должен зависеть от тестового предохранителя.',
        );
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
