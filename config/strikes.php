<?php
// Strikes: what a shop gets for a refused return or anything else an admin
// decides is a violation. One button on the admin vendor page gives the next
// one, and the step it lands on decides the penalty:
//
//   1  a warning and +5% royalty for 30 days
//   2  +10% royalty for 60 days
//   3  the shop is suspended for 30 days (lifted by cron/auto-confirm.php)
//   4  the account is suspended for good
//
// The step is the number of strikes still counting plus one. Each counts for
// STRIKE_EXPIRY_MONTHS from the day it was given, so a shop that behaves for a
// year starts again at 1. Past 4 it stays at 4.
//
// A new strike REPLACES the current royalty penalty instead of adding to it:
// every penalty still running on the shop is cleared first, the hand-made ones
// from the Penalties card included. A 2nd strike in the middle of the 1st
// one's 30 days means +10%, not +15%.
//
// Removing a strike (an admin's mistake) only stops it counting. The penalty
// or suspension it caused is lifted on its own card, deliberately by hand.

require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/fingerprint.php';

const STRIKE_EXPIRY_MONTHS = 12;
const STRIKE_MAX           = 4;

const STRIKE_STEPS = [
    1 => ['rate' => 0.05, 'days' => 30],
    2 => ['rate' => 0.10, 'days' => 60],
    3 => ['suspend_days' => 30],
    4 => ['account' => true],
];

/** Strikes still counting against a shop. */
function strikes_active_count(PDO $pdo, int $businessId): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM vendor_strikes WHERE business_id = ? AND revoked_at IS NULL AND expires_at > NOW()');
    $stmt->execute([$businessId]);
    return (int)$stmt->fetchColumn();
}

/** The step the next strike would land on. */
function strike_next_level(PDO $pdo, int $businessId): int {
    return min(STRIKE_MAX, strikes_active_count($pdo, $businessId) + 1);
}

/** What a step does, for the admin button and the vendor's email. */
function strike_penalty_text(int $level, string $lang): string {
    $s = STRIKE_STEPS[$level];
    if ($lang === 'km') {
        if (isset($s['rate'])) return 'កម្រៃជើងសារ +' . ($s['rate'] * 100) . '% រយៈពេល ' . $s['days'] . ' ថ្ងៃ ជំនួសការពិន័យណាមួយដែលកំពុងដំណើរការ។';
        if (isset($s['suspend_days'])) return 'ហាងរបស់អ្នកត្រូវបានផ្អាករយៈពេល ' . $s['suspend_days'] . ' ថ្ងៃ ហើយនឹងបង្ហាញដល់អ្នកទិញវិញដោយស្វ័យប្រវត្តិ។ ការបញ្ជាទិញដែលមានស្រាប់មិនរងផលប៉ះពាល់ទេ។';
        return 'គណនីអ្នកលក់របស់អ្នកត្រូវបានផ្អាកជាអចិន្ត្រៃយ៍។';
    }
    if (isset($s['rate'])) return '+' . ($s['rate'] * 100) . '% royalty for ' . $s['days'] . ' days, replacing any penalty already running.';
    if (isset($s['suspend_days'])) return 'Your shop is suspended for ' . $s['suspend_days'] . ' days and goes back up on its own. Orders you already have are not affected.';
    return 'Your vendor account is suspended for good.';
}

/**
 * Give a shop its next strike and apply that step's penalty. Returns
 * [level, null] or [null, error]. The vendor is told by notification and
 * by email.
 */
function strike_give(PDO $pdo, int $businessId, int $vendorId, string $reason, int $adminId): array {
    $reason = mb_substr(trim($reason), 0, 255);
    if ($reason === '') return [null, 'missing_reason'];

    $stmt = $pdo->prepare('
        SELECT b.id, b.name, v.name AS vendor_name, v.email AS vendor_email
          FROM businesses b JOIN vendors v ON v.id = b.user_id
         WHERE b.id = ? AND b.user_id = ? AND b.deleted_at IS NULL
    ');
    $stmt->execute([$businessId, $vendorId]);
    $biz = $stmt->fetch();
    if (!$biz) return [null, 'not_found'];

    $pdo->beginTransaction();
    try {
        // Locks the shop's row so two admins pressing at once cannot both
        // read the same count and both give strike 2.
        $pdo->prepare('SELECT id FROM businesses WHERE id = ? FOR UPDATE')->execute([$businessId]);
        $level = strike_next_level($pdo, $businessId);
        $step  = STRIKE_STEPS[$level];

        $pdo->prepare('
            INSERT INTO vendor_strikes (business_id, level, reason, created_by, expires_at)
            VALUES (?, ?, ?, ?, NOW() + INTERVAL ' . STRIKE_EXPIRY_MONTHS . ' MONTH)
        ')->execute([$businessId, $level, $reason, $adminId]);

        // Replace, never add: whatever royalty penalty is running stops today.
        $pdo->prepare('
            UPDATE vendor_penalties SET cleared_at = NOW()
             WHERE business_id = ? AND cleared_at IS NULL AND (end_date IS NULL OR end_date >= CURDATE())
        ')->execute([$businessId]);

        $note = 'Strike ' . $level . ': ' . $reason;
        if (isset($step['rate'])) {
            $pdo->prepare('
                INSERT INTO vendor_penalties (business_id, rate_increase, admin_note, start_date, end_date, created_by)
                VALUES (?, ?, ?, CURDATE(), CURDATE() + INTERVAL ' . (int)$step['days'] . ' DAY, ?)
            ')->execute([$businessId, $step['rate'], $note, $adminId]);
        } elseif (isset($step['suspend_days'])) {
            // A shop already taken down by hand, with no end date, stays down
            // until an admin lifts it; the strike does not shorten that.
            $pdo->prepare('
                UPDATE businesses
                   SET suspended_until   = IF(suspended = 1 AND suspended_until IS NULL, NULL, NOW() + INTERVAL ' . (int)$step['suspend_days'] . ' DAY),
                       suspension_reason = IF(suspended = 1, suspension_reason, ?),
                       suspended_at      = IF(suspended = 1, suspended_at, NOW()),
                       suspended         = 1
                 WHERE id = ?
            ')->execute([$note, $businessId]);
        } else {
            // As admin/vendor-action.php 'suspend': no sign-in, every shop down,
            // and fingerprints kept so a new account is recognised.
            $pdo->prepare('UPDATE vendors SET suspended = 1, suspension_reason = ?, suspended_at = NOW() WHERE id = ?')
                ->execute([$note, $vendorId]);
            $pdo->prepare('
                UPDATE businesses
                   SET suspension_reason = IF(suspended = 1, suspension_reason, ?),
                       suspended_at      = IF(suspended = 1, suspended_at, NOW()),
                       suspended = 1, suspended_until = NULL
                 WHERE user_id = ? AND deleted_at IS NULL
            ')->execute([$note, $vendorId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    if ($level === STRIKE_MAX) fingerprint_save($pdo, 'vendor', $vendorId, $note);
    audit_log($pdo, 'business.strike', 'business', $businessId, ['level' => $level, 'reason' => $reason, 'vendor_id' => $vendorId]);

    try {
        notify($pdo, 'vendor', $vendorId, 'strike',
            'Your shop received strike ' . $level . ' of ' . STRIKE_MAX . '. ' . strike_penalty_text($level, 'en'),
            $level <= 2 ? '/products/' : null, ['level' => $level]);
    } catch (Throwable $e) {
        error_log('strike_give: notify failed for business ' . $businessId . ': ' . $e->getMessage());
    }

    if ($biz['vendor_email']) {
        [$subj, $html] = render_email_template($pdo, 'vendor_strike', [
            'name'       => htmlspecialchars($biz['vendor_name']),
            'business'   => htmlspecialchars($biz['name']),
            'strike'     => $level,
            'reason'     => htmlspecialchars($reason),
            'penalty_km' => strike_penalty_text($level, 'km'),
            'penalty_en' => strike_penalty_text($level, 'en'),
            'cta_url'    => 'https://teepsaa.com/contact/',
        ]);
        if ($html !== '') {
            try { send_email($biz['vendor_email'], $subj, $html); } catch (Throwable $e) { /* logged by send_email */ }
        }
    }

    return [$level, null];
}

/**
 * Put back up every shop whose 3rd-strike suspension has run out. Run hourly
 * by cron/auto-confirm.php. A shop whose account is suspended stays down —
 * lifting the account never puts a shop back on its own either.
 */
function strike_lift_suspensions(PDO $pdo): int {
    $due = $pdo->query('
        SELECT b.id, b.name, b.user_id, v.name AS vendor_name, v.email AS vendor_email
          FROM businesses b JOIN vendors v ON v.id = b.user_id
         WHERE b.suspended = 1 AND b.suspended_until IS NOT NULL AND b.suspended_until <= NOW()
           AND v.suspended = 0 AND b.deleted_at IS NULL
    ')->fetchAll();

    $lift = $pdo->prepare('
        UPDATE businesses SET suspended = 0, suspension_reason = NULL, suspended_at = NULL, suspended_until = NULL
         WHERE id = ? AND suspended = 1 AND suspended_until IS NOT NULL AND suspended_until <= NOW()
    ');
    $n = 0;
    foreach ($due as $b) {
        $lift->execute([$b['id']]);
        if (!$lift->rowCount()) continue;
        $n++;
        audit_log($pdo, 'business.unsuspend', 'business', (int)$b['id'], ['vendor_id' => (int)$b['user_id'], 'by' => 'strike_expiry'], ['label' => 'system:cron']);
        if ($b['vendor_email']) {
            [$subj, $html] = render_email_template($pdo, 'business_reinstated', [
                'name'     => htmlspecialchars($b['vendor_name']),
                'business' => htmlspecialchars($b['name']),
                'cta_url'  => 'https://vendor.teepsaa.com/analytics/',
            ]);
            if ($html !== '') {
                try { send_email($b['vendor_email'], $subj, $html); } catch (Throwable $e) { /* logged by send_email */ }
            }
        }
    }
    return $n;
}
