<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Русские сообщения standard Laravel validation.
 *
 * ПРОБЛЕМА, КОТОРУЮ РЕШАЕТ ЭТОТ ЭТАП
 *
 * У приложения локаль ru, а переведённые validation-строки Laravel лежат
 * в вендоре только на английском. Пока не было lang/ru/validation.php,
 * trans('validation.unique') при локали ru возвращал сам ключ, и панель
 * показывала администратору «validation.unique» вместо человеческого
 * текста. Здесь проверяется сам контракт переводов, без HTML.
 *
 * ЧТО ПРОВЕРЯЕТСЯ
 *
 * 1. Фактическая локаль приложения — ru (./config/app.php и APP_LOCALE).
 * 2. Правила, которые реально используют текущие формы панели, отдают
 *    русский текст, а не ключ «validation.<rule>».
 * 3. Слова «:attribute» и «:max» подставляются в предложение.
 *
 * Правила форм панели сейчас: required (у всех трёх форм), string/maxLength
 * (текстовые поля), integer/minValue (sort_order), exists (category select у
 * товара), unique (составной slug у товара).
 */
class ValidationTranslationsTest extends TestCase
{
    public function test_the_application_locale_is_russian(): void
    {
        $this->assertSame('ru', $this->app->getLocale());
        $this->assertSame('ru', $this->app->getFallbackLocale());
    }

    /**
     * Правила текущих форм панели не должны отдавать свои ключи.
     */
    public function test_the_validation_rules_used_by_the_admin_forms_return_russian_text_not_keys(): void
    {
        $rulesUsedByForms = [
            'required',
            'string',
            'integer',
            'exists',
            'unique',
            'min.numeric',
            'max.string',
        ];

        foreach ($rulesUsedByForms as $rule) {
            $message = trans("validation.{$rule}");

            $this->assertNotSame(
                "validation.{$rule}",
                $message,
                "Правило {$rule} должно переводиться в lang/ru/validation.php, а не возвращать ключ.",
            );
            $this->assertMatchesRegularExpression(
                '/[а-яё]/iu',
                (string) $message,
                "Сообщение правила {$rule} должно быть на русском.",
            );
        }
    }

    public function test_the_required_message_builds_a_sentence_with_the_attribute(): void
    {
        $this->assertSame(
            'Поле «название» обязательно для заполнения.',
            trans('validation.required', ['attribute' => 'название']),
        );
    }

    public function test_the_unique_message_builds_a_sentence_with_the_attribute(): void
    {
        $this->assertSame(
            'Такое значение поля «slug» уже существует.',
            trans('validation.unique', ['attribute' => 'slug']),
        );
    }

    public function test_the_max_string_message_uses_the_max_limit(): void
    {
        $this->assertSame(
            'Значение поля «название» не должно быть длиннее 255 символов.',
            trans('validation.max.string', ['attribute' => 'название', 'max' => 255]),
        );
    }

    public function test_the_attributes_section_names_the_catalog_fields_in_russian(): void
    {
        // Секция lang/ru/validation.php / attributes — запасной способ
        // назвать поля для валидации вне Filament-форм, у которой нет
        // собственного списка имён.
        $this->assertSame('категория', trans('validation.attributes.product_category_id'));
        $this->assertSame('порядок', trans('validation.attributes.sort_order'));
    }
}