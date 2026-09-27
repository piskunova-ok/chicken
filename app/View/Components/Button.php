<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Основная кнопка/ссылка сайта.
 *
 * Рендерится как <a>, если передан $href, иначе как <button>.
 * Поддерживает варианты primary (заливка) и secondary (контур) и
 * корректное неактивное состояние для обоих вариантов.
 */
final class Button extends Component
{
    public function __construct(
        public readonly string $label = '',
        /** null — рендерить <button>, строка — рендерить <a href>. */
        public readonly ?string $href = null,
        /** primary | secondary */
        public readonly string $variant = 'primary',
        public readonly bool $disabled = false,
        /** Внешняя ссылка: добавляет rel="noopener noreferrer". */
        public readonly bool $external = false,
    ) {}

    public function render(): View
    {
        return view('components.button');
    }
}
