<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Карточка товара в каталоге.
 *
 * Компонент намеренно ничего не знает о том, откуда пришли данные: сегодня это
 * массив из config/catalog.php, завтра это будет строка из базы. Представление
 * карточки от источника данных не зависит, поэтому переход на БД не потребует
 * переписывать HTML.
 *
 * Поддерживаемые поля: name, photo, short_description, category, packaging,
 * quantity, weight, shelf_life, storage, nutrition, additional_info,
 * details_url.
 *
 * Ключевое правило: характеристика выводится, только если значение реально
 * есть. Незаполненные поля не создают ни пустых строк, ни десятка
 * [УТОЧНИТЬ] на странице — они просто не попадают в разметку.
 */
final class ProductCard extends Component
{
    /**
     * Подписи характеристик. Ключи соответствуют будущим полям записи,
     * значения — тексту, который увидит посетитель.
     */
    private const SPEC_LABELS = [
        'category' => 'Категория',
        'packaging' => 'Упаковка',
        'quantity' => 'Количество',
        'weight' => 'Вес',
        'shelf_life' => 'Срок годности',
        'storage' => 'Условия хранения',
        'nutrition' => 'Пищевая ценность',
        'characteristics' => 'Характеристики',
    ];

    /**
     * @param  string  $name  Наименование позиции.
     * @param  string|null  $image  Фотография. null — локальный placeholder.
     * @param  string|null  $imageAlt  Альтернативный текст фотографии.
     * @param  string|null  $shortDescription  Краткое описание.
     * @param  array<string, string|null>  $specs  Характеристики по ключам self::SPEC_LABELS.
     * @param  string|null  $detailsUrl  Ссылка на страницу товара. null — кнопка неактивна.
     * @param  string  $detailsLabel  Подпись кнопки подробностей.
     * @param  string  $ratio  Пропорции изображения-заглушки и будущей фотографии.
     *                         16/9 — те же, что у карточек продуктов на главной,
     *                         поэтому обе страницы читаются одинаково. Пропорция
     *                         задаётся на рамке, поэтому реальная фотография
     *                         занимает ту же область и заполняет её через
     *                         object-cover.
     * @param  string|null  $additionalInfo  Дополнительная информация под характеристиками.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $image = null,
        public readonly ?string $imageAlt = null,
        public readonly ?string $shortDescription = null,
        public readonly array $specs = [],
        public readonly ?string $detailsUrl = null,
        public readonly string $detailsLabel = 'Подробнее',
        public readonly string $ratio = '16/9',
        public readonly ?string $additionalInfo = null,
    ) {}

    /**
     * Только заполненные характеристики, готовые к выводу: [подпись, значение].
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function visibleSpecs(): array
    {
        $rows = [];

        foreach ($this->specs as $key => $value) {
            $label = self::SPEC_LABELS[$key] ?? null;

            if ($label === null || $value === null) {
                continue;
            }

            /*
             * Значение обрезается, и строка пропускается, если после обрезки
             * нечего показывать. Проверка на пустую строку здесь недостаточна:
             * пробелы и переводы строк тоже дали бы посетителю строку
             * «Условия хранения» с пустым значением.
             */
            $value = trim((string) $value);

            if ($value === '') {
                continue;
            }

            $rows[] = [$label, $value];
        }

        return $rows;
    }

    public function hasDescription(): bool
    {
        return $this->shortDescription !== null && trim($this->shortDescription) !== '';
    }

    public function hasSpecs(): bool
    {
        return $this->visibleSpecs() !== [];
    }

    public function hasDetailsUrl(): bool
    {
        return $this->detailsUrl !== null && trim($this->detailsUrl) !== '';
    }

    public function isPlaceholderImage(): bool
    {
        return $this->image === null || trim($this->image) === '';
    }

    public function hasAdditionalInfo(): bool
    {
        return $this->additionalInfo !== null && trim($this->additionalInfo) !== '';
    }

    public function render(): View
    {
        return view('components.product-card');
    }
}
