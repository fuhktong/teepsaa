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
require __DIR__ . '/../../config/announcements.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('messages');

$amsgTab = 'announcements';

$id = (int)($_GET['id'] ?? 0);

$blank = [
    'id' => 0, 'audience' => 'buyers', 'kind' => 'promotional',
    'subject_km' => '', 'subject_en' => '', 'heading_km' => '', 'heading_en' => '',
    'body_km' => '', 'body_en' => '', 'cta_km' => '', 'cta_en' => '', 'cta_url' => '',
    'status' => 'draft', 'total_recipients' => 0, 'sent_count' => 0, 'failed_count' => 0,
    'created_at' => null, 'queued_at' => null, 'finished_at' => null,
];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM announcements WHERE id = ?');
    $stmt->execute([$id]);
    $a = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$a) {
        header('Location: /admin/messages/announcements.php');
        exit;
    }
} else {
    $a = $blank;
}

$error = $_SESSION['ann_error'] ?? '';
$note  = $_SESSION['ann_success'] ?? '';
unset($_SESSION['ann_error'], $_SESSION['ann_success']);
// A failed save stashes the submitted values so the form isn't wiped.
$old = $_SESSION['ann_old'] ?? [];
unset($_SESSION['ann_old']);

$v   = fn(string $f) => htmlspecialchars((string)($old[$f] ?? $a[$f] ?? ''));
$sel = fn(string $f, string $opt) => ((string)($old[$f] ?? $a[$f]) === $opt ? ' selected' : '');

// Only a draft can be edited — once queued the wording is frozen, since part of
// the list may already have it in their inbox.
$editable = $a['status'] === 'draft';

$preview = array_merge($a, array_intersect_key($old, $blank));
[, $previewHtml] = announcement_render(
    $preview,
    ($preview['kind'] ?? 'promotional') !== 'service' ? unsubscribe_url('buyer', 'preview') : null
);

$recipientCount = announcement_audience_count($pdo, $preview['audience'], $preview['kind']);

// Delivery failures, worth surfacing on a finished send.
$problems = [];
if ($id && $a['status'] !== 'draft') {
    $ps = $pdo->prepare(
        "SELECT role, email, status, error FROM announcement_recipients
          WHERE announcement_id = ? AND status IN ('failed','skipped')
          ORDER BY status, id LIMIT 50"
    );
    $ps->execute([$id]);
    $problems = $ps->fetchAll(PDO::FETCH_ASSOC);
}

$pending = 0;
if ($id && $a['status'] === 'sending') {
    $q = $pdo->prepare("SELECT COUNT(*) FROM announcement_recipients WHERE announcement_id = ? AND status = 'pending'");
    $q->execute([$id]);
    $pending = (int)$q->fetchColumn();
}

$adminEmail = '';
$ae = $pdo->prepare('SELECT email FROM admins WHERE id = ?');
$ae->execute([(int)$_SESSION['admin_id']]);
$adminEmail = (string)$ae->fetchColumn();

$audienceLabel = ['buyers' => 'Buyers', 'vendors' => 'Vendors', 'both' => 'Buyers + Vendors'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($a['status'] === 'sending'): ?><meta http-equiv="refresh" content="20"><?php endif; ?>
    <title><?= $id ? 'Announcement' : 'New announcement' ?> — Admin — teepsaa</title>
    <link rel="preload" href="/fonts/source-sans-3-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/noto-sans-khmer-khmer.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <link rel="stylesheet" href="/admin/messages/messages-admin.css">
</head>
<body>
<?php require __DIR__ . '/../../header/header.php'; ?>
<main>
    <?php require __DIR__ . '/tabs.php'; ?>

    <h1 class="amsg-subhead"><?= $id ? 'Announcement' : 'New announcement' ?></h1>

    <?php if ($error): ?>
    <div class="admin-alert admin-alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($note): ?>
    <div class="admin-alert admin-alert--success"><?= htmlspecialchars($note) ?></div>
    <?php endif; ?>

    <?php if (!$editable): ?>
    <div class="admin-alert admin-alert--info">
        This announcement is <strong><?= htmlspecialchars($a['status']) ?></strong> and can no longer be edited —
        <?= (int)$a['sent_count'] ?> of <?= (int)$a['total_recipients'] ?> sent<?= (int)$a['failed_count'] ? ', ' . (int)$a['failed_count'] . ' failed' : '' ?>.
        <?php if ($a['status'] === 'sending'): ?>
            <?= $pending ?> still queued. The hourly cron worker sends the rest; this page refreshes every 20&nbsp;seconds.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="ann-grid">
        <div class="ann-col">
            <h2>Content</h2>
            <form method="POST" action="/admin/messages/announcement-action.php" id="ann-form">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">

                <div class="ann-field">
                    <label>Send to</label>
                    <div class="ann-pair">
                        <select name="audience" <?= $editable ? '' : 'disabled' ?>>
                            <option value="buyers"<?= $sel('audience', 'buyers') ?>>All buyers</option>
                            <option value="vendors"<?= $sel('audience', 'vendors') ?>>All vendors</option>
                            <option value="both"<?= $sel('audience', 'both') ?>>Buyers + vendors</option>
                        </select>
                        <select name="kind" <?= $editable ? '' : 'disabled' ?>>
                            <option value="promotional"<?= $sel('kind', 'promotional') ?>>Promotional (respects unsubscribe)</option>
                            <option value="service"<?= $sel('kind', 'service') ?>>Service notice (goes to everyone)</option>
                        </select>
                    </div>
                    <?php if ($editable): ?>
                    <p class="ann-hint"><strong><?= $recipientCount ?></strong> eligible recipient<?= $recipientCount === 1 ? '' : 's' ?> right now — verified, active accounts<?= $preview['kind'] !== 'service' ? ' that have not unsubscribed' : '' ?>. Change the two dropdowns and save the draft to recount.</p>
                    <?php endif; ?>
                </div>

                <div class="ann-field">
                    <label>Subject</label>
                    <div class="ann-pair">
                        <input type="text" name="subject_km" value="<?= $v('subject_km') ?>" placeholder="ខ្មែរ" <?= $editable ? 'required' : 'disabled' ?>>
                        <input type="text" name="subject_en" value="<?= $v('subject_en') ?>" placeholder="English" <?= $editable ? 'required' : 'disabled' ?>>
                    </div>
                </div>

                <div class="ann-field">
                    <label>Heading</label>
                    <div class="ann-pair">
                        <input type="text" name="heading_km" value="<?= $v('heading_km') ?>" placeholder="ខ្មែរ" <?= $editable ? 'required' : 'disabled' ?>>
                        <input type="text" name="heading_en" value="<?= $v('heading_en') ?>" placeholder="English" <?= $editable ? 'required' : 'disabled' ?>>
                    </div>
                </div>

                <div class="ann-field">
                    <label>Body <span class="ann-langtag">— Khmer (shown on top)</span></label>
                    <textarea name="body_km" <?= $editable ? 'required' : 'disabled' ?>><?= $v('body_km') ?></textarea>
                </div>
                <div class="ann-field">
                    <label>Body <span class="ann-langtag">— English (shown below)</span></label>
                    <textarea name="body_en" <?= $editable ? 'required' : 'disabled' ?>><?= $v('body_en') ?></textarea>
                </div>
                <p class="ann-hint">Line breaks are kept. Simple HTML such as <code>&lt;strong&gt;</code> and <code>&lt;a href&gt;</code> works too. There are no <code>{tokens}</code> here — the same text goes to everyone.</p>

                <div class="ann-field">
                    <label>Button label <span class="ann-langtag">— optional</span></label>
                    <div class="ann-pair">
                        <input type="text" name="cta_km" value="<?= $v('cta_km') ?>" placeholder="ខ្មែរ" <?= $editable ? '' : 'disabled' ?>>
                        <input type="text" name="cta_en" value="<?= $v('cta_en') ?>" placeholder="English" <?= $editable ? '' : 'disabled' ?>>
                    </div>
                </div>
                <div class="ann-field">
                    <label>Button link <span class="ann-langtag">— optional</span></label>
                    <input type="url" name="cta_url" value="<?= $v('cta_url') ?>" placeholder="https://teepsaa.com/products/" <?= $editable ? '' : 'disabled' ?>>
                </div>

                <?php if ($editable): ?>
                <div class="ann-actions">
                    <button type="submit" name="action" value="save" class="btn btn-primary">Save draft</button>
                    <?php if ($id): ?>
                    <button type="submit" name="action" value="test" class="btn">Send test to <?= htmlspecialchars($adminEmail) ?></button>
                    <?php endif; ?>
                    <a href="/admin/messages/announcements.php" class="btn">Cancel</a>
                </div>
                <?php endif; ?>
            </form>

            <?php if ($id && $editable): ?>
            <form method="POST" action="/admin/messages/announcement-action.php" class="ann-send-form"
                  onsubmit="return confirm('Send this announcement to <?= $recipientCount ?> recipients? This cannot be undone.');">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button type="submit" name="action" value="queue" class="btn btn-send">Send to <?= $recipientCount ?> recipient<?= $recipientCount === 1 ? '' : 's' ?></button>
                <span class="ann-hint">Sends the saved draft exactly as previewed — save your edits first.</span>
            </form>

            <form method="POST" action="/admin/messages/announcement-action.php" style="margin-top:1rem;"
                  onsubmit="return confirm('Delete this draft?');">
                <?= csrf_input() ?>
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button type="submit" name="action" value="delete" class="btn-admin-sm btn-admin-sm--danger">Delete draft</button>
            </form>
            <?php endif; ?>

            <?php if ($a['status'] === 'sending'): ?>
            <div class="ann-actions">
                <form method="POST" action="/admin/messages/announcement-action.php">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button type="submit" name="action" value="batch" class="btn btn-primary">Send next <?= min($pending, ANNOUNCEMENT_BATCH) ?> now</button>
                </form>
                <form method="POST" action="/admin/messages/announcement-action.php"
                      onsubmit="return confirm('Stop sending? Everyone already emailed keeps their copy.');">
                    <?= csrf_input() ?>
                    <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                    <button type="submit" name="action" value="cancel" class="btn">Stop sending</button>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($problems): ?>
            <h2 style="margin-top:2rem;">Not delivered (<?= count($problems) ?><?= count($problems) === 50 ? '+' : '' ?>)</h2>
            <div class="ann-problems">
                <?php foreach ($problems as $p): ?>
                <div class="ann-problem">
                    <span class="ann-problem-status ann-problem-status--<?= htmlspecialchars($p['status']) ?>"><?= htmlspecialchars($p['status']) ?></span>
                    <span class="ann-problem-email"><?= htmlspecialchars($p['email']) ?></span>
                    <span class="ann-problem-why"><?= htmlspecialchars($p['error'] ?? '') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="ann-preview">
            <h2>Preview <span class="ann-langtag">(<?= $editable ? 'last saved draft' : 'as sent' ?>)</span></h2>
            <iframe srcdoc="<?= htmlspecialchars($previewHtml, ENT_QUOTES) ?>" title="Announcement preview"></iframe>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../../footer/footer.php'; ?>
</body>
</html>
