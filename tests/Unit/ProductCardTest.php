<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\View\Components\ProductCard;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет правило, на котором держится карточка каталога: характеристика
 * выводится, только если значение реально есть. Пустые значения не должны
 * превращаться в пустые строки или в [УТОЧНИТЬ] на странице.
 *
 * Данные в тесте выдуманные намеренно — это не данные сайта, а проверка
 * логики фильтрации. Реальные характеристики заказчика на этом этапе
 * отсутствуют, и в config/catalog.php их нет.
 */
final class ProductCardTest extends TestCase
{
    public function test_it_collects_only_filled_specs(): void
    {
        $card = new ProductCard(
            name: 'Тушка курицы',
            specs: [
                'category' => 'Первая категория',
                'weight' => '1,2 кг',
                'shelf_life' => null,
                'storage' => '',
                'unknown_key' => 'значение',
            ],
        );

        $this->assertSame([
            ['Категория', 'Первая категория'],
            ['Вес', '1,2 кг'],
        ], $card->visibleSpecs());
    }

    public function test_it_skips_whitespace_only_spec_values(): void
    {
        // Пробелы и перевод строки не должны превращаться в строку
        // «Условия хранения» с пустым значением.
        $card = new ProductCard(
            name: 'X',
            specs: [
                'storage' => '   ',
                'weight' => "\n",
                'shelf_life' => ' 5 суток ',
            ],
        );

        $this->assertSame([['Срок годности', '5 суток']], $card->visibleSpecs());
    }

    public function test_it_supports_the_characteristics_field(): void
    {
        $card = new ProductCard(
            name: 'X',
            specs: ['characteristics' => 'Свежая, охлаждённая'],
        );

        $this->assertSame([['Характеристики', 'Свежая, охлаждённая']], $card->visibleSpecs());
    }

    public function test_it_collects_nothing_when_specs_are_absent(): void
    {
        $card = new ProductCard(name: 'Вариант продукции 01');

        $this->assertSame([], $card->visibleSpecs());
        $this->assertFalse($card->hasSpecs());
    }

    public function test_it_ignores_whitespace_only_description(): void
    {
        $this->assertFalse((new ProductCard(name: 'X', shortDescription: "  \n "))->hasDescription());
        $this->assertTrue((new ProductCard(name: 'X', shortDescription: 'Описание'))->hasDescription());
        $this->assertFalse((new ProductCard(name: 'X'))->hasDescription());
    }

    public function test_it_detects_placeholder_image(): void
    {
        $this->assertTrue((new ProductCard(name: 'X'))->isPlaceholderImage());
        $this->assertTrue((new ProductCard(name: 'X', image: ''))->isPlaceholderImage());
        $this->assertFalse((new ProductCard(name: 'X', image: 'images/02_eggs.jpg'))->isPlaceholderImage());
    }

    public function test_it_reports_whether_a_details_url_exists(): void
    {
        $this->assertFalse((new ProductCard(name: 'X'))->hasDetailsUrl());
        $this->assertFalse((new ProductCard(name: 'X', detailsUrl: null))->hasDetailsUrl());
        // Пустая строка из данных не считается ссылкой: иначе получится href="".
        $this->assertFalse((new ProductCard(name: 'X', detailsUrl: ''))->hasDetailsUrl());
        $this->assertFalse((new ProductCard(name: 'X', detailsUrl: "  \n"))->hasDetailsUrl());
        $this->assertTrue((new ProductCard(name: 'X', detailsUrl: '/products/chicken/tushka'))->hasDetailsUrl());
    }

    public function test_it_uses_the_same_ratio_as_the_product_cards_on_the_home_page(): void
    {
        $this->assertSame('16/9', (new ProductCard(name: 'X'))->ratio);
    }
}
