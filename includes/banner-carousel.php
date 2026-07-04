<?php
// Shared banner carousel — requires $pdo to be available
$_bannerLang = $_SESSION['lang'] ?? 'km';
try {
    $_banners = $pdo->query(
        'SELECT id, title, title_km, subtitle, subtitle_km, link_url, image_filename
         FROM banners WHERE active = 1 ORDER BY sort_order ASC, id ASC'
    )->fetchAll();
} catch (PDOException $e) {
    $_banners = [];
}
if (!empty($_banners)):
    $count = count($_banners);
?>
<div class="banner-carousel" id="bannerCarousel">
    <div class="banner-slides">
        <?php foreach ($_banners as $i => $b): ?>
        <?php
            $tag  = $b['link_url'] ? 'a' : 'div';
            $href = $b['link_url'] ? ' href="' . htmlspecialchars($b['link_url']) . '"' : '';
            // Show Khmer copy when in Khmer and it's provided, else fall back to English.
            $bTitle    = ($_bannerLang === 'km' && !empty($b['title_km']))    ? $b['title_km']    : $b['title'];
            $bSubtitle = ($_bannerLang === 'km' && !empty($b['subtitle_km'])) ? $b['subtitle_km'] : $b['subtitle'];
        ?>
        <<?= $tag . $href ?> class="banner-slide<?= $i === 0 ? ' active' : '' ?>"
             style="background-image:url('/uploads/<?= htmlspecialchars($b['image_filename']) ?>')">
            <?php if ($bTitle || $bSubtitle): ?>
            <div class="banner-text">
                <?php if ($bTitle): ?><p class="banner-title"><?= htmlspecialchars($bTitle) ?></p><?php endif; ?>
                <?php if ($bSubtitle): ?><p class="banner-subtitle"><?= htmlspecialchars($bSubtitle) ?></p><?php endif; ?>
            </div>
            <?php endif; ?>
        </<?= $tag ?>>
        <?php endforeach; ?>
    </div>
    <?php if ($count > 1): ?>
    <button class="banner-btn banner-btn--prev" aria-label="Previous">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
    <button class="banner-btn banner-btn--next" aria-label="Next">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
    </button>
    <div class="banner-dots">
        <?php for ($i = 0; $i < $count; $i++): ?>
        <button class="banner-dot<?= $i === 0 ? ' active' : '' ?>" data-index="<?= $i ?>" aria-label="Slide <?= $i + 1 ?>"></button>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
<script>
(function(){
    var el = document.getElementById('bannerCarousel');
    if (!el) return;
    var slides = el.querySelectorAll('.banner-slide');
    var dots   = el.querySelectorAll('.banner-dot');
    var cur = 0, timer;

    function go(n) {
        slides[cur].classList.remove('active');
        if (dots[cur]) dots[cur].classList.remove('active');
        cur = (n + slides.length) % slides.length;
        slides[cur].classList.add('active');
        if (dots[cur]) dots[cur].classList.add('active');
    }

    function start() { timer = setInterval(function(){ go(cur + 1); }, 5000); }
    function reset() { clearInterval(timer); start(); }

    var prev = el.querySelector('.banner-btn--prev');
    var next = el.querySelector('.banner-btn--next');
    if (prev) prev.addEventListener('click', function(){ go(cur - 1); reset(); });
    if (next) next.addEventListener('click', function(){ go(cur + 1); reset(); });
    dots.forEach(function(d, i){ d.addEventListener('click', function(){ go(i); reset(); }); });

    el.addEventListener('mouseenter', function(){ clearInterval(timer); });
    el.addEventListener('mouseleave', start);

    if (slides.length > 1) start();
})();
</script>
<?php endif; ?>
