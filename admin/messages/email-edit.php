<?php
session_start();
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/csrf.php';
require __DIR__ . '/../../config/notify.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin' || !($_SESSION['is_admin'] ?? false)) {
    header('Location: /login-admin/');
    exit;
}

$key = $_GET['key'] ?? '';
$stmt = $pdo->prepare('SELECT * FROM email_templates WHERE template_key = ?');
$stmt->execute([$key]);
$tpl = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tpl) {
    header('Location: /admin/messages/emails.php');
    exit;
}

$error = $_SESSION['email_tpl_error'] ?? '';
unset($_SESSION['email_tpl_error']);
// A failed save stashes the submitted values so the form isn't wiped.
$old = $_SESSION['email_tpl_old'] ?? [];
unset($_SESSION['email_tpl_old']);
$val = fn(string $f) => htmlspecialchars($old[$f] ?? $tpl[$f] ?? '');

// Build a live preview from the CURRENTLY SAVED template with sample values.
$sample = [
    'name'      => 'Sok Dara',
    'order'     => '240704-0012',
    'product'   => 'Cool Jeans',
    'units'     => '3',
    'code'      => '<div style="font-size:2rem;font-weight:bold;letter-spacing:0.3em;font-family:monospace;margin:12px 0;color:#111">123456</div>',
    'link'      => '<p><a href="#">https://teepsaa.com/reset?token=…</a></p>',
    'summary'   => '<table style="width:100%;border-collapse:collapse"><tr><td style="padding:3px 0;font-size:0.9rem">Cool Jeans &times; 2</td><td style="padding:3px 0;font-size:0.9rem;text-align:right">$50.00</td></tr></table><hr style="border:none;border-top:1px solid #eee;margin:16px 0"><p style="margin:0;font-size:0.95rem"><strong>សរុប · Total: $50.00</strong></p>',
    'cart_list' => '<ul style="margin:12px 0;padding-left:20px"><li>Cool Jeans &times; 2</li><li>Silk Scarf</li></ul>',
    'cta_url'   => '#',
];
[, $previewHtml] = render_email_template($pdo, $key, $sample);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit: <?= htmlspecialchars($tpl['label']) ?> — Admin — teepsaa</title>
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/header/header.css">
    <link rel="stylesheet" href="/footer/footer.css">
    <link rel="stylesheet" href="/admin/admin.css">
    <style>
        .et-edit-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; align-items:start; }
        .et-field { margin-bottom:1rem; }
        .et-field label { font-size:0.85rem; font-weight:600; display:block; margin-bottom:4px; }
        .et-field input, .et-field textarea {
            width:100%; padding:0.5rem 0.75rem; border:1px solid var(--border-strong);
            border-radius:var(--radius-sm); font-size:0.9rem; font-family:inherit;
        }
        .et-field textarea { min-height:96px; resize:vertical; line-height:1.5; }
        .et-pair { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; }
        .et-langtag { font-weight:400; color:var(--text-muted); font-size:0.78rem; }
        .et-tokens { background:#f6f8fc; border:1px solid var(--border); border-radius:var(--radius-sm); padding:0.6rem 0.85rem; font-size:0.82rem; margin-bottom:1.25rem; }
        .et-tokens code { background:#e8edf7; padding:1px 5px; border-radius:3px; }
        .et-preview { position:sticky; top:1rem; }
        .et-preview iframe { width:100%; height:640px; border:1px solid var(--border); border-radius:var(--radius); background:#fff; }
        .et-preview h2, .et-edit-col h2 { font-size:0.95rem; margin-bottom:0.75rem; }
        .btn { display:inline-block; padding:0.5rem 1.1rem; border-radius:var(--radius-sm); border:1px solid var(--border-strong); background:#fff; font-size:0.9rem; cursor:pointer; font-family:inherit; text-decoration:none; color:inherit; }
        .btn-primary { background:var(--primary); color:#fff; border-color:var(--primary); }
        .btn-primary:hover { background:var(--primary-hover); }
        .admin-alert { padding:0.75rem 1rem; border-radius:var(--radius); font-size:0.9rem; margin-bottom:1rem; }
        .admin-alert--error { background:#fef2f2; color:#dc2626; border:1px solid #fca5a5; }
        @media (max-width:900px){ .et-edit-grid { grid-template-columns:1fr; } .et-preview { position:static; } }
    </style>
</head>
<body>
<?php require __DIR__ . '/../../header/header.php'; ?>
<main>
    <p style="margin-bottom:0.75rem;"><a href="/admin/messages/emails.php" style="color:var(--text-muted);font-size:0.85rem;text-decoration:none;">&larr; All email templates</a></p>
    <h1><?= htmlspecialchars($tpl['label']) ?></h1>

    <?php if ($tpl['tokens']): ?>
    <div class="et-tokens">
        <strong>Available placeholders:</strong>
        <?php foreach (array_map('trim', explode(',', $tpl['tokens'])) as $tok): ?><code><?= htmlspecialchars($tok) ?></code> <?php endforeach; ?>
        <br>Keep these exactly as-is — they are filled in automatically when the email is sent.
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="admin-alert admin-alert--error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="et-edit-grid">
        <div class="et-edit-col">
            <h2>Content</h2>
            <form method="POST" action="/admin/messages/email-save.php">
                <?= csrf_input() ?>
                <input type="hidden" name="key" value="<?= htmlspecialchars($tpl['template_key']) ?>">

                <div class="et-field">
                    <label>Subject</label>
                    <div class="et-pair">
                        <input type="text" name="subject_km" value="<?= $val('subject_km') ?>" placeholder="ខ្មែរ" required>
                        <input type="text" name="subject_en" value="<?= $val('subject_en') ?>" placeholder="English" required>
                    </div>
                </div>

                <div class="et-field">
                    <label>Heading</label>
                    <div class="et-pair">
                        <input type="text" name="heading_km" value="<?= $val('heading_km') ?>" placeholder="ខ្មែរ" required>
                        <input type="text" name="heading_en" value="<?= $val('heading_en') ?>" placeholder="English" required>
                    </div>
                </div>

                <div class="et-field">
                    <label>Body <span class="et-langtag">— Khmer (shown on top)</span></label>
                    <textarea name="body_km" required><?= $val('body_km') ?></textarea>
                </div>
                <div class="et-field">
                    <label>Body <span class="et-langtag">— English (shown below)</span></label>
                    <textarea name="body_en" required><?= $val('body_en') ?></textarea>
                </div>

                <div class="et-field">
                    <label>Button label <span class="et-langtag">— optional</span></label>
                    <div class="et-pair">
                        <input type="text" name="cta_km" value="<?= $val('cta_km') ?>" placeholder="ខ្មែរ">
                        <input type="text" name="cta_en" value="<?= $val('cta_en') ?>" placeholder="English">
                    </div>
                </div>

                <div style="display:flex;gap:0.5rem;margin-top:0.5rem;">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="/admin/messages/emails.php" class="btn">Cancel</a>
                </div>
            </form>
        </div>

        <div class="et-preview">
            <h2>Preview <span class="et-langtag">(sample values)</span></h2>
            <iframe srcdoc="<?= htmlspecialchars($previewHtml, ENT_QUOTES) ?>" title="Email preview"></iframe>
        </div>
    </div>
</main>
<?php require __DIR__ . '/../../footer/footer.php'; ?>
</body>
</html>
