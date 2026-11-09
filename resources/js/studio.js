/* Gomovie shared account/studio interactions. No public frontend DOM mutation. */
const isStudio = document.querySelector('.gm-studio-shell, .gm-account-shell, .gm-appearance-workspace');
if (isStudio) {
    let floating = null;
    let trigger = null;
    const closeMenu = () => {
        floating?.remove();
        floating = null;
        if (trigger) trigger.setAttribute('aria-expanded', 'false');
        trigger = null;
    };
    const openDialog = (id) => {
        const dialog = document.getElementById(id);
        if (!(dialog instanceof HTMLDialogElement)) return;
        closeMenu();
        if (!dialog.isConnected || dialog.parentElement !== document.body) document.body.appendChild(dialog);
        if (!dialog.open) dialog.showModal();
        const focus = dialog.querySelector('[autofocus],input:not([type="hidden"]),select,textarea,button');
        focus?.focus({preventScroll:true});
    };
    document.addEventListener('click', (event) => {
        const modalOpen = event.target.closest('[data-studio-open]');
        if (modalOpen) {event.preventDefault();openDialog(modalOpen.dataset.studioOpen);return;}
        const modalClose = event.target.closest('[data-studio-close]');
        if (modalClose) {event.preventDefault();modalClose.closest('dialog')?.close();return;}
        const dot = event.target.closest('[data-studio-menu]');
        if (dot) {
            event.preventDefault();
            const same = dot === trigger;
            closeMenu();
            if (same) return;
            const template = document.getElementById(dot.dataset.studioMenu);
            if (!(template instanceof HTMLTemplateElement)) return;
            const el = document.createElement('div');
            el.className = 'gm-floating-actions';
            el.setAttribute('role','menu');
            el.setAttribute('aria-label',dot.getAttribute('aria-label') || 'Actions');
            el.appendChild(template.content.cloneNode(true));
            document.body.appendChild(el);
            const box = dot.getBoundingClientRect();
            const width = el.offsetWidth;
            const height = el.offsetHeight;
            const right = Math.min(window.innerWidth - width - 12,Math.max(12,box.right - width));
            const top = box.bottom + 6 + height > window.innerHeight
                ? Math.max(12,box.top - height - 6) : box.bottom + 6;
            el.style.left = right + 'px';
            el.style.top = top + 'px';
            floating=el; trigger=dot;
            dot.setAttribute('aria-expanded','true');
            el.querySelector('a,button')?.focus({preventScroll:true});
            return;
        }
        if (!event.target.closest('.gm-floating-actions')) closeMenu();
        const action = event.target.closest('[data-studio-confirm]');
        if (action && !window.confirm(action.dataset.studioConfirm || 'Confirm this action?')) event.preventDefault();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeMenu();
    });
    window.addEventListener('scroll', closeMenu, true);
    window.addEventListener('resize', closeMenu);
    document.querySelectorAll('dialog.gm-studio-modal').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
    });
}
