<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Короткая рукописная надпись (Caveat, коричневый акцент).
 *
 * Предназначена только для эмоциональных акцентов перед заголовками.
 * Компонент намеренно не даёт выбрать произвольный шрифт или размер,
 * чтобы Caveat не «утёк» в основной текст, меню или кнопки.
 */
final class Eyebrow extends Component
{
    public function __construct(
        public readonly string $text,
        /** Светлая или тёмная секция. */
        public readonly string $tone = 'light',
    ) {}

    public function render(): View
    {
        return view('components.eyebrow');
    }
}
