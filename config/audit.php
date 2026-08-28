<?php

// Admin audit log — one row per state-changing admin action.
//
// The rule: if an action moves money, changes who can be paid, changes who has
// access, or changes an account's standing, it gets logged. Read-only page
// views do not.
//
// Requires config/db.php (for $pdo) and a started session. Include it after
// config/admin-auth.php so admin_id() is available.

require_once __DIR__ . '/admin-auth.php';

// How long payouts to a vendor are held after they change their bank details.
// A hijacked vendor account should not be able to redirect a payout that is
// already sitting in the queue before anyone notices the change.
const BANK_CHANGE_HOLD_SECONDS = 86400; // 24 hours

// Canonical action names. Kept as a list so the audit viewer and the daily
// activity digest can label them without duplicating strings, and so a typo in
// a call site shows up as an unlabelled row rather than silently blending in.
const AUDIT_ACTION_LABELS = [
    'business.approve'        => 'Business approved',
    'business.reject'         => 'Business rejected',
    'business.delete'         => 'Business deleted',
    'payment.confirm'         => 'Payment confirmed',
    'payment.reject'          => 'Payment rejected',
    'payout.complete'         => 'Payout released',
    'payout.solo_override'    => 'Payout released without a second admin',
    'payout.hold_override'    => 'Payout released during a bank-change hold',
    'refund.approve'          => 'Return approved',
    'refund.reject'           => 'Refund rejected',
    'refund.complete'         => 'Refund paid',
    'order.cancel'            => 'Order cancelled',
    'vendor.suspend'          => 'Vendor suspended',
    'vendor.unsuspend'        => 'Vendor reinstated',
    'vendor.royalty_waived'   => 'Vendor royalty waiver changed',
    'vendor.royalty_add_on'   => 'Vendor royalty rate changed',
    'admin.create'            => 'Admin account created',
    'admin.update'            => 'Admin account changed',
    'admin.toggle_active'     => 'Admin activated/deactivated',
    'admin.delete'            => 'Admin account deleted',
    'vendor.bank_change'      => 'Vendor changed their bank details',
];

// The subset that should never happen unnoticed. The activity digest pulls
// these out into their own section at the top of the email.
const AUDIT_HIGH_RISK = [
    'payout.solo_override',
    'payout.hold_override',
    'admin.create',
    'admin.update',
    'admin.toggle_active',
    'admin.delete',
    'vendor.bank_change',
];

function audit_label(string $action): string {
    return AUDIT_ACTION_LABELS[$action] ?? $action;
}

// Best-effort client IP. Hostinger sits behind a proxy, so prefer the
// forwarded header's first hop, but only when it parses as an IP — the header
// is client-supplied and must never be trusted into the column raw.
function audit_client_ip(): ?string {
    $candidates = [];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $candidates[] = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    $candidates[] = $_SERVER['REMOTE_ADDR'] ?? '';
    foreach ($candidates as $ip) {
        if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return null;
}

// Write one audit row.
//
// $actor lets a non-admin action be attributed to the account that did it —
// vendor.bank_change is written by the vendor, not an admin, and passes
// ['label' => 'vendor:someone@example.com']. Omit it for admin actions and the
// current admin session is used.
//
// Never throws. A failed audit write must not roll back or block the business
// action that was being performed — but it must not vanish either, so failures
// go to the PHP error log where the deploy can surface them.
function audit_log(
    PDO $pdo,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    array $detail = [],
    ?array $actor = null
): void {
    try {
        if ($actor !== null) {
            $adminId    = $actor['id'] ?? null;
            $adminEmail = $actor['label'] ?? null;
        } else {
            $adminId    = admin_id() ?: null;
            $adminEmail = audit_admin_email($pdo, $adminId);
        }

        $pdo->prepare(
            'INSERT INTO admin_audit (admin_id, admin_email, action, entity_type, entity_id, detail, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $adminId,
            $adminEmail,
            $action,
            $entityType,
            $entityId,
            $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            audit_client_ip(),
        ]);
    } catch (Throwable $e) {
        error_log('[audit] failed to log ' . $action . ': ' . $e->getMessage());
    }
}

// Email for the acting admin, resolved once per request. Cached in the session
// so the common case costs no query — but re-read when the session predates
// this feature, so an already-signed-in admin is still named correctly.
function audit_admin_email(PDO $pdo, ?int $adminId): ?string {
    if (!$adminId) {
        return null;
    }
    if (!empty($_SESSION['admin_email'])) {
        return $_SESSION['admin_email'];
    }
    try {
        $stmt = $pdo->prepare('SELECT email FROM admins WHERE id = ?');
        $stmt->execute([$adminId]);
        $email = $stmt->fetchColumn() ?: null;
    } catch (Throwable $e) {
        return null;
    }
    if ($email) {
        $_SESSION['admin_email'] = $email;
    }
    return $email;
}
