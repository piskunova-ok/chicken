{{--
    ВРЕМЕННАЯ СТРАНИЦА КАТЕГОРИИ.

    Создана, чтобы на главной работали настоящие ссылки на категории
    (products.eggs -> /products/eggs, products.chicken -> /products/chicken),
    а не заглушки. Полноценные каталоги разрабатываются на следующем этапе.

    Что здесь намеренно НЕ указано: состав ассортимента, цены, объёмы,
    сроки, упаковка и любые характеристики продукции. Эти данные должны быть
    подтверждены владельцем проекта, поэтому страница сообщает только о том,
    что раздел готовится, и ведёт обратно на главную.
--}}
@php
    $backUrl = route('home').'#products';
@endphp

<x-layouts.app
    :title="$product['name'].' — '.config('site.name')"
    :description="$product['description']"
>
    <x-section tone="canvas" spacing="loose">
        <div class="grid gap-10 lg:grid-cols-2 lg:items-center lg:gap-16">
            <div>
                <x-section-heading
                    eyebrow="Категория продукции"
                    :title="$product['name']"
                    :lead="$product['description']"
                    level="h1"
                />

                <div
                    class="mt-8 rounded-card border border-line bg-surface p-6"
                    data-category-placeholder
                >
                    <p class="text-body font-semibold text-ink">
                        Раздел готовится
                    </p>
                    <p class="mt-2 text-body text-ink-muted">
                        Описание категории, ассортимент и условия поставки появятся
                        здесь позже. Мы не публикуем сведения о продукции,
                        которые ещё не подтверждены.
                    </p>
                </div>

                <x-button
                    label="Вернуться к продукции"
                    :href="$backUrl"
                    variant="secondary"
                    class="mt-8"
                />
            </div>

            <x-media variant="card" ratio="4/3" />
        </div>
    </x-section>
</x-layouts.app>
