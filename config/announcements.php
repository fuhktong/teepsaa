<?php
// Announcements — one-off bulk emails to buyers and/or vendors.
//
// Composed in /admin/messages/announcements.php, frozen into
// announcement_recipients when queued, then drained by cron/announcement-send.php.
// Kept out of the transactional path on purpose: config/mail.php opens a fresh
// SMTP connection per message, so a list of any size has to be paced.

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/unsubscribe.php';
require_once __DIR__ . '/notify.php';

// How many messages one worker pass sends. Small enough that a batch finishes
// well inside a web request when the admin clicks "Send next batch".
const ANNOUNCEMENT_BATCH = 25;

// ── Audience ──────────────────────────────────────────────────────────

function announcement_roles(string $audience): array {
    return match ($audience) {
        'vendors' => ['vendor'],
        'both'    => ['buyer', 'vendor'],
        default   => ['buyer'],
    };
}

// The eligibility rule, in one place so the count shown before sending and the
// rows frozen at queue time can never disagree.
//   - closed, banned and unverified accounts are always excluded
//   - opted-out accounts are excluded from promotional sends only
function announcement_audience_sql(string $role, string $kind): string {
    $table = unsubscribe_table($role);
    $sql   = "SELECT id, email FROM {$table}
              WHERE deleted_at IS NULL
                AND banned = 0
                AND email_verified_at IS NOT NULL
                AND email <> ''";
    if ($kind !== 'service') {
        $sql .= ' AND unsubscribed_at IS NULL';
    }
    return $sql . ' ORDER BY id';
}

function announcement_audience_count(PDO $pdo, string $audience, string $kind): int {
    $n = 0;
    foreach (announcement_roles($audience) as $role) {
        $inner = announcement_audience_sql($role, $kind);
        $n += (int)$pdo->query("SELECT COUNT(*) FROM ({$inner}) x")->fetchColumn();
    }
    return $n;
}

// ── Rendering ─────────────────────────────────────────────────────────

// Bodies are stored as the admin typed them: plain text with line breaks, with
// simple inline HTML (<strong>, <a>, …) allowed through.
function announcement_body_html(string $raw): string {
    return nl2br($raw, false);
}

// Builds [subject, html] for one recipient. $unsubUrl is null for service
// announcements and for the preview.
function announcement_render(array $a, ?string $unsubUrl): array {
    $subject = email_subject_bi($a['subject_km'], $a['subject_en']);
    $html    = notification_email_html_bi(
        $a['heading_km'], announcement_body_html($a['body_km']),
        $a['heading_en'], announcement_body_html($a['body_en']),
        $a['cta_km'] ?: null,
        $a['cta_en'] ?: null,
        $a['cta_url'] ?: null,
        $unsubUrl ? unsubscribe_footer_html($unsubUrl) : null
    );
    return [$subject, $html];
}

// ── Queue ─────────────────────────────────────────────────────────────

// Freezes the current audience into announcement_recipients and flips the
// announcement to 'sending'. Returns the number of recipients queued.
function announcement_queue(PDO $pdo, array $a): int {
    $ins = $pdo->prepare(
        'INSERT IGNORE INTO announcement_recipients (announcement_id, role, user_id, email)
         VALUES (?, ?, ?, ?)'
    );
    $n = 0;
    $pdo->beginTransaction();
    try {
        foreach (announcement_roles($a['audience']) as $role) {
            $rows = $pdo->query(announcement_audience_sql($role, $a['kind']));
            while ($r = $rows->fetch(PDO::FETCH_ASSOC)) {
                $ins->execute([$a['id'], $role, (int)$r['id'], $r['email']]);
                $n++;
            }
        }
        $pdo->prepare(
            "UPDATE announcements
                SET status = 'sending', total_recipients = ?, queued_at = NOW(), finished_at = NULL
              WHERE id = ?"
        )->execute([$n, $a['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $n;
}

// Sends through the existing SMTP transport but reports per-recipient failure,
// which send_email() deliberately swallows. Returns null on success.
function announcement_deliver(string $to, string $subject, string $html): ?string {
    if (!SMTP_PASS) {          // local dev — config/mail.php writes to mail.log
        send_email($to, $subject, $html);
        return null;
    }
    try {
        smtp_deliver($to, $subject, $html);
        return null;
    } catch (Throwable $e) {
        return substr($e->getMessage(), 0, 240);
    }
}

// Sends up to $limit queued messages for the oldest announcement still sending.
// Returns ['id','sent','failed','skipped','remaining','finished'] — or null
// when nothing is queued at all.
function announcement_process_batch(PDO $pdo, int $limit = ANNOUNCEMENT_BATCH): ?array {
    $a = $pdo->query(
        "SELECT * FROM announcements WHERE status = 'sending' ORDER BY queued_at, id LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    if (!$a) return null;

    $stmt = $pdo->prepare(
        "SELECT * FROM announcement_recipients
          WHERE announcement_id = ? AND status = 'pending'
          ORDER BY id LIMIT {$limit}"
    );
    $stmt->execute([$a['id']]);
    $queue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $mark = $pdo->prepare(
        'UPDATE announcement_recipients SET status = ?, error = ?, sent_at = NOW() WHERE id = ?'
    );
    $sent = $failed = $skipped = 0;

    foreach ($queue as $r) {
        $role  = $r['role'];
        $table = unsubscribe_table($role);
        $acc = $pdo->prepare(
            "SELECT email, deleted_at, banned, unsubscribed_at FROM {$table} WHERE id = ?"
        );
        $acc->execute([$r['user_id']]);
        $u = $acc->fetch(PDO::FETCH_ASSOC);

        // Re-checked at send time, not just at queue time: a long list can take
        // a while to drain and someone may close, be banned, or opt out mid-run.
        $skipReason = null;
        if (!$u)                                                     $skipReason = 'account no longer exists';
        elseif ($u['deleted_at'])                                    $skipReason = 'account closed';
        elseif ((int)$u['banned'] === 1)                             $skipReason = 'account banned';
        elseif ($a['kind'] !== 'service' && $u['unsubscribed_at'])   $skipReason = 'unsubscribed';

        if ($skipReason) {
            $mark->execute(['skipped', $skipReason, $r['id']]);
            $skipped++;
            continue;
        }

        $unsubUrl = null;
        if ($a['kind'] !== 'service') {
            $tok = unsubscribe_token($pdo, $role, (int)$r['user_id']);
            if ($tok) $unsubUrl = unsubscribe_url($role, $tok);
        }
        [$subject, $html] = announcement_render($a, $unsubUrl);

        $err = announcement_deliver($u['email'], $subject, $html);
        if ($err === null) {
            $mark->execute(['sent', null, $r['id']]);
            $sent++;
        } else {
            $mark->execute(['failed', $err, $r['id']]);
            $failed++;
        }
    }

    if ($sent || $failed) {
        $pdo->prepare('UPDATE announcements SET sent_count = sent_count + ?, failed_count = failed_count + ? WHERE id = ?')
            ->execute([$sent, $failed, $a['id']]);
    }

    $rem = $pdo->prepare("SELECT COUNT(*) FROM announcement_recipients WHERE announcement_id = ? AND status = 'pending'");
    $rem->execute([$a['id']]);
    $remaining = (int)$rem->fetchColumn();

    if ($remaining === 0) {
        $pdo->prepare("UPDATE announcements SET status = 'sent', finished_at = NOW() WHERE id = ?")
            ->execute([$a['id']]);
    }

    return [
        'id'        => (int)$a['id'],
        'sent'      => $sent,
        'failed'    => $failed,
        'skipped'   => $skipped,
        'remaining' => $remaining,
        'finished'  => $remaining === 0,
    ];
}
