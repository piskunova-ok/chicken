<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * Форма товара: создание и редактирование.
 *
 * ПОЛЯ РОВНО ТЕ, ЧТО ЕСТЬ В ТАБЛИЦЕ products
 *
 * В форме одиннадцать полей, и это все колонки products, кроме служебных
 * (id, image, created_at, updated_at). Выдумывать поля, которых в схеме нет,
 * нельзя, а служебные колонки администратору нечего редактировать.
 *
 * ЧТО НЕ ПОКАЗАНО И ПОЧЕМУ
 *
 * image в форму не выведено. Колонка есть, но загрузка файлов и работа с
 * хранилищем — отдельная задача: на этом этапе не создаётся FileUpload, не
 * появляется storage:link и не меняется конфигурация файловой системы.
 * Показывать поле только для чтения смысла не имеет: единственное
 * существующее значение лучше видно в самой карточке товара на сайте.
 *
 * РАЗДЕЛЕНИЕ НА ДВЕ ЧАСТИ
 *
 * Сначала «Куда и как называть» (категория, название, slug), затем
 * «Про товар» (описание и характеристики), затем «Показ» (активность,
 * порядок). Порядок совпадает с порядком вопросов к администратору, а не с
 * порядком колонок в миграции.
 *
 * ПУСТЫЕ ЗНАЧЕНИЯ — ЭТО NULL
 *
 * short_description, weight, packaging, storage, shelf_life и
 * additional_info в базе nullable. Filament при dehydrate() отдаёт null
 * для пустой строки, и Laravel пишет NULL — то есть «нет данных»
 * хранится как отсутствие данных, а не как «-», «нет» или «[УТОЧНИТЬ]».
 * Плейсхолдеры в полях поэтому означают «что сюда писать», а не «чем
 * заменить пустое значение», и подставляются в форму, но не в запись.
 *
 * ТЕКСТЫ ОШИБОК СВОИ, А НЕ ИЗ ПЕРЕВОДОВ
 *
 * В проекте нет каталога lang/, а локаль приложения — ru. Переводы Laravel
 * лежат только в вендоре и только на английском, поэтому trans() для
 * локали ru отдаёт сам ключ: на отказе по адресу панель показывала
 * «validation.unique», а не «Значение поля уже занято». Общий lang/ для
 * этого этапа не создаётся — это отдельная задача по всему приложению, —
 * поэтому нужные тексты заданы прямо на компонентах формы.
 */
class ProductForm
{
    /**
     * Русские тексты ошибок валидации полей товара.
     *
     * Ключ массива — имя правила, значение — текст для администратора.
     * «:attribute» — подпись поля строчными буквами, как её отдаёт
     * Filament (Str::lcfirst в getValidationAttribute), поэтому «:attribute»
     * стоит внутри предложения: «поле «название» обязательно».
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private static function validationMessages(array $overrides = []): array
    {
        return [
            'required' => 'Поле «:attribute» обязательно для заполнения.',
            'max' => 'Значение поля «:attribute» не должно быть длиннее :max символов.',
            'integer' => 'Значение поля «:attribute» должно быть целым числом.',
            'min' => 'Значение поля «:attribute» не может быть меньше :min.',
            'exists' => 'Выбранной категории больше нет.',
            'unique' => 'В выбранной категории уже есть товар с таким адресом.',
            ...$overrides,
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                /*
                 * Категория выбирается из списка названий, а не вводится
                 * числом: администратор оперирует «Яйца кур» и «Мясо кур».
                 * relationship('category', 'name') берёт значения прямо из
                 * связи, поэтому ProductCategory::query() в коде формы нет,
                 * а переименование категории в панели сразу отражается
                 * здесь. В базу уходит product_category_id — тот самый
                 * внешний ключ, который ждёт колонка.
                 */
                Select::make('product_category_id')
                    ->label('Категория')
                    ->relationship(
                        'category',
                        'name',
                        /*
                         * Неактивные категории скрыты.
                         *
                         * Список берётся по is_active = true: назначить
                         * товар в раздел, который не открывается на сайте, —
                         * неочевидная ошибка, заметить её можно только на
                         * публичной странице.
                         *
                         * Фильтр передаётся третьим аргументом relationship(),
                         * а не отдельным вызовом modifyQueryUsing(): такого
                         * метода у Select в Filament 5 нет, и вызов
                         * modifyQueryUsing() упал бы с BadMethodCallException
                         * при отрисовке формы.
                         *
                         * ЗАЩИТА ОТ «ИСЧЕЗНОВШЕЙ» ЗАПИСИ. Условие строится
                         * через orWhere по id текущей категории, поэтому
                         * товар, чья категория уже выключена, продолжает
                         * редактироваться. Иначе форма показала бы пустое
                         * поле «Категория» у существующего товара, и её
                         * сохранение молча обнулило бы внешний ключ, то
                         * есть потеряло бы товар из его раздела.
                         *
                         * $record внедряется в замыкание по имени и типу
                         * (Model) — штатный механизм evaluate() у компонента
                         * схемы. При создании записи нет, $record равен
                         * null, и orWhere не добавляется.
                         *
                         * Тип первого аргумента — Eloquent\Builder, а не
                         * Query\Builder: relationship() передаёт запрос
                         * связанной модели. С аннотацией Query\Builder
                         * замыкание падало бы с TypeError при каждой
                         * отрисовке формы.
                         */
                        modifyQueryUsing: fn (Builder $query, ?Product $record): Builder => $query
                            ->where('is_active', true)
                            ->when(
                                $record?->product_category_id,
                                fn (Builder $query, mixed $currentCategoryId): Builder => $query
                                    ->orWhere('id', $currentCategoryId),
                            ),
                    )
                    ->required()
                    ->searchable()
                    ->preload()
                    ->validationMessages(self::validationMessages())
                    ->helperText('Товар показывается на странице своей категории, пока активен он сам и его категория.'),

                TextInput::make('name')
                    ->label('Название')
                    ->required()
                    // 255 — длина string-колонки из миграции.
                    ->maxLength(255)
                    ->validationMessages(self::validationMessages()),

                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Часть адреса товара. Одинаковый slug в разных категориях допустим, внутри одной категории — нет.')
                    /*
                     * УНИКАЛЬНОСТЬ СОСТАВНАЯ, А НЕ ГЛОБАЛЬНАЯ
                     *
                     * В базе стоит индекс products(['product_category_id',
                     * 'slug'])->unique(), то есть адрес товара задаётся парой
                     * «категория + товар» (см. шапку миграции). Проверка
                     * формы повторяет это правило, а не глобальный
                     * unique(slug):
                     *
                     *  - Rule::unique(...)->where('product_category_id', …)
                     *    добавляет к проверке равенство категории, и два
                     *    товара с одинаковым slug в РАЗНЫХ категориях
                     *    проходят — как в базе;
                     *  - global unique(slug) запретил бы, например, «mix» в
                     *    яйцах и в мясе, хотя такой товар осмыслен, и
                     *    заставил бы разводить слаг суффиксами;
                     *  - категория берётся из СОСТОЯНИЯ ФОРМЫ через $get, а не
                     *    из записи: при смене категории проверяется slug в
                     *    той категории, которую администратор выбрал, а не в
                     *    старой.
                     *
                     * Текущая запись исключается из проверки штатно: unique()
                     * по умолчанию игнорирует редактируемый record, поэтому
                     * сохранение товара без изменения slug не конфликтует
                     * само с собой. Автоматическая генерация slug не
                     * добавлена намеренно: администратор вводит его явно, и
                     * скрытое переписывание значения ломало бы составную
                     * проверку и адреса.
                     */
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                            ->where('product_category_id', $get('product_category_id')),
                    )
                    ->validationMessages(self::validationMessages()),

                Textarea::make('short_description')
                    ->label('Краткое описание')
                    ->rows(3)
                    // text-колонка, поэтому ограничение длины здесь не
                    // нужно: в отличие от string, длина не проверяется ни
                    // в SQLite, ни в других СУБД.
                    ->columnSpanFull(),

                TextInput::make('weight')
                    ->label('Вес')
                    ->maxLength(255)
                    // string, а не число: значения приходят текстом
                    // («от 1 кг»), и числовой тип заставил бы выбирать между
                    // потерей данных и разбором единиц.
                    ->placeholder('например: от 1 кг')
                    ->validationMessages(self::validationMessages()),

                TextInput::make('shelf_life')
                    ->label('Срок годности')
                    ->maxLength(255)
                    ->placeholder('например: 5 суток')
                    ->validationMessages(self::validationMessages()),

                Textarea::make('packaging')
                    ->label('Упаковка')
                    ->rows(2)
                    ->columnSpanFull(),

                Textarea::make('storage')
                    ->label('Условия хранения')
                    ->rows(2)
                    ->columnSpanFull(),

                Textarea::make('additional_info')
                    ->label('Дополнительная информация')
                    ->rows(2)
                    ->columnSpanFull(),

                Toggle::make('is_active')
                    ->label('Активен')
                    ->default(true)
                    ->helperText('Выключенный товар исчезает с публичной страницы категории, но его запись и адрес сохраняются. Это замена удалению на первом этапе админки.'),

                TextInput::make('sort_order')
                    ->label('Порядок')
                    ->required()
                    ->integer()
                    // Колонка объявлена unsignedInteger, поэтому
                    // отрицательное значение не имеет смысла и отсекается
                    // формой, а не ошибкой базы. Ноль допустим: так
                    // объявлено значение по умолчанию в миграции.
                    ->minValue(0)
                    ->default(0)
                    ->validationMessages(self::validationMessages())
                    ->helperText('Меньшие значения выводятся раньше внутри своей категории.'),
            ]);
    }
}
