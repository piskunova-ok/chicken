<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Единственная строка настроек сайта (id = 1), редактируемая в админке.
 *
 * Пустые ячейки означают «использовать значение по умолчанию»: сайт сначала
 * смотрит в эту таблицу, а для пустых полей — в config/site.php и переменные
 * окружения. Логика чтения живёт в App\Support\Settings, модель хранит
 * только данные.
 *
 * $guarded = [] не используется по тем же причинам, что и в остальных
 * моделях проекта: явный #[Fillable] фиксирует контракт управляемых полей.
 */
#[Fillable([
    'company_name',
    'short_description',
    'logo',
    'phone',
    'phone_secondary',
    'email',
    'address',
    'schedule',
    'telegram',
    'whatsapp',
    'vk',
])]
class SiteSetting extends Model {}
