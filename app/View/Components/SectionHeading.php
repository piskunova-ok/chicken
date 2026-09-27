<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Заголовок секции: рукописный акцент, заголовок и необязательный лид.
 *
 * Уровень заголовка задаётся явно (по умолчанию h2), чтобы не нарушать
 * иерархию документа на страницах, где используется h1 в Hero.
 */
final class SectionHeading extends Component
{
    public function __construct(
        public readonly ?string $eyebrow = null,
        public readonly string $title = '',
        public readonly ?string $lead = null,
        /** left | center */
        public readonly string $align = 'left',
        /** light | dark — для тёмных секций. */
        public readonly string $tone = 'light',
        /** h1 | h2 | h3 */
        public readonly string $level = 'h2',
    ) {}

    public function render(): View
    {
        return view('components.section-heading');
    }
}
