<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\QualityCertificate;
use Illuminate\Contracts\View\View;

/**
 * Страница «Качество»: /quality.
 *
 * Раньше это был Route::view без данных: заголовки, лид и четыре пункта
 * приходили из config/content.php. Теперь на странице есть ещё блок
 * сертификатов, а его состав берётся из базы: владелец загружает документы
 * из админки, и страница показывает только активные, в заданном порядке.
 *
 * Оформление и общие тексты по-прежнему живут в конфиге — из базы приходят
 * ровно сертификаты. Пустой список — норма: блок на странице не выводится,
 * и она выглядит как раньше, без выдуманных документов.
 */
final class QualityController extends Controller
{
    public function index(): View
    {
        /*
         * Отбор делает база, а не PHP: только активные документы и порядок
         * sort_order с запасным ключом id. Второй ключ удерживает выдачу
         * стабильной, когда у нескольких документов порядок совпадает.
         */
        $certificates = QualityCertificate::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('site.quality', [
            'certificates' => $certificates,
        ]);
    }
}
