<?php
// The Home › Bags › Silk Krama Scarf trail.
//
// Set $crumbs before requiring this — an ordered list of [label, path]
// pairs, the last being the current page and carrying an empty path:
//
//     $crumbs = [
//         [$t['crumb_home'], '/'],
//         ['Bags',           '/search/?category=5'],
//         [$productName,     ''],
//     ];
//     require __DIR__ . '/../breadcrumb/breadcrumb.php';
//
// The matching hidden block for Google is schema_breadcrumb($crumbs) in
// config/schema.php — pass it the same array, from the page's <head>. Google
// only shows the trail in a result if the visible one and the hidden one
// agree, which is why both read from one variable.
//
// Requires breadcrumb/breadcrumb.css in the page's <head>.

if (!empty($crumbs) && count($crumbs) > 1):
    $crumbLast = count($crumbs) - 1;
?>
<nav class="breadcrumb" aria-label="<?= htmlspecialchars($t['crumb_label'] ?? 'Breadcrumb') ?>">
    <ol class="breadcrumb-list">
        <?php foreach ($crumbs as $crumbIndex => [$crumbLabel, $crumbPath]): ?>
            <li class="breadcrumb-item">
                <?php if ($crumbPath !== '' && $crumbIndex !== $crumbLast): ?>
                    <a href="<?= lang_href($crumbPath) ?>"><?= htmlspecialchars((string)$crumbLabel) ?></a>
                <?php else: ?>
                    <span aria-current="page"><?= htmlspecialchars((string)$crumbLabel) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>
