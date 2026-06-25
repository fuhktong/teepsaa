<?php
$lang     = $_SESSION['lang']     ?? 'km';
$currency = $_SESSION['currency'] ?? 'USD';
?>
<footer>
    <div class="footer-top">
        <div class="footer-columns">
            <div class="footer-col">
                <span class="footer-col-head">teepsaa</span>
                <a href="/about/">About</a>
                <a href="/careers/">Careers</a>
            </div>
            <div class="footer-col">
                <span class="footer-col-head">Your Account</span>
                <a href="/account/">Account</a>
                <a href="/orders/">Your Orders</a>
                <a href="/forgot-password-buyer/">Forgot buyer password</a>
                <a href="/forgot-password-vendor/">Forgot vendor password</a>
            </div>
            <div class="footer-col">
                <span class="footer-col-head">Help</span>
                <a href="/help/">Help Center</a>
                <a href="/shipping/">Shipping &amp; Policies</a>
                <a href="/returns/">Returns</a>
                <a href="/privacy/">Privacy Policy</a>
                <a href="/terms/">Terms of Service</a>
            </div>
            <div class="footer-col">
                <span class="footer-col-head">Sell</span>
                <a href="/register-vendor/">Sell on teepsaa</a>
            </div>
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

            <p class="footer-copy">&copy; <?= date('Y') ?> teepsaa. All rights reserved.</p>
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
