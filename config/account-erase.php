<?php
// What "delete my account" does, in one place for the website and both apps.
//
// The account row is kept and its personal details are erased. Orders,
// payments, payouts, refunds and reviews point at that row and are the sales
// records the accounts and taxes need, so the row stays as a blank "Deleted
// account" for them to point at. Everything that identifies the person goes:
// name, email, phone, photo, addresses, bank QR, saved items, follows, messages
// to support, notifications, sign-ins.
//
// This cannot be undone, and registering again with the same email makes a new,
// empty account — the email is no longer on the old row to match against.
//
// Orders do not keep their own copy of the buyer's name, phone or address; the
// order pages read them from the buyer row. That is why the callers refuse to
// erase while an order is still open (account_open_orders_*): the shop needs
// the address until the parcel has arrived and the order is settled.
//
// The caller checks the password, sends the goodbye email (read the address
// before calling — it is gone afterwards) and signs the session out.

require_once __DIR__ . '/upload.php';

// Every order still in motion. 'delivered' counts as open: the payout and any
// refund request still need both sides. A rejected refund goes back to
// 'delivered', so it is covered too.
const ACCOUNT_DONE_STATUSES = "('completed', 'cancelled', 'refunded')";

const ACCOUNT_ERASED_NAME = 'Deleted account';

function account_open_orders_buyer(PDO $pdo, int $buyerId): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE buyer_user_id = ? AND status NOT IN ' . ACCOUNT_DONE_STATUSES);
    $stmt->execute([$buyerId]);
    return (int)$stmt->fetchColumn();
}

function account_open_orders_vendor(PDO $pdo, int $vendorId): int {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM orders o
        JOIN businesses b ON b.id = o.business_id
        WHERE b.user_id = ? AND o.status NOT IN ' . ACCOUNT_DONE_STATUSES
    );
    $stmt->execute([$vendorId]);
    return (int)$stmt->fetchColumn();
}

function account_erase_buyer(PDO $pdo, int $buyerId): void {
    $avatar = $pdo->prepare('SELECT avatar FROM buyers WHERE id = ?');
    $avatar->execute([$buyerId]);
    $files = [$avatar->fetchColumn()];

    $pdo->beginTransaction();

    $pdo->prepare('
        UPDATE buyers SET
            name = ?, email = ?, password = ?, phone = NULL, avatar = NULL,
            house_number = NULL, address = NULL, address_notes = NULL,
            khan = NULL, sangkat = NULL, city = NULL, lat = NULL, lng = NULL,
            verify_token = NULL, verify_code_expires = NULL,
            unsubscribe_token = NULL, unsubscribed_at = NOW(),
            abandoned_cart_notified_at = NULL,
            deleted_at = COALESCE(deleted_at, NOW())
        WHERE id = ?
    ')->execute([ACCOUNT_ERASED_NAME, account_erased_email('buyer', $buyerId), account_dead_password(), $buyerId]);

    // What the buyer typed on an order and where they bought from. The order,
    // its items and its money stay.
    $pdo->prepare('UPDATE orders SET buyer_notes = NULL, buyer_ip = NULL WHERE buyer_user_id = ?')
        ->execute([$buyerId]);

    foreach (['buyer_addresses', 'cart_items', 'wishlists', 'business_follows'] as $table) {
        $pdo->prepare("DELETE FROM $table WHERE buyer_user_id = ?")->execute([$buyerId]);
    }
    account_erase_shared($pdo, 'buyer', $buyerId);

    $pdo->commit();

    account_unlink($files);
}

function account_erase_vendor(PDO $pdo, int $vendorId): void {
    $stmt = $pdo->prepare('SELECT avatar, aba_qr FROM vendors WHERE id = ?');
    $stmt->execute([$vendorId]);
    $files = array_values($stmt->fetch() ?: []);

    // Every shop the vendor ever had, including ones closed earlier — a closed
    // shop still holds the address it was run from.
    $stmt = $pdo->prepare('SELECT id, banner FROM businesses WHERE user_id = ?');
    $stmt->execute([$vendorId]);
    $businesses = $stmt->fetchAll();
    $bizIds = array_map('intval', array_column($businesses, 'id'));
    $files  = array_merge($files, array_column($businesses, 'banner'));

    if ($bizIds) {
        $in = implode(',', $bizIds);
        $files = array_merge($files, $pdo->query("SELECT filename FROM photos WHERE business_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN));
    }

    $pdo->beginTransaction();

    $pdo->prepare('
        UPDATE vendors SET
            name = ?, email = ?, password = ?, phone = NULL, avatar = NULL,
            aba_qr = NULL, aba_account_name = NULL,
            verify_token = NULL, verify_code_expires = NULL,
            unsubscribe_token = NULL, unsubscribed_at = NOW(),
            deleted_at = COALESCE(deleted_at, NOW())
        WHERE id = ?
    ')->execute([ACCOUNT_ERASED_NAME, account_erased_email('vendor', $vendorId), account_dead_password(), $vendorId]);

    // The shop's name stays: it is on buyers' receipts and order history. Where
    // it was run from does not. lat/lng cannot be NULL, so 0 stands for "none".
    // approved = -1 is how a business closed by its owner is marked.
    if ($bizIds) {
        $pdo->exec("
            UPDATE businesses SET
                house_number = NULL, address = NULL, address_notes = NULL,
                khan = NULL, sangkat = NULL, city = NULL, lat = 0, lng = 0,
                banner = NULL, approved = -1,
                deleted_at = COALESCE(deleted_at, NOW())
            WHERE id IN ($in)
        ");
        $pdo->exec("DELETE FROM photos WHERE business_id IN ($in)");
    }

    $pdo->prepare('DELETE FROM vendor_notifications WHERE vendor_user_id = ?')->execute([$vendorId]);
    account_erase_shared($pdo, 'vendor', $vendorId);

    $pdo->commit();

    account_unlink($files);
}

// The tables that hold either kind of account, told apart by role.
function account_erase_shared(PDO $pdo, string $role, int $id): void {
    foreach (['api_tokens', 'device_tokens', 'password_resets', 'notifications', 'announcement_recipients'] as $table) {
        $pdo->prepare("DELETE FROM $table WHERE user_id = ? AND role = ?")->execute([$id, $role]);
    }

    $pdo->prepare('
        DELETE m FROM support_messages m
        JOIN support_threads t ON t.id = m.thread_id
        WHERE t.sender_id = ? AND t.sender_role = ?
    ')->execute([$id, $role]);
    $pdo->prepare('DELETE FROM support_threads WHERE sender_id = ? AND sender_role = ?')->execute([$id, $role]);
}

// Unique and NOT NULL, so each erased row needs its own. .invalid is reserved —
// no mail can ever be delivered there.
function account_erased_email(string $role, int $id): string {
    return "deleted-$role-$id@deleted.invalid";
}

// A hash of random bytes nobody ever saw, so no password can match it.
function account_dead_password(): string {
    return password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
}

// Files go after the commit: a rolled-back erase must not have lost the photos.
function account_unlink(array $files): void {
    foreach ($files as $file) {
        $file = basename(trim((string)$file));
        if ($file === '' || $file === '.' || $file === '..') continue;
        $path = upload_dir() . '/' . $file;
        if (is_file($path)) @unlink($path);
        image_delete_derivatives($file);
    }
}
