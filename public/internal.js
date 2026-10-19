document.querySelectorAll('[data-pending-form]').forEach((form) => {
    const button = form.querySelector('[data-pending-label]');
    if (!button) return;
    const originalLabel = button.textContent;
    form.addEventListener('submit', () => {
        button.disabled = true;
        button.textContent = button.dataset.pendingLabel;
        form.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', () => {
        button.disabled = false;
        button.textContent = originalLabel;
        form.removeAttribute('aria-busy');
    });
});

const sidebar = document.querySelector('#internal-sidebar');
const sidebarTrigger = document.querySelector('[data-sidebar-open]');
const workspace = document.querySelector('[data-shell-workspace]');
const overlay = document.querySelector('.sidebar-overlay');
const mobileSidebar = window.matchMedia('(max-width: 1023px)');

if (sidebar && sidebarTrigger && workspace && overlay) {
    const closeSidebar = (restoreFocus = true) => {
        document.body.classList.remove('sidebar-open');
        overlay.hidden = true;
        workspace.inert = false;
        sidebarTrigger.setAttribute('aria-expanded', 'false');
        sidebar.removeAttribute('role');
        sidebar.removeAttribute('aria-modal');
        if (restoreFocus) sidebarTrigger.focus();
    };

    sidebarTrigger.addEventListener('click', () => {
        document.body.classList.add('sidebar-open');
        overlay.hidden = false;
        sidebarTrigger.setAttribute('aria-expanded', 'true');
        sidebar.setAttribute('role', 'dialog');
        sidebar.setAttribute('aria-modal', 'true');
        workspace.inert = true;
        sidebar.querySelector('[data-sidebar-close]').focus();
    });

    document.querySelectorAll('[data-sidebar-close]').forEach((button) => {
        button.addEventListener('click', () => closeSidebar());
    });

    document.addEventListener('keydown', (event) => {
        if (!document.body.classList.contains('sidebar-open')) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            closeSidebar();
        }
        if (event.key === 'Tab') {
            const controls = [...sidebar.querySelectorAll('a[href], button')].filter((element) => element.getClientRects().length);
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    mobileSidebar.addEventListener('change', () => closeSidebar(false));
}

document.querySelectorAll('.shell-popover').forEach((popover) => {
    popover.addEventListener('toggle', () => {
        if (popover.open) {
            document.querySelectorAll('.shell-popover').forEach((other) => {
                if (other !== popover) other.open = false;
            });
        }
    });
    popover.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            popover.open = false;
            popover.querySelector('summary').focus();
        }
    });
    document.addEventListener('click', (event) => {
        if (!popover.contains(event.target)) popover.open = false;
    });
});

document.querySelectorAll('[data-user-role]').forEach((roleSelect) => {
    const updateScope = () => {
        const scope = roleSelect.selectedOptions[0]?.dataset.scope;
        roleSelect.form.querySelectorAll('[data-user-scope]').forEach((group) => {
            const relevant = group.dataset.userScope === scope;
            group.hidden = !relevant;
            group.querySelectorAll('select').forEach((select) => {
                select.disabled = !relevant;
                select.required = relevant;
            });
        });
    };
    roleSelect.addEventListener('change', updateScope);
    updateScope();
});

document.querySelectorAll('[data-document-file]').forEach((input) => {
    input.addEventListener('change', () => {
        const file = input.files[0];
        const info = input.form.querySelector('[data-document-file-info]');
        if (info) {
            info.textContent = file ? file.name + ' (' + (file.size / 1048576).toFixed(2) + ' MB)' : '';
        }
    });
});
