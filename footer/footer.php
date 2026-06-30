<?php
$lang     = $_SESSION['lang']     ?? 'km';
$currency = $_SESSION['currency'] ?? 'USD';

// Translations: reuse the page's $t if the header already loaded it.
if (!isset($t)) {
    $t = require __DIR__ . '/../lang/' . (in_array($lang, ['en', 'km']) ? $lang : 'en') . '.php';
}

// "Your Account" column mirrors each role's avatar dropdown.
if (empty($_SESSION['user_id'])) {
    $accountLinks = [
        ['/login/',    $t['footer_sign_in']],
        ['/register/', $t['footer_create_account']],
    ];
} elseif (($_SESSION['role'] ?? '') === 'vendor') {
    $accountLinks = [
        ['/orders-vendor/',             $t['nav_orders']],
        ['/products/',                  $t['nav_products']],
        ['/messages-vendor/',           $t['nav_messages']],
        ['/dashboard-vendor/settings/', $t['nav_settings']],
    ];
} elseif (($_SESSION['role'] ?? '') === 'admin') {
    $accountLinks = [
        ['/admin/orders.php',   $t['nav_orders']],
        ['/admin/messages/',    $t['nav_messages']],
        ['/admin/settings.php', $t['nav_settings']],
    ];
} else {
    $accountLinks = [
        ['/dashboard-buyer/',          $t['nav_orders']],
        ['/wishlist/',                 $t['nav_wishlist']],
        ['/messages-buyer/',           $t['nav_messages']],
        ['/dashboard-buyer/settings/', $t['nav_settings']],
    ];
}
?>
<footer>
    <div class="footer-top">
        <div class="footer-head">
            <div class="footer-brandrow">
                <a href="/" class="footer-logo">
                    <img src="/images/<?= $lang === 'km' ? 'teepsaa_logo_khm.png' : 'teepsaa_logo_eng_myriad.png' ?>" alt="teepsaa">
                </a>
                <span class="footer-tagline"<?= $lang === 'km' ? ' lang="km"' : '' ?>><?= $lang === 'km' ? 'ទិញឱ្យងាយស្រួល' : 'Shopping made easy' ?></span>
            </div>

            <div class="footer-social">
                <a href="#" class="footer-social-link" aria-label="teepsaa on Instagram">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="1.8"/>
                        <circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/>
                        <circle cx="17.2" cy="6.8" r="1.2" fill="currentColor"/>
                    </svg>
                </a>
                <a href="#" class="footer-social-link" aria-label="teepsaa on Facebook">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="currentColor" d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.88 3.77-3.88 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.44 2.89h-2.34v6.99A10 10 0 0 0 22 12z"/>
                    </svg>
                </a>
                <a href="#" class="footer-social-link" aria-label="teepsaa on Telegram">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="currentColor" d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3l-4.1-1.3c-.88-.25-.9-.86.2-1.3l15.97-6.16c.73-.27 1.37.18 1.09 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.98 1.93c-.22.22-.4.42-.84.42z"/>
                    </svg>
                </a>
            </div>
        </div>
        <div class="footer-columns">

            <nav class="footer-col" aria-label="<?= htmlspecialchars($t['footer_your_account']) ?>">
                <h2 class="footer-col-head"><?= $t['footer_your_account'] ?></h2>
                <ul class="footer-col-list">
                    <?php foreach ($accountLinks as [$href, $label]): ?>
                    <li><a href="<?= $href ?>"><?= $label ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            <nav class="footer-col" aria-label="<?= htmlspecialchars($t['footer_help']) ?>">
                <h2 class="footer-col-head"><?= $t['footer_help'] ?></h2>
                <ul class="footer-col-list">
                    <li><a href="/help/"><?= $t['footer_help_center'] ?></a></li>
                    <li><a href="/shipping/"><?= $t['footer_shipping'] ?></a></li>
                    <li><a href="/returns/"><?= $t['footer_returns'] ?></a></li>
                    <li><a href="/privacy/"><?= $t['footer_privacy'] ?></a></li>
                    <li><a href="/terms/"><?= $t['footer_terms'] ?></a></li>
                </ul>
            </nav>
            <nav class="footer-col" aria-label="<?= htmlspecialchars($t['footer_brand']) ?>">
                <h2 class="footer-col-head"><?= $t['footer_brand'] ?></h2>
                <ul class="footer-col-list">
                    <li><a href="/about/"><?= $t['footer_about'] ?></a></li>
                    <li><a href="/careers/"><?= $t['footer_careers'] ?></a></li>
                    <li><a href="/register-vendor/"><?= $t['footer_sell_on'] ?></a></li>
                </ul>
            </nav>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="footer-bottom-inner">
            <div class="footer-selects">
                <label class="footer-select-wrap">
                    <span class="footer-flag">
                        <img src="/flags/<?= $lang === 'km' ? 'kh' : 'us' ?>.svg" width="26" height="16" alt="" style="display:block">
                    </span>
                    <select id="footer-lang-sel" class="footer-select">
                        <option value="en" <?= $lang === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="km" <?= $lang === 'km' ? 'selected' : '' ?>>ខ្មែរ</option>
                    </select>
                </label>

                <label class="footer-select-wrap">
                    <select id="footer-currency-sel" class="footer-select">
                        <option value="USD" <?= $currency === 'USD' ? 'selected' : '' ?>>$ USD – US Dollar</option>
                        <option value="KHR" <?= $currency === 'KHR' ? 'selected' : '' ?>>៛ KHR – Cambodian Riel</option>
                    </select>
                </label>
            </div>

            <p class="footer-copy">&copy; <?= date('Y') ?> <?= $t['footer_copyright'] ?></p>
        </div>
    </div>
</footer>

<script>
(function () {
    var SCROLL_KEY = 'teepsaa_scroll';

    var saved = sessionStorage.getItem(SCROLL_KEY);
    if (saved !== null) {
        sessionStorage.removeItem(SCROLL_KEY);
        window.scrollTo(0, parseInt(saved, 10));
    }

    function postAndReload(url, data) {
        sessionStorage.setItem(SCROLL_KEY, window.scrollY);
        var fd = new FormData();
        Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
        fetch(url, { method: 'POST', body: fd }).then(function () { location.reload(); });
    }

    var langSel = document.getElementById('footer-lang-sel');
    var flagImg = document.querySelector('.footer-flag img');

    langSel.addEventListener('change', function () {
        if (flagImg) flagImg.src = '/flags/' + (this.value === 'km' ? 'kh' : 'us') + '.svg';
        postAndReload('/lang/set.php', { lang: this.value });
    });

    document.getElementById('footer-currency-sel').addEventListener('change', function () {
        postAndReload('/currency/set.php', { currency: this.value });
    });
})();
</script>
