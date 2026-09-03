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

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM prospects WHERE id = ?');
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) {
    header('Location: /admin/prospects/');
    exit;
}

$visits = $pdo->prepare('
    SELECT v.*, a.name AS admin_name
      FROM prospect_visits v
      LEFT JOIN admins a ON a.id = v.admin_id
     WHERE v.prospect_id = ?
     ORDER BY v.visited_at DESC, v.id DESC
');
$visits->execute([$id]);
$visits = $visits->fetchAll(PDO::FETCH_ASSOC);

$photos = $pdo->prepare('SELECT * FROM prospect_photos WHERE prospect_id = ? ORDER BY id DESC');
$photos->execute([$id]);
$photos = $photos->fetchAll(PDO::FETCH_ASSOC);

$note  = $_SESSION['psp_success'] ?? '';
$error = $_SESSION['psp_error']   ?? '';
unset($_SESSION['psp_success'], $_SESSION['psp_error']);
$old = $_SESSION['psp_old'] ?? [];
unset($_SESSION['psp_old']);

$v = fn(string $f) => htmlspecialchars((string)($old[$f] ?? $p[$f] ?? ''));

// The same tree vendors pick from on /submit/. Read-only: the chosen names are
// stored on the prospect row, so a prospect never lands in `businesses`.
$catTree = $pdo->query('SELECT id, parent_id, name FROM categories ORDER BY name ASC')->fetchAll(PDO::FETCH_ASSOC);

$tel = $p['phone'] ? preg_replace('/[^0-9+]/', '', $p['phone']) : '';

// Overdue only counts while the prospect is still worth chasing — the digest
// makes the same exclusion, so the two never disagree.
$followupDue = $p['next_followup_at'] !== null
    && $p['next_followup_at'] <= date('Y-m-d')
    && !in_array($p['status'], ['signed_up', 'not_interested', 'closed_down'], true);

// Stored comma separated; shown as separate tags so three categories read as
// three things rather than one long string.
$catList = array_values(array_filter(array_map('trim', explode(',', (string)$p['category'])), fn($c) => $c !== ''));

// Everything else you would ask a shop for, readable without opening the edit
// form. A field with nothing in it is left out entirely rather than shown as
// blank — the panel is for reading at a glance, and the edit form is where the
// gaps are obvious anyway.
$facts = array_filter([
    'Owner'      => $p['owner_name'],
    'Phone'      => $p['phone'],
    'Address'    => $p['address'],
    'Follow up'  => $p['next_followup_at'] ? date('j M Y', strtotime($p['next_followup_at'])) : null,
    'Khmer name' => $p['business_name_km'],
    'Telegram'   => $p['telegram'] ? '@' . ltrim($p['telegram'], '@') : null,
], fn($v) => $v !== null && $v !== '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — <?= htmlspecialchars($p['business_name']) ?></title>
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
<?php require __DIR__ . '/../../admin-subnav/admin-subnav.php'; ?>

<main>
    <?php require __DIR__ . '/../admin-tabs.php'; ?>

    <div class="psp-head">
        <div>
            <h1 class="psp-subhead"><?= htmlspecialchars($p['business_name']) ?></h1>
            <?php if ($p['business_name_km']): ?>
                <p class="psp-row-km"><?= htmlspecialchars($p['business_name_km']) ?></p>
            <?php endif; ?>
        </div>
        <div class="psp-head-actions">
            <span class="psp-badge" style="background:<?= prospect_status_color($p['status']) ?>">
                <?= htmlspecialchars(prospect_status_label($p['status'])) ?>
            </span>
            <a href="/admin/prospects/" class="psp-btn">All prospects</a>
        </div>
        <?php if ($photos): ?>
            <!-- Recognising the shopfront is most of the job, so the photos sit
                 with the name. Managing them lives in Edit details. -->
            <div class="psp-head-photos">
                <?php foreach ($photos as $ph): ?>
                    <a href="/uploads/<?= htmlspecialchars($ph['filename']) ?>" target="_blank" rel="noopener">
                        <img src="/uploads/<?= htmlspecialchars($ph['filename']) ?>" alt="" loading="lazy">
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($note): ?><div class="psp-alert psp-alert-success"><?= htmlspecialchars($note) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="psp-alert psp-alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="psp-quick">
        <?php if ($tel !== ''): ?>
            <a class="psp-btn psp-btn-primary" href="tel:<?= htmlspecialchars($tel) ?>">Call <?= htmlspecialchars($p['phone']) ?></a>
        <?php endif; ?>
        <?php if ($p['telegram']): ?>
            <a class="psp-btn" href="https://t.me/<?= htmlspecialchars(ltrim($p['telegram'], '@')) ?>" target="_blank" rel="noopener">Telegram</a>
        <?php endif; ?>
        <?php if ($p['lat'] !== null && $p['lng'] !== null): ?>
            <!-- No origin in the href on purpose: js/geo-capture.js adds one from
                 the device GPS on click. Without it Google picks a start itself,
                 which on a signed-in browser is usually your saved Home. -->
            <a class="psp-btn" target="_blank" rel="noopener" data-geo-directions
               href="https://www.google.com/maps/dir/?api=1&amp;destination=<?= (float)$p['lat'] ?>,<?= (float)$p['lng'] ?>">Directions</a>
        <?php endif; ?>
    </div>

    <?php if ($catList || $facts || $p['notes']): ?>
    <dl class="psp-facts">
        <?php if ($catList): ?>
            <div class="psp-fact">
                <dt><?= count($catList) === 1 ? 'Category' : 'Categories' ?></dt>
                <dd>
                    <ul class="psp-fact-tags">
                        <?php foreach ($catList as $c): ?><li><?= htmlspecialchars($c) ?></li><?php endforeach; ?>
                    </ul>
                </dd>
            </div>
        <?php endif; ?>
        <?php foreach ($facts as $label => $val): $due = ($label === 'Follow up' && $followupDue); ?>
            <div class="psp-fact">
                <dt><?= $label ?></dt>
                <dd<?= $due ? ' class="psp-due"' : '' ?>><?= htmlspecialchars((string)$val) ?><?= $due ? ' — due' : '' ?></dd>
            </div>
        <?php endforeach; ?>
        <?php if ($p['notes']): ?>
            <div class="psp-fact psp-fact-wide">
                <dt>Notes</dt>
                <dd><?= nl2br(htmlspecialchars($p['notes'])) ?></dd>
            </div>
        <?php endif; ?>
    </dl>
    <?php endif; ?>

    <!-- ── Log a visit ─────────────────────────────────────────────── -->
    <section class="psp-card">
        <h2>Log a visit</h2>
        <form method="post" action="/admin/prospects/prospect-action.php" enctype="multipart/form-data" class="psp-form">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="visit">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="lat" value="">
            <input type="hidden" name="lng" value="">

            <div class="psp-field">
                <label for="outcome">Outcome</label>
                <select id="outcome" name="outcome" class="psp-select">
                    <?php foreach (PROSPECT_STATUSES as $key => $label): ?>
                        <option value="<?= $key ?>"<?= $p['status'] === $key ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="psp-hint">This becomes the prospect's current status.</p>
            </div>

            <div class="psp-field">
                <label for="visit-note">What happened</label>
                <textarea id="visit-note" name="note" rows="3" placeholder="Owner not in, come back mornings"></textarea>
            </div>

            <div class="psp-field">
                <label>Photo</label>
                <div class="psp-photo">
                    <input type="file" name="photo" accept="image/*" capture="environment" data-photo-shrink>
                    <input type="hidden" name="photo_data">
                    <img data-photo-preview hidden alt="">
                    <span class="psp-hint" data-photo-info></span>
                </div>
            </div>

            <div class="psp-geo">
                <button type="button" class="psp-btn" data-geo-capture>Attach my location</button>
                <span class="psp-hint" data-geo-info></span>
            </div>

            <div class="psp-actions">
                <button type="submit" class="psp-btn psp-btn-primary psp-btn-big">Log visit</button>
            </div>
        </form>
    </section>

    <!-- ── Visit log ───────────────────────────────────────────────── -->
    <section class="psp-card">
        <h2>Visit log<?= $visits ? ' (' . count($visits) . ')' : '' ?></h2>
        <?php if (!$visits): ?>
            <p class="psp-hint">No visits recorded yet.</p>
        <?php else: ?>
            <ul class="psp-visits">
                <?php foreach ($visits as $vs): ?>
                    <li>
                        <span class="psp-visit-when"><?= date('j M Y, g:ia', strtotime($vs['visited_at'])) ?></span>
                        <span class="psp-badge psp-badge-sm" style="background:<?= prospect_status_color($vs['outcome']) ?>">
                            <?= htmlspecialchars(prospect_status_label($vs['outcome'])) ?>
                        </span>
                        <?php if ($vs['admin_name']): ?>
                            <span class="psp-meta-bit">by <?= htmlspecialchars($vs['admin_name']) ?></span>
                        <?php endif; ?>
                        <?php if ($vs['note']): ?>
                            <p class="psp-visit-note"><?= nl2br(htmlspecialchars($vs['note'])) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <!-- ── Edit ────────────────────────────────────────────────────── -->
    <section class="psp-card">
        <details<?= $old ? ' open' : '' ?>>
            <summary><h2>Edit details</h2></summary>
            <form method="post" action="/admin/prospects/prospect-action.php" class="psp-form">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

                <div class="psp-field">
                    <label for="business_name">Business name *</label>
                    <input type="text" id="business_name" name="business_name" value="<?= $v('business_name') ?>" required>
                </div>

                <div class="psp-field">
                    <label for="business_name_km">Business name (Khmer)</label>
                    <input type="text" id="business_name_km" name="business_name_km" value="<?= $v('business_name_km') ?>">
                </div>

                <div class="psp-field">
                    <label for="edit-status">Status</label>
                    <select id="edit-status" name="status" class="psp-select">
                        <?php foreach (PROSPECT_STATUSES as $key => $label): ?>
                            <option value="<?= $key ?>"<?= ($old['status'] ?? $p['status']) === $key ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="psp-hint">Changing it here does not write a visit — use "Log a visit" for that.</p>
                </div>

                <div class="psp-field">
                    <label for="edit-owner">Owner / contact name</label>
                    <input type="text" id="edit-owner" name="owner_name" value="<?= $v('owner_name') ?>">
                </div>

                <div class="psp-field">
                    <label for="edit-phone">Phone</label>
                    <input type="tel" id="edit-phone" name="phone" value="<?= $v('phone') ?>" inputmode="tel">
                </div>

                <div class="psp-field">
                    <label for="edit-telegram">Telegram</label>
                    <input type="text" id="edit-telegram" name="telegram" value="<?= $v('telegram') ?>" placeholder="@handle">
                </div>

                <div class="psp-field">
                    <label>Categories
                        <button type="button" class="psp-link-btn" data-cat-clear>Clear all</button>
                    </label>
                    <ul class="psp-cat-chosen" data-cat-chosen hidden></ul>
                    <div class="psp-cat" data-cat-cascade data-target="edit-category"></div>
                    <button type="button" class="psp-btn psp-btn-sm" data-cat-add>Add category</button>
                    <input type="hidden" id="edit-category" name="category" value="<?= $v('category') ?>">
                    <p class="psp-hint">The same list vendors choose from. Stop at any level, and add as many as the shop sells.</p>
                </div>

                <div class="psp-field">
                    <label for="edit-address">Address</label>
                    <input type="text" id="edit-address" name="address" value="<?= $v('address') ?>">
                </div>

                <div class="psp-field">
                    <label for="edit-followup">Follow up on</label>
                    <div class="psp-date-row">
                        <input type="date" id="edit-followup" name="next_followup_at" value="<?= $v('next_followup_at') ?>">
                        <button type="button" class="psp-btn psp-btn-sm"
                                onclick="document.getElementById('edit-followup').value = ''">Clear</button>
                    </div>
                    <p class="psp-hint">Clear it and save to drop this prospect out of the daily digest.</p>
                </div>

                <div class="psp-field">
                    <label for="edit-notes">Notes</label>
                    <textarea id="edit-notes" name="notes" rows="4"><?= $v('notes') ?></textarea>
                </div>

                <div class="psp-field psp-field-pair">
                    <div>
                        <label for="edit-lat">Latitude</label>
                        <input type="text" id="edit-lat" name="lat" value="<?= $v('lat') ?>" inputmode="decimal">
                    </div>
                    <div>
                        <label for="edit-lng">Longitude</label>
                        <input type="text" id="edit-lng" name="lng" value="<?= $v('lng') ?>" inputmode="decimal">
                    </div>
                </div>
                <div class="psp-geo">
                    <button type="button" class="psp-btn" data-geo-capture>Set pin to where I am now</button>
                    <span class="psp-hint" data-geo-info></span>
                </div>

                <div class="psp-actions">
                    <button type="submit" class="psp-btn psp-btn-primary">Save changes</button>
                </div>
            </form>

            <!-- Sibling forms, never nested inside the one above — a form inside
                 a form is invalid and the inner one silently stops submitting.
                 Viewing the photos happens up in the head; this is the managing. -->
            <hr class="psp-subdivider">
            <h3 class="psp-subhead-h3">Photos<?= $photos ? ' (' . count($photos) . ')' : '' ?></h3>

            <?php if (!$photos): ?>
                <p class="psp-hint">No photos yet.</p>
            <?php else: ?>
                <div class="psp-photos">
                    <?php foreach ($photos as $ph): ?>
                        <div class="psp-photo-cell">
                            <a href="/uploads/<?= htmlspecialchars($ph['filename']) ?>" target="_blank" rel="noopener">
                                <img src="/uploads/<?= htmlspecialchars($ph['filename']) ?>" alt="" loading="lazy">
                            </a>
                            <span class="psp-photo-date"><?= date('j M Y', strtotime($ph['created_at'])) ?></span>
                            <form method="post" action="/admin/prospects/prospect-action.php"
                                  onsubmit="return confirm('Delete this photo?');">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="delete-photo">
                                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="photo_id" value="<?= (int)$ph['id'] ?>">
                                <button type="submit" class="psp-photo-del" title="Delete photo">&times;</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/admin/prospects/prospect-action.php" enctype="multipart/form-data" class="psp-photo-add">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="add-photo">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <input type="file" name="photo" accept="image/*" capture="environment" data-photo-shrink>
                <input type="hidden" name="photo_data">
                <img data-photo-preview hidden alt="">
                <span class="psp-hint" data-photo-info></span>
                <button type="submit" class="psp-btn">Add photo</button>
            </form>
        </details>
    </section>

    <form method="post" action="/admin/prospects/prospect-action.php" class="psp-danger"
          onsubmit="return confirm('Delete this prospect, its visits and its photos? This cannot be undone.');">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <button type="submit" class="psp-btn psp-btn-danger">Delete prospect</button>
    </form>
</main>

<script type="application/json" id="cat-tree-data"><?= json_encode($catTree, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?></script>

<?php require __DIR__ . '/../../footer/footer.php'; ?>
<script src="/js/geo-capture.js"></script>
<script src="/js/photo-shrink.js"></script>
<script src="/js/cat-cascade.js"></script>
</body>
</html>
