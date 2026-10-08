/**
 * Мобильное меню в шапке, отправка формы обратной связи в Web3Forms и
 * перенос фокуса к результату отправки.
 *
 * Все три блока работают без фреймворков.
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
 * Отправка формы обратной связи прямо в Web3Forms.
 *
 * Форма, помеченная data-contact-form, постится fetch-запросом на адрес из
 * своего же action — это https://api.web3forms.com/submit. Сервер Laravel в
 * отправке не участвует, поэтому CSRF-токен не нужен. FormData берётся у
 * самой формы: скрытые access_key, subject и from_name и видимые поля
 * name, phone, email, message и consent уходят единой логикой, без
 * дублирования списков полей в коде.
 *
 * Успехом считается только ответ API с success === true. Любой другой
 * исход — отказ, недоступность, не-JSON-ответ, исключение — показывает
 * скрытый блок с понятной ошибкой; его текст отрендерен заранее из
 * config/content.php, поэтому причина сбоя на страницу не попадает.
 * После успеха поля очищаются, чтобы следующий посетитель не увидел чужие
 * данные, а повторная отправка не отправила их вторично.
 */
const initContactForm = () => {
    const form = document.querySelector('form[data-contact-form]');

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const status = document.querySelector('#contact-form-status');
    const error = document.querySelector('#contact-form-mail-error');
    const button = form.querySelector('button[type="submit"]');

    const reveal = (target) => {
        [status, error].forEach((block) => {
            if (block instanceof HTMLElement && block !== target) {
                block.hidden = true;
            }
        });

        if (target instanceof HTMLElement) {
            target.hidden = false;
            target.focus();
            target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (button instanceof HTMLButtonElement) {
            button.disabled = true;
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
            });

            const result = await response.json();

            if (result && result.success === true) {
                reveal(status);
                form.reset();
            } else {
                reveal(error);
            }
        } catch (exception) {
            reveal(error);
        } finally {
            if (button instanceof HTMLButtonElement) {
                button.disabled = false;
            }
        }
    });
};

/**
 * Перенос фокуса к результату отправки формы.
 *
 * Блоки результата (data-form-result) рендерятся на странице всегда, но
 * скрыты атрибутом hidden: клиентская отправка показывает нужный блок и
 * фокусирует его сама, без перезагрузки. Этот init остаётся как страховка
 * на случай серверного рендера видимого результата — но скрытые блоки он
 * игнорирует, чтобы обычное открытие страницы не перехватывало фокус.
 */
const initFormResultFocus = () => {
    const result = document.querySelector('[data-form-result]:not([hidden])');

    if (!(result instanceof HTMLElement) || !result.hasAttribute('tabindex')) {
        return;
    }

    result.focus();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initMobileMenu();
        initContactForm();
        initFormResultFocus();
    });
} else {
    initMobileMenu();
    initContactForm();
    initFormResultFocus();
}