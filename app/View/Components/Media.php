<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Изображение или его локальный placeholder.
 *
 * Ключевая идея компонента: разметка и пропорции не меняются при переходе
 * от заглушки к реальной фотографии. Чтобы заменить placeholder, достаточно
 * передать 'src' (и осмысленный 'alt') — контейнер, скругление и aspect-ratio
 * останутся прежними, поэтому вёрстка не «поедет».
 *
 * Пока 'src' не задан, рисуется локальная векторная заглушка в палитре сайта.
 * Внешние URL не используются: ни hotlink, ни CDN.
 */
final class Media extends Component
{
    /**
     * @param  string|null  $src  Путь к изображению. null — локальный placeholder.
     * @param  string|null  $alt  Описание изображения. null — изображение декоративное.
     * @param  string  $ratio  Пропорции: 4/3, 3/2, 1/1, 16/9, 4/5, 5/4.
     * @param  string  $variant  Стиль заглушки: hero | card | lifestyle.
     * @param  bool  $priority  True для изображения первого экрана (без lazy).
     */
    public function __construct(
        public readonly ?string $src = null,
        public readonly ?string $alt = null,
        public readonly string $ratio = '4/3',
        public readonly string $variant = 'card',
        public readonly bool $priority = false,
    ) {}

    /**
     * Tailwind-классы пропорций. Классы перечислены литералами в шаблоне,
     * чтобы Tailwind стабильно их генерировал.
     */
    public function ratioClasses(): string
    {
        return match ($this->ratio) {
            '1/1' => 'aspect-square',
            '3/2' => 'aspect-[3/2]',
            '16/9' => 'aspect-[16/9]',
            '4/5' => 'aspect-[4/5]',
            '5/4' => 'aspect-[5/4]',
            default => 'aspect-[4/3]',
        };
    }

    /**
     * Текстовая подпись внутри заглушки: подсказывает владельцу сайта,
     * что здесь появится фотография, и попадёт в скринридер.
     */
    public function placeholderLabel(): string
    {
        return match ($this->variant) {
            'hero' => 'Место для фотографии на первом экране',
            'lifestyle' => 'Место для фотографии',
            default => 'Место для фотографии категории',
        };
    }

    public function render(): View
    {
        return view('components.media');
    }
}
