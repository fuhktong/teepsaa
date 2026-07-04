(function () {
    var bellBtn     = document.getElementById('bell-btn');
    var bellDrop    = document.getElementById('bell-dropdown');
    var bellBadge   = document.getElementById('bell-badge');
    var bellItems   = document.getElementById('bell-items');
    var markReadBtn = document.getElementById('bell-mark-read');

    if (!bellBtn) return;

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function updateBadge(count) {
        if (count > 0) {
            bellBadge.textContent = count > 9 ? '9+' : String(count);
            bellBadge.style.display = '';
        } else {
            bellBadge.style.display = 'none';
        }
    }

    function renderItems(items) {
        if (!items.length) {
            bellItems.innerHTML = '<p class="bell-empty">' + ((window.T && window.T.no_notifications) || 'No notifications yet') + '</p>';
            return;
        }
        bellItems.innerHTML = items.map(function (item) {
            var href = item.link || '#';
            var cls  = 'bell-item' + (item.read ? '' : ' bell-item--unread');
            return '<a href="' + escHtml(href) + '" class="' + cls + '" data-id="' + item.id + '">'
                + '<span class="bell-item-msg">' + escHtml(item.message) + '</span>'
                + '<span class="bell-item-time">' + escHtml(item.time) + '</span>'
                + '</a>';
        }).join('');

        bellItems.querySelectorAll('.bell-item').forEach(function (el) {
            el.addEventListener('click', function () {
                var id = this.dataset.id;
                fetch('/api/notifications/mark-read.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: new URLSearchParams({ id: id })
                });
                this.classList.remove('bell-item--unread');
            });
        });
    }

    function fetchNotifications(renderList) {
        fetch('/api/notifications/', { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) return;
                updateBadge(data.count);
                if (renderList || bellDrop.classList.contains('open')) {
                    renderItems(data.items);
                }
            })
            .catch(function () {});
    }

    bellBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var opening = !bellDrop.classList.contains('open');
        bellDrop.classList.toggle('open', opening);

        if (opening) {
            bellItems.innerHTML = '<p class="bell-empty">' + ((window.T && window.T.loading) || 'Loading…') + '</p>';
            fetchNotifications(true);
            var avatarDrop = document.getElementById('user-dropdown');
            var langDrop   = document.getElementById('lang-dropdown');
            if (avatarDrop) avatarDrop.classList.remove('open');
            if (langDrop)   langDrop.classList.remove('open');
        }
    });

    document.addEventListener('click', function () {
        bellDrop.classList.remove('open');
    });

    bellDrop.addEventListener('click', function (e) { e.stopPropagation(); });

    if (markReadBtn) {
        markReadBtn.addEventListener('click', function () {
            fetch('/api/notifications/mark-read.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: new URLSearchParams({ id: '0' })
            }).then(function () {
                updateBadge(0);
                bellItems.querySelectorAll('.bell-item--unread').forEach(function (el) {
                    el.classList.remove('bell-item--unread');
                });
            });
        });
    }

    // Initial fetch (badge only, no list render)
    fetchNotifications(false);
    // Poll every 15 seconds
    setInterval(function () { fetchNotifications(false); }, 15000);
})();
