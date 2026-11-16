const drawer = document.querySelector('#public-drawer');
const trigger = document.querySelector('.menu-toggle');
const overlay = document.querySelector('.public-nav-overlay');

if (drawer && trigger && overlay) {
    const mobile = window.matchMedia('(max-width: 1023px)');
    const closeButton = drawer.querySelector('.public-menu-close');
    const background = document.querySelectorAll('.header-top, body > main, body > footer, .public-skip');
    let opened = false;
    document.documentElement.classList.add('public-nav-ready');

    const close = (restoreFocus = true) => {
        opened = false;
        document.body.classList.remove('public-nav-open');
        background.forEach(element => { element.inert = false; });
        overlay.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        drawer.removeAttribute('role');
        drawer.removeAttribute('aria-modal');
        drawer.inert = mobile.matches;
        if (mobile.matches) drawer.setAttribute('aria-hidden', 'true');
        else drawer.removeAttribute('aria-hidden');
        if (restoreFocus && mobile.matches) trigger.focus();
    };

    const sync = () => {
        const focusInside = drawer.contains(document.activeElement);
        const focusOnTrigger = document.activeElement === trigger;
        close(focusInside);
        if (!mobile.matches && (focusInside || focusOnTrigger)) drawer.querySelector('a').focus();
    };

    trigger.addEventListener('click', () => {
        if (!mobile.matches) return;
        opened = true;
        drawer.inert = false;
        drawer.removeAttribute('aria-hidden');
        drawer.setAttribute('role', 'dialog');
        drawer.setAttribute('aria-modal', 'true');
        trigger.setAttribute('aria-expanded', 'true');
        overlay.hidden = false;
        document.body.classList.add('public-nav-open');
        requestAnimationFrame(() => { if (opened) closeButton.focus(); });
        background.forEach(element => { element.inert = true; });
    });
    closeButton.addEventListener('click', () => close());
    overlay.addEventListener('click', () => close());
    drawer.addEventListener('click', event => {
        if (opened && event.target.closest('a')) close();
    });
    document.addEventListener('keydown', event => {
        if (!opened) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        } else if (event.key === 'Tab') {
            const controls = [...drawer.querySelectorAll('button, a[href]')];
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (!drawer.contains(document.activeElement)) {
                event.preventDefault(); first.focus();
            } else if (event.shiftKey && document.activeElement === first) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault(); first.focus();
            }
        }
    });
    mobile.addEventListener('change', sync);
    window.addEventListener('pageshow', sync);
    sync();
}
