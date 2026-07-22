(function () {
    var bellBtn     = document.getElementById('bell-btn');
    var bellDrop    = document.getElementById('bell-dropdown');
    var bellBadge   = document.getElementById('bell-badge');
    var bellItems   = document.getElementById('bell-items');
    var markReadBtn = document.getElementById('bell-mark-read');

    if (!bellBtn) return;

    // ── New-notification chime ───────────────────────────────────────────
    // Browsers block audio until the user has interacted with the page, so a
    // shared AudioContext is created/resumed on the first gesture (capture
    // phase, so the bell's stopPropagation can't swallow it). The chime is
    // synthesized — no asset to deploy — and only ever plays in the same poll
    // that raises the unread badge, so there's never a sound without the red dot.
    var audioCtx  = null;
    var lastMaxId = null; // highest notification id seen; null until first poll

    function unlockAudio() {
        if (!audioCtx) {
            var AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            try { audioCtx = new AC(); } catch (e) { return; }
        }
        if (audioCtx.state === 'suspended') audioCtx.resume();
    }

    ['click', 'keydown', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, unlockAudio, true);
    });

    function playChime() {
        if (!audioCtx || audioCtx.state !== 'running') return;
        var now = audioCtx.currentTime;
        // Three-note rising chime (E5, G5, C6), each with a quick decay.
        [[659, 0], [784, 0.1], [1047, 0.2]].forEach(function (pair) {
            var t    = now + pair[1];
            var osc  = audioCtx.createOscillator();
            var gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.value = pair[0];
            gain.gain.setValueAtTime(0.0001, t);
            gain.gain.exponentialRampToValueAtTime(0.16, t + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.35);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(t);
            osc.stop(t + 0.4);
        });
    }

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

                // Chime only when a genuinely new notification arrived since the
                // last poll — tracked by the highest id, which sidesteps the
                // read-one/receive-one same-interval count wash. The count > 0
                // guard keeps sound and red badge locked together. First poll
                // just sets the baseline so we don't ding for pre-existing unread.
                var maxId = (data.items && data.items.length) ? data.items[0].id : 0;
                if (lastMaxId === null) {
                    lastMaxId = maxId;
                } else if (maxId > lastMaxId) {
                    if (data.count > 0) playChime();
                    lastMaxId = maxId;
                }

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
