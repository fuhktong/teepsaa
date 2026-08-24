<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/admin-auth.php';
require __DIR__ . '/../../config/csrf.php';
require __DIR__ . '/../../config/prospects.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('prospects');

$adminSection = 'marketing';
$adminTab     = 'prospects';

$error = $_SESSION['psp_error'] ?? '';
unset($_SESSION['psp_error']);
$old = $_SESSION['psp_old'] ?? [];
unset($_SESSION['psp_old']);

$v = fn(string $f, string $default = '') => htmlspecialchars((string)($old[$f] ?? $default));

// You are standing in the shop when you add it, so "Pitched" is the honest
// default; "To visit" is for shops added from a list at the desk.
$status = $old['status'] ?? 'pitched';

// The same tree vendors pick from on /submit/. Read-only: the chosen name is
// stored on the prospect row, so a prospect never lands in `businesses`.
$catTree = $pdo->query('SELECT id, parent_id, name FROM categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Add Prospect</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <link rel="stylesheet" href="/admin/prospects/prospects.css">
    <?php require __DIR__ . '/app-head.php'; ?>
</head>
<body>

<?php require __DIR__ . '/../../header/header.php'; ?>

<main>
    <?php require __DIR__ . '/../admin-tabs.php'; ?>

    <div class="psp-head">
        <h1>Add prospect</h1>
        <div class="psp-head-actions">
            <a href="/admin/prospects/" class="psp-btn">Cancel</a>
        </div>
    </div>

    <?php if ($error): ?><div class="psp-alert psp-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" action="/admin/prospects/prospect-action.php" enctype="multipart/form-data" class="psp-form">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="lat" value="<?= $v('lat') ?>">
        <input type="hidden" name="lng" value="<?= $v('lng') ?>">

        <!-- Everything above the fold is what you can fill in one-handed on the
             pavement; the rest can wait until you are back at a desk. -->
        <div class="psp-field">
            <label for="business_name">Business name *</label>
            <input type="text" id="business_name" name="business_name" value="<?= $v('business_name') ?>"
                   required autofocus autocomplete="off" enterkeyhint="next">
        </div>

        <div class="psp-field">
            <label for="status">Outcome</label>
            <select id="status" name="status" class="psp-select">
                <?php foreach (PROSPECT_STATUSES as $key => $label): ?>
                    <option value="<?= $key ?>"<?= $status === $key ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="psp-hint">Anything other than "To visit" also writes the first entry in the visit log.</p>
        </div>

        <div class="psp-field">
            <label>Photo of the shopfront</label>
            <div class="psp-photo">
                <input type="file" id="photo" name="photo" accept="image/*" capture="environment" data-photo-shrink>
                <input type="hidden" name="photo_data">
                <img data-photo-preview hidden alt="">
                <span class="psp-hint" data-photo-info></span>
            </div>
        </div>

        <div class="psp-field">
            <label>Location</label>
            <div class="psp-geo">
                <button type="button" class="psp-btn" data-geo-capture data-geo-auto>Use my location</button>
                <span class="psp-hint" data-geo-info>Locating…</span>
            </div>
        </div>

        <div class="psp-field">
            <label for="phone">Phone</label>
            <input type="tel" id="phone" name="phone" value="<?= $v('phone') ?>" inputmode="tel" autocomplete="off">
        </div>

        <div class="psp-field">
            <label for="owner_name">Owner / contact name</label>
            <input type="text" id="owner_name" name="owner_name" value="<?= $v('owner_name') ?>" autocomplete="off">
        </div>

        <div class="psp-field">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="3" placeholder="What was said, when to come back, who to ask for"><?= $v('notes') ?></textarea>
        </div>

        <div class="psp-field">
            <label for="next_followup_at">Follow up on</label>
            <div class="psp-date-row">
                <input type="date" id="next_followup_at" name="next_followup_at" value="<?= $v('next_followup_at') ?>">
                <button type="button" class="psp-btn psp-btn-sm"
                        onclick="document.getElementById('next_followup_at').value = ''">Clear</button>
            </div>
            <p class="psp-hint">Leave this empty and the prospect stays out of the daily digest.</p>
        </div>

        <details class="psp-more">
            <summary>More details</summary>

            <div class="psp-field">
                <label for="business_name_km">Business name (Khmer)</label>
                <input type="text" id="business_name_km" name="business_name_km" value="<?= $v('business_name_km') ?>" autocomplete="off">
            </div>

            <div class="psp-field">
                <label for="telegram">Telegram</label>
                <input type="text" id="telegram" name="telegram" value="<?= $v('telegram') ?>" placeholder="@handle" autocomplete="off">
            </div>

            <div class="psp-field">
                <label>Category
                    <button type="button" class="psp-link-btn" data-cat-clear>Clear</button>
                </label>
                <div class="psp-cat" data-cat-cascade data-target="category"></div>
                <input type="hidden" id="category" name="category" value="<?= $v('category') ?>">
                <p class="psp-hint">The same list vendors choose from. Stop at any level.</p>
            </div>

            <div class="psp-field">
                <label for="address">Address</label>
                <input type="text" id="address" name="address" value="<?= $v('address') ?>" autocomplete="off">
            </div>
        </details>

        <div class="psp-actions">
            <button type="submit" class="psp-btn psp-btn-primary psp-btn-big">Save prospect</button>
        </div>
    </form>
</main>

<script type="application/json" id="cat-tree-data"><?= json_encode($catTree, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?></script>

<?php require __DIR__ . '/../../footer/footer.php'; ?>
<script src="/js/geo-capture.js"></script>
<script src="/js/photo-shrink.js"></script>
<script src="/js/cat-cascade.js"></script>
</body>
</html>
