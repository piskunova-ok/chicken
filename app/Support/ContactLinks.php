<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Безопасное формирование контактных ссылок.
 *
 * Пока контакты не подтверждены, в конфиге лежат заглушки вида [ТЕЛЕФОН]
 * и [EMAIL]. Такие значения нельзя превращать в ссылки: получится
 * нерабочий tel:[ТЕЛЕФОН] и mailto:[EMAIL]. Поэтому ссылка создаётся
 * только для подтверждённого значения, иначе контакт выводится текстом.
 */
final class ContactLinks
{
    public static function value(string $key): ?string
    {
        $value = config("site.contacts.{$key}");

        return is_string($value) ? $value : null;
    }

    /**
     * Значение-заглушка вида [ТЕЛЕФОН] / [EMAIL] / [АДРЕС].
     */
    public static function isPlaceholder(?string $value): bool
    {
        return $value === null || str_starts_with(trim($value), '[');
    }

    /**
     * Ссылка на телефон либо null, если значение ещё не подтверждено.
     */
    public static function phoneUrl(): ?string
    {
        $phone = self::value('phone');

        if (self::isPlaceholder($phone)) {
            return null;
        }

        $digits = preg_replace('/[^0-9+]/', '', (string) $phone);

        return is_string($digits) && $digits !== '' ? 'tel:'.$digits : null;
    }

    /**
     * Ссылка на почту либо null, если значение ещё не подтверждено.
     */
    public static function emailUrl(): ?string
    {
        $email = self::value('email');

        if (self::isPlaceholder($email) || ! str_contains((string) $email, '@')) {
            return null;
        }

        return 'mailto:'.$email;
    }
}
