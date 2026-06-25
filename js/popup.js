document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.getElementById('popup-overlay');
    if (!overlay) return;

    const body     = document.getElementById('popup-body');
    const closeBtn = document.getElementById('popup-close');

    const close = () => {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    };

    // Always reset on load — covers fresh loads, bfcache restores, and any other edge case
    close();
    window.addEventListener('pageshow', close);

    document.querySelectorAll('[data-popup]').forEach(el => {
        el.addEventListener('click', e => {
            if (e.target.closest('button, a, form')) return;
            const src = document.getElementById(el.dataset.popup);
            if (!src) return;
            body.innerHTML = src.innerHTML;
            body.dataset.sourceId = el.dataset.popup;
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    });

    closeBtn.addEventListener('click', close);
    overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
});
