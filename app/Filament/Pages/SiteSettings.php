<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\Settings;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Настройки сайта: страница админки для строки site_settings (id = 1).
 *
 * Поля соответствуют колонкам таблицы site_settings. Пустая ячейка означает
 * «оставить значение по умолчанию из config/site.php»: первоначальный
 * показ берёт сырые значения из базы (Settings::raw), а не результат с
 * подстановкой конфига, чтобы сохранение не переносило значения-заглушки
 * в таблицу. Сайт при чтении сам подставляет фолбэк (Settings::value).
 */
class SiteSettings extends Page
{
    /**
     * SSR-загрузка страницы лишней не нужна: форма статичная.
     */
    protected string $view = 'filament.pages.site-settings';

    protected static ?string $title = 'Настройки сайта';

    protected static ?string $navigationLabel = 'Настройки сайта';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Сайт';

    /**
     * Состояние формы: те же ключи, что и колонки site_settings.
     *
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'company_name' => Settings::raw('company_name'),
            'short_description' => Settings::raw('short_description'),
            'phone' => Settings::raw('phone'),
            'phone_secondary' => Settings::raw('phone_secondary'),
            'email' => Settings::raw('email'),
            'address' => Settings::raw('address'),
            'schedule' => Settings::raw('schedule'),
            'telegram' => Settings::raw('telegram'),
            'whatsapp' => Settings::raw('whatsapp'),
            'vk' => Settings::raw('vk'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Основные')
                    ->description('Название и короткое описание сайта (поиск, список категорий, подвал).')
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Название компании')
                            ->maxLength(255)
                            ->helperText('Появляется в шапке, подвале и в мета-данных. Пусто — берётся SITE_NAME из конфига.'),

                        Textarea::make('short_description')
                            ->label('Короткое описание')
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Подзаголовок на главной и в подвале. Пусто — берётся из конфига.'),
                    ]),

                Section::make('Контакты')
                    ->columns(2)
                    ->schema([
                        TextInput::make('phone')
                            ->label('Телефон')
                            ->helperText('Формат свободный, например «+7 (900) 000-00-00».'),
                        TextInput::make('phone_secondary')
                            ->label('Дополнительный телефон')
                            ->helperText('Необязательный. Показывается в подвале.'),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->helperText('Показывается в подвале и на странице контактов.'),
                        TextInput::make('address')
                            ->label('Адрес')
                            ->helperText('Адрес фермы/офиса для страницы контактов.'),
                        TextInput::make('schedule')
                            ->label('Режим работы')
                            ->columnSpanFull()
                            ->helperText('Например «Пн–Сб 8:00–19:00, Вс — выходной».'),
                    ]),

                Section::make('Социальные сети')
                    ->description('Заполняются по желанию. Принимаются полные ссылки или короткие формы: @username, телефон, никнейм.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('telegram')
                            ->label('Telegram'),
                        TextInput::make('whatsapp')
                            ->label('WhatsApp'),
                        TextInput::make('vk')
                            ->label('ВКонтакте'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())
                            ->alignment($this->getFormActionsAlignment())
                            ->key('form-actions'),
                    ]),
            ]);
    }

    /**
     * @return array<int, \Filament\Actions\Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Сохранить')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        // getState() проверяет валидацию полей формы.
        $data = $this->form->getState();

        SiteSetting::query()->updateOrCreate(['id' => 1], $data);

        // Сброс кэша: следующий публичный запрос увидит новые значения.
        Settings::flush();

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}