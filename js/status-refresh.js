const T = window.T || {};
const STATUS_STEPS = ['pending', 'paid', 'dispatched', 'delivered', 'completed'];
const STATUS_LABELS = {
    pending:    T.st_pending    || 'Payment<br>submitted',
    paid:       T.st_paid       || 'Payment<br>confirmed',
    dispatched: T.st_dispatched || 'Dispatched',
    delivered:  T.st_delivered  || 'Delivered',
    completed:  T.st_completed  || 'Completed',
};

const REFUND_STEPS = ['refund_requested', 'return_approved', 'return_dispatched', 'return_received', 'refunded'];
const REFUND_LABELS = {
    refund_requested:  T.st_refund_requested  || 'Refund<br>Requested',
    return_approved:   T.st_return_approved   || 'Return<br>Approved',
    return_dispatched: T.st_return_dispatched || 'Return<br>Sent',
    return_received:   T.st_return_received   || 'Item<br>Received',
    refunded:          T.st_refunded          || 'Refunded',
};

function renderStatusBar(status) {
    if (status === 'cancelled') {
        return '<div class="ostatus-bar ostatus-cancelled">' + (T.order_cancelled || 'Order cancelled') + '</div>';
    }
    if (status === 'refund_rejected') {
        return '<div class="ostatus-bar ostatus-cancelled">' + (T.refund_rejected || 'Refund rejected') + '</div>';
    }
    if (REFUND_STEPS.includes(status)) {
        const idx = REFUND_STEPS.indexOf(status);
        let html = '<div class="ostatus-bar">';
        REFUND_STEPS.forEach((key, i) => {
            const cls = i < idx ? 'done' : i === idx ? 'active refund-active' : 'upcoming';
            html += `<div class="ostatus-step ${cls}"><div class="ostatus-dot"></div><span class="ostatus-label">${REFUND_LABELS[key]}</span></div>`;
            if (i < REFUND_STEPS.length - 1) html += `<div class="ostatus-line${i < idx ? ' done' : ''}"></div>`;
        });
        html += '</div>';
        return html;
    }
    const idx = STATUS_STEPS.indexOf(status);
    if (idx === -1) return '';
    let html = '<div class="ostatus-bar">';
    STATUS_STEPS.forEach((key, i) => {
        const cls = i < idx ? 'done' : i === idx ? 'active' : 'upcoming';
        html += `<div class="ostatus-step ${cls}">`;
        html += `<div class="ostatus-dot"></div>`;
        html += `<span class="ostatus-label">${STATUS_LABELS[key]}</span>`;
        html += `</div>`;
        if (i < STATUS_STEPS.length - 1) {
            html += `<div class="ostatus-line${i < idx ? ' done' : ''}"></div>`;
        }
    });
    html += '</div>';
    return html;
}

let _activeToast = null;

function showToast(message, isError) {
    if (_activeToast) {
        clearTimeout(_activeToast._timer);
        _activeToast.remove();
    }
    const toast = document.createElement('div');
    toast.className = 'toast-bar' + (isError ? ' toast-bar--error' : '');
    toast.innerHTML = message;
    document.body.appendChild(toast);
    _activeToast = toast;
    setTimeout(() => toast.classList.add('toast-bar--visible'), 10);
    toast._timer = setTimeout(() => {
        toast.classList.remove('toast-bar--visible');
        setTimeout(() => {
            toast.remove();
            if (_activeToast === toast) _activeToast = null;
        }, 300);
    }, 3000);
}

function syncStatusBar(container, barHtml) {
    const wrapper = container.querySelector('[data-status-bar]');
    if (!wrapper) return;
    const bar = wrapper.querySelector('.ostatus-bar');
    if (bar) bar.outerHTML = barHtml;
}

function syncActionButtons(container, newStatus) {
    container.querySelectorAll('[data-action-status]').forEach(el => {
        el.style.display = el.dataset.actionStatus === newStatus ? '' : 'none';
    });
}

async function refreshCard(card, { loginUrl, isAdminFilter, popupBody }) {
    const orderId       = card.dataset.orderId;
    const orderRef      = card.dataset.orderRef;
    const currentStatus = card.dataset.status;
    const popupId       = card.dataset.popup;

    try {
        const res = await fetch(`/api/order-status.php?order_id=${encodeURIComponent(orderId)}`);

        if (res.status === 401) {
            showToast(`${T.session_expired || 'Session expired —'} <a href="${loginUrl}">${T.login_again || 'please log in again'}</a>`, true);
            return;
        }

        const data = await res.json();

        if (data.error) {
            showToast(T.refresh_error || 'Could not refresh — try again.', true);
            return;
        }

        const newStatus = data.status;

        if (newStatus !== currentStatus) {
            const barHtml = renderStatusBar(newStatus);

            syncStatusBar(card, barHtml);
            syncActionButtons(card, newStatus);
            card.dataset.status = newStatus;

            if (popupId) {
                const src = document.getElementById(popupId);
                if (src) {
                    syncStatusBar(src, barHtml);
                    syncActionButtons(src, newStatus);
                }
                if (popupBody && popupBody.dataset.sourceId === popupId) {
                    syncStatusBar(popupBody, barHtml);
                    syncActionButtons(popupBody, newStatus);
                }
            }

            let toastMsg = (T.order_updated ? T.order_updated.replace('%s', orderRef) : `Order #${orderRef} updated`);
            if (isAdminFilter) {
                toastMsg += ' — Reload page to remove from this filter';
            }
            showToast(toastMsg, false);
        }
    } catch {
        showToast(T.refresh_error || 'Could not refresh — try again.', true);
    }
}

export function initStatusRefresh(options = {}) {
    const loginUrl      = options.loginUrl || '/login-buyer/';
    const isAdminFilter = !!options.isAdminFilter;
    const popupBody     = document.getElementById('popup-body');
    const btn           = document.querySelector('[data-refresh-all-btn]');
    if (!btn) return;

    btn.addEventListener('click', async () => {
        btn.disabled = true;
        btn.classList.add('is-spinning');

        const cards = [...document.querySelectorAll('[data-order-id]')];
        try {
            await Promise.all([
                Promise.all(cards.map(card => refreshCard(card, { loginUrl, isAdminFilter, popupBody }))),
                new Promise(r => setTimeout(r, 700)),
            ]);
        } finally {
            btn.disabled = false;
            btn.classList.remove('is-spinning');
        }
    });
}
