const publicMenu = document.querySelector('#public-navigation');
const publicToggle = document.querySelector('.menu-toggle');
const publicHeader = document.querySelector('.public-header');

if (publicMenu && publicToggle && publicHeader) {
    const mobile = window.matchMedia('(max-width: 960px)');

    const closeMenu = (restoreFocus = false) => {
        publicMenu.hidden = mobile.matches;
        publicToggle.setAttribute('aria-expanded', 'false');
        if (restoreFocus && mobile.matches) publicToggle.focus();
    };

    const syncMenu = () => {
        const focusWillBeHidden = mobile.matches && publicMenu.contains(document.activeElement);
        const toggleHadFocus = document.activeElement === publicToggle;
        publicToggle.hidden = !mobile.matches;
        closeMenu(focusWillBeHidden);
        if (!mobile.matches && toggleHadFocus) publicMenu.querySelector('a').focus();
    };

    publicToggle.addEventListener('click', () => {
        const open = publicToggle.getAttribute('aria-expanded') !== 'true';
        publicToggle.setAttribute('aria-expanded', String(open));
        publicMenu.hidden = !open;
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobile.matches && publicToggle.getAttribute('aria-expanded') === 'true') {
            event.preventDefault();
            closeMenu(true);
        }
    });

    document.addEventListener('click', (event) => {
        if (mobile.matches && !publicHeader.contains(event.target)) closeMenu();
    });

    publicMenu.addEventListener('click', (event) => {
        if (mobile.matches && event.target.closest('a')) closeMenu(true);
    });

    mobile.addEventListener('change', syncMenu);
    syncMenu();
}
