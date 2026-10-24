<?php
// What a refund request carries with it, and what happens once it is in.
//
// A change of mind is not a dispute: nothing to judge, so no photo is asked
// for and no admin approves it — the request goes straight to
// 'return_approved' and the buyer is told to send the item back. Every other
// reason, the buyer's own words included, is a dispute: at least one photo of
// the problem, and the admin looks before approving.
//
// The photos are kept like dispatch photos (config/order-photo.php), outside
// the web root, one row each in refund_photos.
//
// When the return arrives, the vendor either confirms it ("Received") or says
// the item is used, empty or different. That second answer needs photos of
// what arrived and holds the refund: the order is 'return_received' with
// return_disputed_at set, and an admin decides — the full product price, part
// of it, or nothing, in which case the item is posted back at the buyer's cost.
//
// A disputed request also gives the vendor one reply: their own words and up
// to REFUND_PHOTOS_MAX photos, within REFUND_REPLY_HOURS of the request. The
// admin decides once the vendor has replied or the time is up, so a vendor
// who says nothing cannot hold a refund past it.
//
// Used by orders-buyer/refund-request.php, products/return-disputed.php and
// products/refund-reply.php (website) and api/v1/buyer/order-action.php,
// api/v1/refund-disputed.php and api/v1/refund-reply.php (apps), so the two
// cannot drift apart. Needs config/notify.php loaded.

require_once __DIR__ . '/order-photo.php';
require_once __DIR__ . '/refund-action.php';

const REFUND_REASON_CHANGED_MIND = 'Changed my mind';
const REFUND_PHOTOS_MAX = 3;
const REFUND_REPLY_MAX_LENGTH = 1000;

// The values the forms post, which is what lands in refund_reason and what the
// vendor and admin read. The labels are the buyer's language. 'other' (the
// buyer's own words) is not in the list but is accepted alongside it.
const BUYER_REFUND_REASONS = [
    REFUND_REASON_CHANGED_MIND     => 'order_reason_mind',
    'Item not as described'        => 'order_reason_1',
    'Wrong item received'          => 'order_reason_2',
    'Item arrived damaged'         => 'order_reason_3',
    'Missing parts or accessories' => 'order_reason_4',
    'Quality not as expected'      => 'order_reason_5',
];

/**
 * The reason a form sent, or null when it is not one: a listed value, or
 * 'other' with the buyer's words. Returns [reason, null] or [null, error].
 */
function refund_reason_from(string $preset, string $other): array {
    $reason = $preset === 'other' ? trim($other) : $preset;
    if ($preset !== 'other' && !array_key_exists($preset, BUYER_REFUND_REASONS)) return [null, 'missing_reason'];
    if ($reason === '') return [null, 'missing_reason'];
    // Shown to the vendor and admin; a textarea has no limit of its own.
    if (mb_strlen($reason) > 1000) return [null, 'too_long'];
    return [$reason, null];
}

function refund_needs_photo(string $reason): bool {
    return $reason !== REFUND_REASON_CHANGED_MIND;
}

/** The uploaded `photos[]` as a list of single $_FILES entries, empty slots left out. */
function refund_uploaded_files(string $field = 'photos'): array {
    $f = $_FILES[$field] ?? null;
    if (!$f) return [];
    if (!is_array($f['name'])) $f = array_map(fn($v) => [$v], $f);
    $files = [];
    foreach (array_keys($f['name']) as $i) {
        if ((int)$f['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
        $files[] = [
            'name' => $f['name'][$i], 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i],
            'error' => $f['error'][$i], 'size' => $f['size'][$i],
        ];
    }
    return $files;
}

/**
 * Check and store the photos. All or nothing: one bad file and the ones
 * already stored are removed again. Returns [filenames, null] or [[], error].
 */
function refund_photos_store(array $files, bool $required): array {
    if (!$files) return $required ? [[], 'missing_photo'] : [[], null];
    if (count($files) > REFUND_PHOTOS_MAX) return [[], 'too_many_photos'];
    $saved = [];
    foreach ($files as $file) {
        [$name, $err] = order_photo_save($file);
        if ($err) {
            refund_photos_discard($saved);
            return [[], $err];
        }
        $saved[] = $name;
    }
    return [$saved, null];
}

function refund_photos_discard(array $filenames): void {
    foreach ($filenames as $f) @unlink(ORDER_PHOTO_DIR . '/' . basename($f));
}

function refund_photos_record(PDO $pdo, int $orderId, string $party, string $stage, array $filenames): void {
    $ins = $pdo->prepare('INSERT INTO refund_photos (order_id, party, stage, filename) VALUES (?, ?, ?, ?)');
    foreach ($filenames as $f) $ins->execute([$orderId, $party, $stage, $f]);
}

/** One order's refund photos as [party_stage => [filename, …]], oldest first — e.g. 'buyer_request'. */
function refund_photos_of(PDO $pdo, int $orderId): array {
    $stmt = $pdo->prepare('SELECT party, stage, filename FROM refund_photos WHERE order_id = ? ORDER BY id');
    $stmt->execute([$orderId]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) $out[$r['party'] . '_' . $r['stage']][] = $r['filename'];
    return $out;
}

/** The status a fresh request moves the order to. */
function refund_request_status(string $reason): string {
    return refund_needs_photo($reason) ? 'refund_requested' : 'return_approved';
}

/**
 * Tell the vendor a refund was asked for, and — when it was a change of mind
 * and so approved at once — tell the buyer to send it back, exactly as an
 * admin's approval would (admin/refund-action.php).
 */
function refund_request_notify(PDO $pdo, int $orderId): void {
    $stmt = $pdo->prepare('
        SELECT o.public_id, o.created_at, o.status,
               v.id AS vendor_id, v.name AS vendor_name, v.email AS vendor_email,
               bu.id AS buyer_id, bu.name AS buyer_name, bu.email AS buyer_email
          FROM orders o
          JOIN businesses b ON b.id = o.business_id
          JOIN vendors v ON v.id = b.user_id
          JOIN buyers bu ON bu.id = o.buyer_user_id
         WHERE o.id = ?
    ');
    $stmt->execute([$orderId]);
    $o = $stmt->fetch();
    if (!$o) return;

    $oid        = order_display_id($orderId, $o['created_at']);
    $vendorLink = $o['status'] === 'refund_requested'
        ? '/orders-vendor/refund.php?id=' . $o['public_id']
        : '/orders-vendor/order.php?id=' . $o['public_id'];
    $buyerLink  = '/orders-buyer/order.php?id=' . $o['public_id'];

    [$subj, $html] = render_email_template($pdo, 'refund_requested', [
        'name'    => htmlspecialchars((string)$o['vendor_name']),
        'order'   => $oid,
        'cta_url' => 'https://teepsaa.com' . $vendorLink,
    ]);
    if ($html !== '') send_email($o['vendor_email'], $subj, $html);
    // A dispute is the vendor's cue to reply; a change of mind needs nothing.
    if ($o['status'] === 'refund_requested') {
        notify($pdo, 'vendor', (int)$o['vendor_id'], 'refund_dispute',
            'A refund was requested for order #' . $oid . '. You have ' . REFUND_REPLY_HOURS . ' hours to reply with your side.',
            $vendorLink, ['ref' => $oid, 'hours' => REFUND_REPLY_HOURS]);
    } else {
        notify($pdo, 'vendor', (int)$o['vendor_id'], 'refund_requested',
            'A refund was requested for order #' . $oid . '.', $vendorLink, ['ref' => $oid]);
    }

    if ($o['status'] !== 'return_approved') return;
    [$subj, $html] = render_email_template($pdo, 'refund_approved', [
        'name'    => htmlspecialchars((string)$o['buyer_name']),
        'order'   => $oid,
        'cta_url' => 'https://teepsaa.com' . $buyerLink,
    ]);
    if ($html !== '') send_email($o['buyer_email'], $subj, $html);
    notify($pdo, 'buyer', (int)$o['buyer_id'], 'refund_approved',
        'Your refund was approved for order #' . $oid . ' — please send the item back.', $buyerLink, ['ref' => $oid]);
}

/**
 * The refund state shown everywhere: status, except that a rejection lives in
 * its own column and a disputed return is held at 'return_received'.
 */
function refund_state(array $o): string {
    if (!empty($o['refund_rejected_at'])) return 'refund_rejected';
    if ($o['status'] === 'return_received' && !empty($o['return_disputed_at'])) return 'return_disputed';
    return $o['status'];
}

/** What the buyer gets back, or got: refund_paid once decided, else the product price. */
function refund_figure(array $o): float {
    if (isset($o['refund_paid'])) return round((float)$o['refund_paid'], 2);
    return round((float)$o['subtotal'] - (float)$o['discount_amount'], 2);
}

/**
 * The vendor's "Item is used, empty or different". Returns null when done, or
 * an error code: not_found, shop_closed, wrong_status, or a photo error.
 */
function return_dispute(PDO $pdo, int $orderId, int $vendorId, array $files): ?string {
    $stmt = $pdo->prepare('
        SELECT o.status, b.deleted_at AS business_closed
          FROM orders o JOIN businesses b ON b.id = o.business_id
         WHERE o.id = ? AND b.user_id = ?
    ');
    $stmt->execute([$orderId, $vendorId]);
    $o = $stmt->fetch();
    if (!$o) return 'not_found';
    if ($o['business_closed'] !== null) return 'shop_closed';
    if ($o['status'] !== 'return_dispatched') return 'wrong_status';

    [$photos, $err] = refund_photos_store($files, true);
    if ($err) return $err;

    $upd = $pdo->prepare("
        UPDATE orders o JOIN businesses b ON b.id = o.business_id
           SET o.status = 'return_received', o.return_disputed_at = NOW()
         WHERE o.id = ? AND b.user_id = ? AND b.deleted_at IS NULL AND o.status = 'return_dispatched'
    ");
    $upd->execute([$orderId, $vendorId]);
    if ($upd->rowCount() === 0) {
        refund_photos_discard($photos);
        return 'wrong_status';
    }
    refund_photos_record($pdo, $orderId, 'vendor', 'return', $photos);

    // Held, not lost. A failure past here must not read as a failed report.
    try {
        $b = $pdo->prepare('SELECT public_id, created_at, buyer_user_id FROM orders WHERE id = ?');
        $b->execute([$orderId]);
        if ($row = $b->fetch()) {
            $oid = order_display_id($orderId, $row['created_at']);
            notify($pdo, 'buyer', (int)$row['buyer_user_id'], 'return_disputed',
                'The shop says the item returned for order #' . $oid . ' is used, empty or different. teepsaa is checking the photos before deciding your refund.',
                '/orders-buyer/order.php?id=' . $row['public_id'], ['ref' => $oid]);
        }
    } catch (Throwable $e) {
        error_log('return_dispute: notify failed for order ' . $orderId . ': ' . $e->getMessage());
    }
    return null;
}

/**
 * When the vendor's reply window closes, as a timestamp, or null when this
 * request has none: only a dispute still waiting for the admin has one.
 * Needs refund_requested_at, status and vendor_reply_at.
 */
function refund_reply_until(array $o): ?int {
    if ($o['status'] !== 'refund_requested' || empty($o['refund_requested_at'])) return null;
    return strtotime($o['refund_requested_at']) + REFUND_REPLY_HOURS * 3600;
}

/** The vendor may still reply — and so the admin waits. */
function refund_reply_open(array $o): bool {
    $until = refund_reply_until($o);
    return $until !== null && empty($o['vendor_reply_at']) && time() < $until;
}

/**
 * The vendor's reply to a dispute. Returns null when done, or an error code:
 * not_found, shop_closed, wrong_status, reply_closed, missing_reply, too_long,
 * or a photo error.
 */
function refund_reply(PDO $pdo, int $orderId, int $vendorId, string $text, array $files): ?string {
    $stmt = $pdo->prepare('
        SELECT o.status, o.refund_requested_at, o.vendor_reply_at, b.deleted_at AS business_closed
          FROM orders o JOIN businesses b ON b.id = o.business_id
         WHERE o.id = ? AND b.user_id = ?
    ');
    $stmt->execute([$orderId, $vendorId]);
    $o = $stmt->fetch();
    if (!$o) return 'not_found';
    if ($o['business_closed'] !== null) return 'shop_closed';
    if ($o['status'] !== 'refund_requested') return 'wrong_status';
    if (!refund_reply_open($o)) return 'reply_closed';

    $text = trim($text);
    if ($text === '') return 'missing_reply';
    if (mb_strlen($text) > REFUND_REPLY_MAX_LENGTH) return 'too_long';

    [$photos, $err] = refund_photos_store($files, false);
    if ($err) return $err;

    // The same window again in SQL, so a reply racing the admin's decision or
    // a second reply matches nothing.
    $upd = $pdo->prepare("
        UPDATE orders o JOIN businesses b ON b.id = o.business_id
           SET o.vendor_reply = ?, o.vendor_reply_at = NOW()
         WHERE o.id = ? AND b.user_id = ? AND b.deleted_at IS NULL
           AND o.status = 'refund_requested' AND o.vendor_reply_at IS NULL
           AND o.refund_requested_at > NOW() - INTERVAL " . REFUND_REPLY_HOURS . " HOUR
    ");
    $upd->execute([$text, $orderId, $vendorId]);
    if ($upd->rowCount() === 0) {
        refund_photos_discard($photos);
        return 'reply_closed';
    }
    refund_photos_record($pdo, $orderId, 'vendor', 'reply', $photos);
    return null;
}
