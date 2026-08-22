<?php
session_start([
    'gc_maxlifetime'  => 28800,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_domain'   => str_ends_with($_SERVER['HTTP_HOST'] ?? '', 'teepsaa.com') ? '.teepsaa.com' : '',
]);

require __DIR__ . '/../../config/csrf.php';
require __DIR__ . '/../../config/db.php';
require __DIR__ . '/../../config/admin-auth.php';
require __DIR__ . '/../../config/announcements.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('messages');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/messages/announcements.php');
    exit;
}
csrf_verify();

$action = $_POST['action'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

$list = '/admin/messages/announcements.php';
$edit = fn(int $i) => $i ? '/admin/messages/announcement-edit.php?id=' . $i : '/admin/messages/announcement-edit.php';

$done = function (string $url, ?string $ok = null, ?string $err = null) {
    if ($ok)  $_SESSION['ann_success'] = $ok;
    if ($err) $_SESSION['ann_error']   = $err;
    header('Location: ' . $url);
    exit;
};

$load = function (int $i) use ($pdo, $done, $list): array {
    $stmt = $pdo->prepare('SELECT * FROM announcements WHERE id = ?');
    $stmt->execute([$i]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) $done($list, null, 'Announcement not found.');
    return $row;
};

// ── Save (create or update a draft) ───────────────────────────────────
if ($action === 'save' || $action === 'test') {
    $data = [
        'audience'   => in_array($_POST['audience'] ?? '', ['buyers', 'vendors', 'both'], true) ? $_POST['audience'] : 'buyers',
        'kind'       => ($_POST['kind'] ?? '') === 'service' ? 'service' : 'promotional',
        'subject_km' => trim($_POST['subject_km'] ?? ''),
        'subject_en' => trim($_POST['subject_en'] ?? ''),
        'heading_km' => trim($_POST['heading_km'] ?? ''),
        'heading_en' => trim($_POST['heading_en'] ?? ''),
        'body_km'    => trim($_POST['body_km'] ?? ''),
        'body_en'    => trim($_POST['body_en'] ?? ''),
        'cta_km'     => trim($_POST['cta_km'] ?? ''),
        'cta_en'     => trim($_POST['cta_en'] ?? ''),
        'cta_url'    => trim($_POST['cta_url'] ?? ''),
    ];

    $fail = function (string $msg) use ($data, $id, $edit, $done) {
        $_SESSION['ann_old'] = $data;
        $done($edit($id), null, $msg);
    };

    foreach (['subject_km', 'subject_en', 'heading_km', 'heading_en', 'body_km', 'body_en'] as $f) {
        if ($data[$f] === '') $fail('Subject, heading and body are required in both languages.');
    }
    // A button with no link (or a link with no label) silently renders nothing.
    if (($data['cta_km'] || $data['cta_en']) && $data['cta_url'] === '') {
        $fail('A button label needs a button link.');
    }
    if ($data['cta_url'] !== '' && !filter_var($data['cta_url'], FILTER_VALIDATE_URL)) {
        $fail('The button link is not a valid URL.');
    }

    if ($id) {
        $a = $load($id);
        if ($a['status'] !== 'draft') $done($edit($id), null, 'This announcement has already been sent — it can no longer be edited.');
        $pdo->prepare(
            'UPDATE announcements
                SET audience = ?, kind = ?, subject_km = ?, subject_en = ?, heading_km = ?, heading_en = ?,
                    body_km = ?, body_en = ?, cta_km = ?, cta_en = ?, cta_url = ?
              WHERE id = ?'
        )->execute([
            $data['audience'], $data['kind'], $data['subject_km'], $data['subject_en'],
            $data['heading_km'], $data['heading_en'], $data['body_km'], $data['body_en'],
            $data['cta_km'] ?: null, $data['cta_en'] ?: null, $data['cta_url'] ?: null, $id,
        ]);
    } else {
        $pdo->prepare(
            'INSERT INTO announcements
                (audience, kind, subject_km, subject_en, heading_km, heading_en,
                 body_km, body_en, cta_km, cta_en, cta_url, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $data['audience'], $data['kind'], $data['subject_km'], $data['subject_en'],
            $data['heading_km'], $data['heading_en'], $data['body_km'], $data['body_en'],
            $data['cta_km'] ?: null, $data['cta_en'] ?: null, $data['cta_url'] ?: null,
            (int)$_SESSION['admin_id'],
        ]);
        $id = (int)$pdo->lastInsertId();
    }

    if ($action === 'test') {
        $a = $load($id);
        $ae = $pdo->prepare('SELECT email FROM admins WHERE id = ?');
        $ae->execute([(int)$_SESSION['admin_id']]);
        $to = (string)$ae->fetchColumn();
        if (!$to) $done($edit($id), null, 'Saved, but your admin account has no email address to test with.');

        [$subject, $html] = announcement_render(
            $a,
            $a['kind'] !== 'service' ? unsubscribe_url('buyer', 'test-link-not-active') : null
        );
        $err = announcement_deliver($to, '[TEST] ' . $subject, $html);
        $done($edit($id), $err === null ? 'Saved. Test email sent to ' . $to . '.' : null,
                          $err === null ? null : 'Saved, but the test email failed: ' . $err);
    }

    $done($edit($id), 'Draft saved.');
}

// ── Queue the send ────────────────────────────────────────────────────
if ($action === 'queue') {
    $a = $load($id);
    if ($a['status'] !== 'draft') $done($edit($id), null, 'This announcement has already been sent.');

    $n = announcement_queue($pdo, $a);
    if ($n === 0) {
        $pdo->prepare("UPDATE announcements SET status = 'draft', total_recipients = 0, queued_at = NULL WHERE id = ?")
            ->execute([$id]);
        $done($edit($id), null, 'Nobody matches that audience right now — nothing was sent.');
    }
    $done($edit($id), $n . ' recipients queued. Sending starts now and continues in the background.');
}

// ── Send one batch by hand (also what the cron worker does) ───────────
if ($action === 'batch') {
    $a = $load($id);
    if ($a['status'] !== 'sending') $done($edit($id), null, 'This announcement is not sending.');

    $r = announcement_process_batch($pdo);
    if ($r === null) $done($edit($id), null, 'Nothing left to send.');
    $done($edit($id), sprintf(
        '%d sent, %d failed, %d skipped. %s',
        $r['sent'], $r['failed'], $r['skipped'],
        $r['finished'] ? 'All done.' : $r['remaining'] . ' still queued.'
    ));
}

// ── Stop a send in progress ───────────────────────────────────────────
if ($action === 'cancel') {
    $a = $load($id);
    if ($a['status'] !== 'sending') $done($edit($id), null, 'This announcement is not sending.');
    $pdo->prepare("UPDATE announcements SET status = 'cancelled', finished_at = NOW() WHERE id = ?")->execute([$id]);
    $pdo->prepare("UPDATE announcement_recipients SET status = 'skipped', error = 'send cancelled' WHERE announcement_id = ? AND status = 'pending'")->execute([$id]);
    $done($edit($id), 'Sending stopped. Everyone already emailed keeps their copy.');
}

// ── Delete a draft ────────────────────────────────────────────────────
if ($action === 'delete') {
    $a = $load($id);
    // Sent announcements are the only record of what went out to the list.
    if ($a['status'] !== 'draft') $done($edit($id), null, 'Only a draft can be deleted.');
    $pdo->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
    $done($list, 'Draft deleted.');
}

header('Location: ' . $list);
exit;
