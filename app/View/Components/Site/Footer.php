<?php

declare(strict_types=1);

namespace App\View\Components\Site;

use App\Support\ContactLinks;
use App\Support\SiteLinks;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Подвал сайта: контакты, навигация, правовые документы и место под
 * будущую ссылку на площадку партнёра.
 */
final class Footer extends Component
{
    /**
     * @return array<int, array{label: string, url: string|null}>
     */
    public function navigation(): array
    {
        return SiteLinks::resolve(config('site.footer_navigation'));
    }

    /**
     * @return array<int, array{label: string, url: string|null}>
     */
    public function legalLinks(): array
    {
        return SiteLinks::resolve(config('site.legal_links'));
    }

    /**
     * @return array<int, array{label: string, url: string|null}>
     */
    public function partners(): array
    {
        return SiteLinks::resolveExternal(config('site.partners'));
    }

    /** Значение-заглушка вида [ТЕЛЕФОН] / [EMAIL]. */
    public function isPlaceholder(?string $value): bool
    {
        return ContactLinks::isPlaceholder($value);
    }

    /** Ссылка на телефон либо null, если значение ещё не подтверждено. */
    public function phoneUrl(): ?string
    {
        return ContactLinks::phoneUrl();
    }

    /** Ссылка на почту либо null, если значение ещё не подтверждено. */
    public function emailUrl(): ?string
    {
        return ContactLinks::emailUrl();
    }

    public function render(): View
    {
        return view('components.site.footer');
    }
}
