/**
 * Мобильное меню в шапке.
 *
 * Работает без фреймворков: кнопка переключает атрибут aria-expanded
 * и атрибут hidden у панели, панель закрывается по Escape, по клику
 * по ссылке и при возврате к десктопной ширине.
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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMobileMenu);
} else {
    initMobileMenu();
}
