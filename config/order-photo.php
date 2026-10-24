<?php
// Dispatch and return photos, and the rules for how a parcel was sent.
//
// A dispatch photo shows the parcel's label — the buyer's name, phone and
// address — so it never goes in uploads/, which is public and crawlable. It is
// kept outside the web root in teepsaa-private/order-photos/ and served by
// api/order-photo.php.
//
// That script has no login check, on purpose: the same picture has to load in
// a website page (session cookie), both apps (bearer token, which an <img>
// cannot send) and an email (nothing at all). The filename is the key instead —
// 32 random hex characters, which nobody can guess and which only reaches the
// order's buyer, its vendor and admins. Same model as a private share link.
//
// Used by analytics/dispatch.php (website) and api/v1/order-dispatch.php (app),
// so the two forms cannot drift apart.

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/url.php';

define('ORDER_PHOTO_DIR', PRIVATE_DIR . '/order-photos');
const ORDER_PHOTO_MAX_BYTES = 2 * 1024 * 1024;
const SHIPPING_METHODS = ['grab', 'courier', 'tuktuk', 'other'];

/**
 * Validate and store one uploaded photo ($_FILES entry).
 * Returns [filename, null] or [null, error code].
 */
function order_photo_save(?array $file): array {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [null, 'missing_photo'];
    if (in_array((int)$file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) return [null, 'too_large'];
    if ((int)$file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) return [null, 'upload_failed'];

    // Magic bytes, never the type the phone claimed.
    $mime = image_type_from_magic($file['tmp_name']);
    if ($mime !== 'image/jpeg' && $mime !== 'image/png') return [null, 'bad_type'];
    if ((int)$file['size'] > ORDER_PHOTO_MAX_BYTES) return [null, 'too_large'];

    if (!is_dir(ORDER_PHOTO_DIR) && !@mkdir(ORDER_PHOTO_DIR, 0775, true) && !is_dir(ORDER_PHOTO_DIR)) {
        error_log('order-photo: cannot create ' . ORDER_PHOTO_DIR);
        return [null, 'save_failed'];
    }
    $filename = bin2hex(random_bytes(16)) . ($mime === 'image/png' ? '.png' : '.jpg');
    if (!move_uploaded_file($file['tmp_name'], ORDER_PHOTO_DIR . '/' . $filename)) return [null, 'save_failed'];
    return [$filename, null];
}

/** Where a stored photo can be seen. Absolute for the apps and email. */
function order_photo_url(?string $filename, bool $absolute = false): ?string {
    if (!$filename) return null;
    return ($absolute ? SITE_URL : '') . '/api/order-photo.php?f=' . rawurlencode($filename);
}

/**
 * How the parcel was sent, from a form or a JSON body:
 *   shipping_method  one of SHIPPING_METHODS (required)
 *   courier          the company, required when the method is courier
 *   tracking         optional — a link (kept as tracking_url) or a waybill
 *                    number (kept as tracking_number). `tracking_url` is still
 *                    read for the first app build, which sent only that.
 * Returns [fields, null] or [null, error code].
 */
function dispatch_fields(array $in): array {
    $method  = (string)($in['shipping_method'] ?? '');
    $courier = trim(preg_replace('/\s+/', ' ', (string)($in['courier'] ?? '')));
    $track   = trim((string)($in['tracking'] ?? $in['tracking_url'] ?? ''));

    if (!in_array($method, SHIPPING_METHODS, true)) return [null, 'bad_method'];
    if ($method !== 'courier') $courier = '';
    if ($method === 'courier' && $courier === '') return [null, 'missing_courier'];
    if (mb_strlen($courier) > 60) return [null, 'courier_too_long'];

    $url = $number = null;
    if ($track !== '') {
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $track)) {
            // FILTER_VALIDATE_URL alone passes javascript: and data: links,
            // which end up as a clickable href for the buyer and the admin.
            $url = safe_external_url($track);
            if ($url === null) return [null, 'invalid_tracking_url'];
        } else {
            if (mb_strlen($track) > 80) return [null, 'tracking_too_long'];
            $number = $track;
        }
    }

    return [[
        'shipping_method' => $method,
        'courier'         => $courier !== '' ? $courier : null,
        'tracking_url'    => $url,
        'tracking_number' => $number,
    ], null];
}

/** How the parcel is coming, in the words of $t: the courier's name, "Grab", "tuk-tuk", or '' when nothing useful can be said. */
function dispatch_via(?string $method, ?string $courier, array $t): string {
    if ($method === 'courier' && $courier) return $courier;
    if ($method === 'grab' || $method === 'tuktuk') return $t['ship_via_' . $method] ?? '';
    return '';
}

/**
 * Tell the buyer their order is on its way: the bell and push, and the
 * "dispatched" email, both naming how it is coming. Called by
 * analytics/dispatch.php and api/v1/order-dispatch.php once the UPDATE has
 * gone through; needs config/notify.php loaded.
 */
function dispatch_notify_buyer(PDO $pdo, int $orderId): void {
    $stmt = $pdo->prepare('
        SELECT bu.id AS buyer_id, bu.email, bu.name,
               o.public_id, o.created_at, o.shipping_method, o.courier, o.tracking_number
          FROM orders o JOIN buyers bu ON bu.id = o.buyer_user_id
         WHERE o.id = ?
    ');
    $stmt->execute([$orderId]);
    $o = $stmt->fetch();
    if (!$o) return;

    $en = require __DIR__ . '/../lang/en.php';
    $km = require __DIR__ . '/../lang/km.php';
    $viaEn = dispatch_via($o['shipping_method'], $o['courier'], $en);
    $viaKm = dispatch_via($o['shipping_method'], $o['courier'], $km);

    $oid  = order_display_id($orderId, $o['created_at']);
    $link = '/orders-buyer/order.php?id=' . $o['public_id'];
    $data = ['ref' => $oid] + ($viaEn !== '' ? ['via' => $viaEn, 'via_km' => $viaKm] : []);
    notify($pdo, 'buyer', (int)$o['buyer_id'], 'order_dispatched',
        $viaEn !== '' ? sprintf($en['notif_order_dispatched_via'], $oid, $viaEn)
                      : 'Your order #' . $oid . ' has been dispatched and is on its way.',
        $link, $data);

    // Both halves of the bilingual email. Blank when there is nothing to say,
    // so an older copy of the template without these tokens reads the same.
    $shipping = function (array $t, string $via) use ($o): string {
        $s = $via !== '' ? sprintf($t['email_dispatch_via'], htmlspecialchars($via)) : '';
        if ($o['tracking_number']) $s .= sprintf($t['email_dispatch_tracking'], htmlspecialchars($o['tracking_number']));
        return $s;
    };
    [$subj, $html] = render_email_template($pdo, 'order_dispatched', [
        'name'        => htmlspecialchars($o['name']),
        'order'       => $oid,
        'shipping_en' => $shipping($en, $viaEn),
        'shipping_km' => $shipping($km, $viaKm),
        'cta_url'     => SITE_URL . $link,
    ]);
    if ($html !== '') send_email($o['email'], $subj, $html);
}
