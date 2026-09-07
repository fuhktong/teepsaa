<?php
// Numbered page links, server-rendered.
//
// The card grids load more as you scroll, which is right for a person on a
// phone and useless to a crawler: it doesn't scroll, so without this it sees
// the first twenty products on a category and stops. Links between your own
// pages are also how standing spreads around a site, and none of it was
// reaching anything past the first batch.
//
// Expects, set by the including page:
//   $pgCurrent  int    the page being shown, 1-based
//   $pgTotal    int    how many pages there are
//   $pgUrl      callable(int $page): string  → the address of that page
//   $t          array  translations
//
// Renders nothing at all when there is only one page.

if (!empty($pgTotal) && $pgTotal > 1):

    // Always show the first and last page, and a small window either side of
    // the current one. Everything else collapses to an ellipsis, so page 40
    // of 200 is still one short row rather than two hundred links.
    $pgWindow = [];
    foreach ([1, $pgTotal] as $edge) $pgWindow[$edge] = true;
    for ($i = $pgCurrent - 2; $i <= $pgCurrent + 2; $i++) {
        if ($i >= 1 && $i <= $pgTotal) $pgWindow[$i] = true;
    }
    $pgNumbers = array_keys($pgWindow);
    sort($pgNumbers);
?>
<nav class="pagination" aria-label="<?= htmlspecialchars($t['pg_label']) ?>">
    <?php if ($pgCurrent > 1): ?>
        <a class="pg-link pg-step" rel="prev" href="<?= $pgUrl($pgCurrent - 1) ?>">&lsaquo; <?= htmlspecialchars($t['pg_prev']) ?></a>
    <?php endif; ?>

    <?php $pgPrevNum = 0; foreach ($pgNumbers as $pgNum): ?>
        <?php if ($pgPrevNum && $pgNum > $pgPrevNum + 1): ?>
            <span class="pg-gap" aria-hidden="true">…</span>
        <?php endif; ?>
        <?php if ($pgNum === $pgCurrent): ?>
            <span class="pg-link pg-current" aria-current="page"><?= $pgNum ?></span>
        <?php else: ?>
            <a class="pg-link" href="<?= $pgUrl($pgNum) ?>" aria-label="<?= htmlspecialchars(sprintf($t['pg_page'], $pgNum)) ?>"><?= $pgNum ?></a>
        <?php endif; ?>
        <?php $pgPrevNum = $pgNum; ?>
    <?php endforeach; ?>

    <?php if ($pgCurrent < $pgTotal): ?>
        <a class="pg-link pg-step" rel="next" href="<?= $pgUrl($pgCurrent + 1) ?>"><?= htmlspecialchars($t['pg_next']) ?> &rsaquo;</a>
    <?php endif; ?>
</nav>
<?php endif; ?>
