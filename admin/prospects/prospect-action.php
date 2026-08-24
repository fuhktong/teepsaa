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
require __DIR__ . '/../../config/prospects.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: /login-admin/');
    exit;
}

admin_require('prospects');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/prospects/');
    exit;
}
csrf_verify();

$adminId = (int)$_SESSION['admin_id'];
$action  = $_POST['action'] ?? '';
$id      = (int)($_POST['id'] ?? 0);

$list   = '/admin/prospects/';
$detail = fn(int $i) => '/admin/prospects/prospect.php?id=' . $i;

$done = function (string $url, ?string $ok = null, ?string $err = null) {
    if ($ok)  $_SESSION['psp_success'] = $ok;
    if ($err) $_SESSION['psp_error']   = $err;
    header('Location: ' . $url);
    exit;
};

// Keeps a failed save from wiping the form.
$stash = function () {
    $_SESSION['psp_old'] = $_POST;
};

$num = function (string $field): ?float {
    $v = trim((string)($_POST[$field] ?? ''));
    return $v === '' ? null : (float)$v;
};

$str = function (string $field, int $max): ?string {
    $v = trim((string)($_POST[$field] ?? ''));
    return $v === '' ? null : mb_substr($v, 0, $max);
};

// A date input either holds a real date or nothing.
$date = function (string $field): ?string {
    $v = trim((string)($_POST[$field] ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
};

// One or more category names, comma separated — the shape businesses.category
// already uses, so a prospect that signs up converts across untranslated.
//
// The picker only ever offers real names, so anything else arrived by hand:
// each one is checked, unknowns and duplicates are dropped, and the order you
// added them in survives. Reading `categories` is the whole extent of the
// overlap with vendors — the names land on the prospect row and `businesses`
// is never touched.
$category = function () use ($pdo): ?string {
    $raw = trim((string)($_POST['category'] ?? ''));
    if ($raw === '') return null;

    static $names = null;
    $names ??= $pdo->query('SELECT name FROM categories')->fetchAll(PDO::FETCH_COLUMN);

    $keep = [];
    foreach (explode(',', $raw) as $one) {
        $one = trim($one);
        if ($one !== '' && in_array($one, $names, true) && !in_array($one, $keep, true)) {
            $keep[] = $one;
        }
    }

    // The column holds 255 (database/migration-prospect-categories.sql). Drop
    // whole names off the end rather than storing half of one.
    $out = '';
    foreach ($keep as $one) {
        $next = $out === '' ? $one : $out . ', ' . $one;
        if (mb_strlen($next) > 255) break;
        $out = $next;
    }
    return $out === '' ? null : $out;
};

$load = function (int $i) use ($pdo, $done, $list): array {
    $stmt = $pdo->prepare('SELECT * FROM prospects WHERE id = ?');
    $stmt->execute([$i]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) $done($list, null, 'Prospect not found.');
    return $row;
};

// Writes a visit row and pulls the prospect's status up to that outcome.
$logVisit = function (int $prospectId, string $outcome, ?string $note, ?float $lat, ?float $lng) use ($pdo, $adminId): int {
    $pdo->prepare(
        'INSERT INTO prospect_visits (prospect_id, admin_id, outcome, note, lat, lng)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$prospectId, $adminId, $outcome, $note, $lat, $lng]);
    $visitId = (int)$pdo->lastInsertId();

    $pdo->prepare('UPDATE prospects SET status = ? WHERE id = ?')->execute([$outcome, $prospectId]);
    return $visitId;
};

switch ($action) {

    // ── Create ────────────────────────────────────────────────────────
    case 'create': {
        $name = trim((string)($_POST['business_name'] ?? ''));
        if ($name === '') {
            $stash();
            $done('/admin/prospects/new.php', null, 'A business name is required.');
        }

        $lat    = $num('lat');
        $lng    = $num('lng');
        $status = prospect_valid_status($_POST['status'] ?? null);

        $pdo->prepare(
            'INSERT INTO prospects
                (business_name, business_name_km, owner_name, phone, telegram, category,
                 address, lat, lng, status, next_followup_at, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            mb_substr($name, 0, 160),
            $str('business_name_km', 160),
            $str('owner_name', 120),
            $str('phone', 30),
            $str('telegram', 60),
            $category(),
            $str('address', 255),
            $lat, $lng, $status,
            $date('next_followup_at'),
            $str('notes', 65535),
            $adminId,
        ]);
        $newId = (int)$pdo->lastInsertId();

        // The first visit is implied by the fact you were standing there.
        $visitId = null;
        if ($status !== 'to_visit') {
            $visitId = $logVisit($newId, $status, $str('notes', 65535), $lat, $lng);
        }

        $msg = 'Prospect saved.';
        $err = null;
        if (!prospect_attach_photo($pdo, $newId, $visitId, $err)) {
            $msg .= ' Photo was not saved: ' . $err;
        }

        $warn = prospect_duplicate_warning($pdo, $name, $lat, $lng, $newId);
        if ($warn) $msg .= ' ' . $warn;

        $done($detail($newId), $msg);
    }

    // ── Edit the record itself ────────────────────────────────────────
    case 'update': {
        $p    = $load($id);
        $name = trim((string)($_POST['business_name'] ?? ''));
        if ($name === '') {
            $stash();
            $done($detail($id), null, 'A business name is required.');
        }

        $pdo->prepare(
            'UPDATE prospects SET
                business_name = ?, business_name_km = ?, owner_name = ?, phone = ?,
                telegram = ?, category = ?, address = ?, lat = ?, lng = ?,
                status = ?, next_followup_at = ?, notes = ?
             WHERE id = ?'
        )->execute([
            mb_substr($name, 0, 160),
            $str('business_name_km', 160),
            $str('owner_name', 120),
            $str('phone', 30),
            $str('telegram', 60),
            $category(),
            $str('address', 255),
            $num('lat'), $num('lng'),
            prospect_valid_status($_POST['status'] ?? null),
            $date('next_followup_at'),
            $str('notes', 65535),
            $id,
        ]);
        $done($detail($id), 'Changes saved.');
    }

    // ── Log a visit (the field action) ────────────────────────────────
    case 'visit': {
        $load($id);
        $outcome = prospect_valid_status($_POST['outcome'] ?? null);
        $visitId = $logVisit($id, $outcome, $str('note', 65535), $num('lat'), $num('lng'));

        $msg = 'Visit logged.';
        $err = null;
        if (!prospect_attach_photo($pdo, $id, $visitId, $err)) {
            $msg .= ' Photo was not saved: ' . $err;
        }
        $done($detail($id), $msg);
    }

    // ── Photo added on its own ────────────────────────────────────────
    case 'add-photo': {
        $load($id);
        $err = null;
        if (!prospect_attach_photo($pdo, $id, null, $err)) {
            $done($detail($id), null, $err ?: 'No photo was supplied.');
        }
        $done($detail($id), 'Photo added.');
    }

    case 'delete-photo': {
        $photoId = (int)($_POST['photo_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM prospect_photos WHERE id = ? AND prospect_id = ?');
        $stmt->execute([$photoId, $id]);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$photo) $done($detail($id), null, 'Photo not found.');

        $pdo->prepare('DELETE FROM prospect_photos WHERE id = ?')->execute([$photoId]);
        prospect_delete_photo_file($photo['filename']);
        $done($detail($id), 'Photo deleted.');
    }

    // ── Delete the prospect ───────────────────────────────────────────
    case 'delete': {
        $p = $load($id);

        // The FKs cascade the rows; the files on disk are ours to clean up.
        $files = $pdo->prepare('SELECT filename FROM prospect_photos WHERE prospect_id = ?');
        $files->execute([$id]);
        foreach ($files->fetchAll(PDO::FETCH_COLUMN) as $filename) {
            prospect_delete_photo_file($filename);
        }

        $pdo->prepare('DELETE FROM prospects WHERE id = ?')->execute([$id]);
        $done($list, 'Deleted "' . $p['business_name'] . '".');
    }
}

$done($list, null, 'Unknown action.');
