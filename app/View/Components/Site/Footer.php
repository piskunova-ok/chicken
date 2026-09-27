<?php

declare(strict_types=1);

namespace App\View\Components\Site;

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

    public function render(): View
    {
        return view('components.site.footer');
    }

    /**
     * Значение-заглушка вида [ТЕЛЕФОН] / [EMAIL] не должно превращаться
     * в нерабочую ссылку tel:[ТЕЛЕФОН] или mailto:[EMAIL].
     */
    public function isPlaceholder(?string $value): bool
    {
        return $value === null || str_starts_with(trim($value), '[');
    }

    /** Ссылка на телефон либо null, если значение ещё не подтверждено. */
    public function phoneUrl(): ?string
    {
        $phone = config('site.contacts.phone');

        if (! is_string($phone) || $this->isPlaceholder($phone)) {
            return null;
        }

        $digits = preg_replace('/[^0-9+]/', '', $phone);

        return is_string($digits) && $digits !== '' ? 'tel:'.$digits : null;
    }

    /** Ссылка на почту либо null, если значение ещё не подтверждено. */
    public function emailUrl(): ?string
    {
        $email = config('site.contacts.email');

        if (! is_string($email) || $this->isPlaceholder($email) || ! str_contains($email, '@')) {
            return null;
        }

        return 'mailto:'.$email;
    }
}
