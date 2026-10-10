<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Единая точка чтения настроек сайта.
 *
 * Принцип «значение из базы, если его заполнил владелец, иначе из конфига».
 * Работает одинаково в трёх ситуациях:
 *
 *  - таблица ещё не создана (свежий клон до миграции) — конфиг;
 *  - строка есть, но поле пустое (только что развёрнутый сайт) — конфиг;
 *  - владелец сохранил настройки в админке — база.
 *
 * Значение из базы читается один раз за запрос и кэшируется в статике.
 * После сохранения формы настроек вызывается flush(), чтобы следующий
 * запрос увидел свежие данные.
 *
 * Поля без запасного значения в конфиге (phone_secondary, telegram,
 * whatsapp, vk) возвращают null, пока их не заполнили в админке.
 */
final class Settings
{
    /**
     * Поля, которые владелец может заполнить в админке.
     */
    private const FIELDS = [
        'company_name',
        'short_description',
        'phone',
        'phone_secondary',
        'email',
        'address',
        'schedule',
        'telegram',
        'whatsapp',
        'vk',
    ];

    private static ?SiteSetting $row = null;

    private static array $values = [];

    /**
     * Значение настройки: заполненное из базы либо конфиг-фолбэк.
     */
    public static function value(string $key): ?string
    {
        self::load();

        $dbValue = self::$values[$key] ?? null;

        if (is_string($dbValue) && trim($dbValue) !== '') {
            return trim($dbValue);
        }

        $fallback = self::fallback($key);

        return is_string($fallback) ? $fallback : null;
    }

    /**
     * Сырое значение из базы без подстановки конфига.
     *
     * Используется формой настроек, чтобы не переносить значения-заглушки
     * из конфига в таблицу: форма показывает пустое поле, а не «липкий»
     * placeholder.
     */
    public static function raw(string $key): ?string
    {
        self::load();

        $dbValue = self::$values[$key] ?? null;

        return is_string($dbValue) ? $dbValue : null;
    }

    /**
     * Готовая ссылка на соцсеть либо null, если значение не заполнено.
     *
     * Принимает и полный URL, и короткую форму: @username для Telegram,
     * телефон для WhatsApp, slug страницы для ВКонтакте.
     */
    public static function socialUrl(string $key): ?string
    {
        $value = self::value($key);

        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (str_contains($value, '://') || str_starts_with($value, '//')) {
            return $value;
        }

        return match ($key) {
            'telegram' => 'https://t.me/'.ltrim($value, '@'),
            'whatsapp' => 'https://wa.me/'.preg_replace('/[^0-9]/', '', $value),
            'vk' => 'https://vk.com/'.ltrim($value, '@/'),
            default => null,
        };
    }

    /**
     * Сброс кэша после сохранения настроек.
     */
    public static function flush(): void
    {
        self::$row = null;
        self::$values = [];
    }

    private static function load(): void
    {
        if (self::$row instanceof SiteSetting) {
            return;
        }

        self::$row = SiteSetting::query()->find(1);

        if (! self::$row instanceof SiteSetting) {
            return;
        }

        foreach (self::FIELDS as $field) {
            self::$values[$field] = self::$row->getAttribute($field);
        }
    }

    private static function fallback(string $key): mixed
    {
        return match ($key) {
            'company_name' => config('site.name'),
            'short_description' => config('site.short_description'),
            'phone' => config('site.contacts.phone'),
            'email' => config('site.contacts.email'),
            'address' => config('site.contacts.address'),
            'schedule' => config('site.contacts.schedule'),
            'phone_secondary', 'telegram', 'whatsapp', 'vk' => null,
            default => null,
        };
    }
}