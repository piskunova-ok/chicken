/**
 * Мобильное меню в шапке и перенос фокуса к результату отправки формы.
 *
 * Оба блока работают без фреймворков. Меню переключает атрибут
 * aria-expanded и атрибут hidden у панели, панель закрывается по Escape,
 * по клику по ссылке и при возврате к десктопной ширине.
 */
const initMobileMenu = () => {
    const toggle = document.querySelector('[data-menu-toggle]');
    const menu = document.querySelector('[data-menu]');

    if (!(toggle instanceof HTMLButtonElement) || !(menu instanceof HTMLElement)) {
        return;
    }

    const iconOpen = toggle.querySelector('[data-menu-icon="open"]');
    const iconClose = toggle.querySelector('[data-menu-icon="close"]');
    const label = toggle.querySelector('.sr-only');

    const setOpen = (open) => {
        toggle.setAttribute('aria-expanded', String(open));
        menu.hidden = !open;
        document.body.classList.toggle('overflow-hidden', open);

        if (iconOpen instanceof HTMLElement) {
            iconOpen.classList.toggle('hidden', open);
        }
        if (iconClose instanceof HTMLElement) {
            iconClose.classList.toggle('hidden', !open);
        }
        if (label) {
            label.textContent = open ? 'Закрыть меню' : 'Открыть меню';
        }
    };

    const isOpen = () => toggle.getAttribute('aria-expanded') === 'true';

    toggle.addEventListener('click', () => setOpen(!isOpen()));

    menu.addEventListener('click', (event) => {
        if (event.target instanceof Element && event.target.closest('a')) {
            setOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen()) {
            setOpen(false);
            toggle.focus();
        }
    });

    const desktop = window.matchMedia('(min-width: 64rem)');
    desktop.addEventListener('change', (event) => {
        if (event.matches && isOpen()) {
            setOpen(false);
        }
    });
};

/**
 * Перенос фокуса к результату отправки формы.
 *
 * После отправки форма показывает либо сообщение об успехе, либо сводку
 * ошибок. Обе они приходят вместе с новой загрузкой страницы, поэтому
 * экранный диктор такую live-область обычно не озвучивает. Перенос фокуса
 * на неё работает всегда, и клавиатура сразу оказывается у результата.
 *
 * Разметка с data-form-result появляется только после отправки, так что
 * на обычном открытии страницы фокус никто не перехватывает.
 */
const initFormResultFocus = () => {
    const result = document.querySelector('[data-form-result]');

    if (!(result instanceof HTMLElement) || !result.hasAttribute('tabindex')) {
        return;
    }

    result.focus();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initMobileMenu();
        initFormResultFocus();
    });
} else {
    initMobileMenu();
    initFormResultFocus();
}
