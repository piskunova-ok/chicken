<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Проверяет те ветки карточки, которых на живых страницах сейчас нет.
 *
 * Ни в одной категории характеристики не подтверждены заказчиком, а
 * страницы товара не созданы, поэтому на сайте эти блоки не выводятся и
 * глазами их не увидеть. Тест подставляет данные напрямую и убеждается, что
 * разметка заработает, когда данные появятся из БД.
 *
 * Значения выдуманные — это проверка вёрстки, а не данные сайта.
 */
final class ProductCardRenderTest extends TestCase
{
    public function test_it_renders_only_filled_specs_as_a_definition_list(): void
    {
        $html = Blade::render(
            '<x-product-card name="Тушка курицы" :specs="$specs" />',
            [
                'specs' => [
                    'weight' => '1,2 кг',
                    'shelf_life' => null,
                    'storage' => '   ',
                    'unknown_key' => 'значение',
                ],
            ]
        );

        // Единственная заполненная характеристика превращается в dt/dd.
        $this->assertStringContainsString('Вес', $html);
        $this->assertStringContainsString('1,2 кг', $html);
        $this->assertStringContainsString('<dl', $html);

        // Незаполненные и неизвестные ключи не превращаются в строки.
        $this->assertStringNotContainsString('Срок годности', $html);
        $this->assertStringNotContainsString('Условия хранения', $html);
        $this->assertStringNotContainsString('Значение', $html);
    }

    public function test_it_omits_the_specs_block_entirely_when_there_are_none(): void
    {
        $html = Blade::render('<x-product-card name="Вариант продукции 01" />');

        $this->assertStringNotContainsString('<dl', $html);
        $this->assertStringNotContainsString('<dt', $html);
    }

    public function test_it_hides_the_details_button_entirely_without_a_details_url(): void
    {
        $html = Blade::render('<x-product-card name="Тушка курицы" />');

        // Пока страницы товара нет, неактивная кнопка «Подробнее» выглядела бы
        // как неработающая ссылка, поэтому её не выводим вообще.
        $this->assertStringNotContainsString('Подробнее', $html);
        $this->assertStringNotContainsString('data-card-details', $html);
        $this->assertStringNotContainsString('href=', $html);
        $this->assertStringNotContainsString('<button', $html);
        // Пустая обёртка с отступом снизу тоже не должна оставаться.
        $this->assertStringNotContainsString('mt-auto', $html);
    }

    public function test_it_treats_a_blank_details_url_as_absent(): void
    {
        // Пустая строка в данных не должна превращаться в ссылку с пустым href.
        $html = Blade::render('<x-product-card name="Тушка курицы" details-url="   " />');

        $this->assertStringNotContainsString('Подробнее', $html);
        $this->assertStringNotContainsString('href=', $html);
    }

    public function test_it_links_to_the_product_page_when_a_details_url_exists(): void
    {
        $html = Blade::render(
            '<x-product-card name="Тушка курицы" details-url="/products/chicken/tushka" />'
        );

        $this->assertStringContainsString('data-card-details', $html);
        $this->assertStringNotContainsString('data-card-details-pending', $html);
        $this->assertStringContainsString('href="/products/chicken/tushka"', $html);
        $this->assertStringContainsString('Подробнее', $html);
    }

    public function test_placeholder_is_silent_and_hidden_from_assistive_tech(): void
    {
        $html = Blade::render('<x-product-card name="Тушка курицы" />');

        // Подпись «Место для фотографии» повторилась бы в каждой из семи
        // карточек, поэтому тихая заглушка помечается aria-hidden.
        $this->assertStringNotContainsString('Место для фотографии', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_alt_text_reaches_the_img_tag(): void
    {
        $html = Blade::render(
            '<x-product-card name="Тушка курицы" image="/images/tushka.jpg" image-alt="Тушка курицы" />'
        );

        $this->assertStringContainsString('src="/images/tushka.jpg"', $html);
        $this->assertStringContainsString('alt="Тушка курицы"', $html);
    }
}
