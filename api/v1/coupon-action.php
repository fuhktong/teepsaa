<?php
// POST /api/v1/coupon-action.php
//
//   {"action":"create","code":"SAVE10","type":"percent","value":"10", ...}
//   {"action":"edit","id":4,"value":"15","min_order":"20", ...}
//   {"action":"activate"|"deactivate","id":4}
//   {"action":"delete","id":4}
//
// The same four things products/coupon-action.php lets a vendor do. One file
// for all four, the way product-action.php handles its five: they share the
// ownership check and differ by one statement.
//
// Which business: the alphabetically-first sellable one, exactly as the website
// picks it (ORDER BY name ASC LIMIT 1). The coupons tab has no business picker,
// so neither does this.
//
// Where this deliberately differs from the website:
//
//   errors      coupon-action.php lumps every bad field into one sentence and
//               redirects. A form with no browser in front of it has to say
//               which box is wrong, so this returns 422 with a field-keyed
//               'fields' object — the same shape product-save.php uses.
//   code length the website caps the code at 32 only in the HTML maxlength,
//               which a request that is not a browser never sees. The column is
//               VARCHAR(32), so a longer code is checked here instead of left
//               to MySQL to truncate.
//   dates       a coupon that expires before it starts is dead the moment it is
//               made. The website accepts that pair; this refuses it.
//   toggle      an explicit activate/deactivate rather than the website's
//               `active = 1 - active` flip. A flip needs the caller and the row
//               to agree on the current state, and two taps arriving together
//               would cancel out and both report success. Same reasoning as
//               product-action.php's show/hide.
require __DIR__ . '/../../config/api.php';

api_require_method('POST');
$vendor = api_require_vendor($pdo);
$userId = (int)$vendor['id'];

$body   = api_body();
$action = (string)($body['action'] ?? '');

$allowed = ['create', 'edit', 'activate', 'deactivate', 'delete'];
if (!in_array($action, $allowed, true)) {
    api_json(['error' => 'bad_action', 'allowed' => $allowed], 400);
}

$bizStmt = $pdo->prepare('
    SELECT id FROM businesses
     WHERE user_id = ? AND approved = 1 AND suspended = 0
     ORDER BY name ASC LIMIT 1
');
$bizStmt->execute([$userId]);
$businessId = (int)$bizStmt->fetchColumn();

if ($businessId <= 0) api_json(['error' => 'no_business'], 409);

// ── Shared field reading ─────────────────────────────────────────────
// Only create and edit send these. Both write the same five columns, so the
// checks live in one place and the two branches differ by the code and type.

$fields = [];

// A date box that was never filled in. Blank means "no start" and "no end",
// which the column stores as NULL — not as today.
function coupon_date(?string $raw, string $time, string $key, array &$fields): ?string {
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    // A phone's date picker cannot produce anything else, but this endpoint is
    // not only ever called by a phone.
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        $fields[$key] = 'Use a date like 2026-01-31.';
        return null;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $raw));
    if (!checkdate($m, $d, $y)) {
        $fields[$key] = 'That date does not exist.';
        return null;
    }
    // Starts at the beginning of the chosen day, expires at the end of it, so a
    // coupon is good through the whole calendar day picked. Same normalisation
    // the website does.
    return $raw . ' ' . $time;
}

if ($action === 'create' || $action === 'edit') {
    $minOrderRaw = $body['min_order'] ?? '';
    $maxUsesRaw  = $body['max_uses']  ?? '';
    $valueRaw    = $body['value']     ?? '';

    // is_numeric, not (float). (float)'abc' is 0.0, which here would read as a
    // value of zero and be refused for the wrong reason.
    if (!is_numeric($valueRaw))      { $fields['value'] = 'Enter a number.'; $value = 0; }
    elseif ((float)$valueRaw <= 0)   { $fields['value'] = 'The discount has to be more than zero.'; $value = 0; }
    else                             { $value = round((float)$valueRaw, 2); }

    if ($minOrderRaw === '' || $minOrderRaw === null) { $minOrder = 0.0; }
    elseif (!is_numeric($minOrderRaw))                { $fields['min_order'] = 'Enter a number, or leave it blank.'; $minOrder = 0.0; }
    elseif ((float)$minOrderRaw < 0)                  { $fields['min_order'] = 'That cannot be negative.'; $minOrder = 0.0; }
    else                                              { $minOrder = round((float)$minOrderRaw, 2); }

    // Blank means no limit, which the column stores as NULL.
    if ($maxUsesRaw === '' || $maxUsesRaw === null) { $maxUses = null; }
    elseif (!ctype_digit((string)$maxUsesRaw))      { $fields['max_uses'] = 'Enter a whole number, or leave it blank.'; $maxUses = null; }
    elseif ((int)$maxUsesRaw < 1)                   { $fields['max_uses'] = 'A limit has to be at least 1.'; $maxUses = null; }
    else                                            { $maxUses = (int)$maxUsesRaw; }

    $startsAt  = coupon_date($body['starts_at']  ?? '', '00:00:00', 'starts_at',  $fields);
    $expiresAt = coupon_date($body['expires_at'] ?? '', '23:59:59', 'expires_at', $fields);

    if ($startsAt && $expiresAt && strtotime($expiresAt) < strtotime($startsAt)) {
        $fields['expires_at'] = 'The end date is before the start date.';
    }
}

// ── create ───────────────────────────────────────────────────────────
if ($action === 'create') {
    // Uppercased and stripped to letters and digits, so SAVE 10 and save-10 are
    // the same code — a buyer typing it out of a message should not be able to
    // miss by a space. Same normalisation as the website.
    $code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim((string)($body['code'] ?? ''))));
    $type = ($body['type'] ?? '') === 'fixed' ? 'fixed' : 'percent';

    if ($code === '')            { $fields['code'] = 'Enter a code.'; }
    elseif (strlen($code) > 32)  { $fields['code'] = 'That code is too long — 32 letters and numbers at most.'; }

    if ($type === 'percent' && isset($value) && $value > 100) {
        $fields['value'] = 'A percentage cannot be more than 100.';
    }

    if ($fields) api_json(['error' => 'invalid', 'fields' => $fields], 422);

    try {
        $pdo->prepare('
            INSERT INTO coupons (code, business_id, type, value, min_order, max_uses, starts_at, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([$code, $businessId, $type, $value, $minOrder, $maxUses, $startsAt, $expiresAt]);
    } catch (PDOException $e) {
        // `code` is unique across the whole table, not per business, so the
        // clash may be with another vendor's coupon or with an admin sitewide
        // one. Either way the vendor can only pick a different code, so the
        // message does not say whose it is.
        if ($e->getCode() === '23000') {
            api_json(['error' => 'invalid', 'fields' => ['code' => 'That code is already taken.']], 422);
        }
        throw $e;
    }

    api_json(['ok' => true, 'action' => 'create', 'id' => (int)$pdo->lastInsertId(), 'code' => $code]);
}

// ── everything below acts on a coupon that already exists ────────────
$id = (int)($body['id'] ?? 0);
if ($id <= 0) api_json(['error' => 'missing_id'], 400);

$look = $pdo->prepare('
    SELECT id, code, type, used_count, expires_at, active
      FROM coupons
     WHERE id = ? AND business_id = ?
');
$look->execute([$id, $businessId]);
$existing = $look->fetch();

// A coupon belonging to another vendor, or a sitewide one with no business, is
// told the same thing as one that never existed.
if (!$existing) api_json(['error' => 'not_found'], 404);

$isExpired = $existing['expires_at'] && strtotime($existing['expires_at']) < time();

if ($action === 'edit') {
    // The website refuses to edit an expired coupon, and so does this. Changing
    // the end date of something already over would quietly bring it back to
    // life for buyers who still have the code.
    if ($isExpired) api_json(['error' => 'expired'], 409);

    // The type is fixed once the coupon exists — the website has no control for
    // it on an existing row. Taking the caller's word would turn "$10 off" into
    // "10% off" on an order that had already been quoted.
    if ($existing['type'] === 'percent' && isset($value) && $value > 100) {
        $fields['value'] = 'A percentage cannot be more than 100.';
    }

    if ($fields) api_json(['error' => 'invalid', 'fields' => $fields], 422);

    $pdo->prepare('
        UPDATE coupons
           SET value = ?, min_order = ?, max_uses = ?, starts_at = ?, expires_at = ?
         WHERE id = ? AND business_id = ?
    ')->execute([$value, $minOrder, $maxUses, $startsAt, $expiresAt, $id, $businessId]);

    api_json(['ok' => true, 'action' => 'edit', 'id' => $id]);
}

if ($action === 'activate' || $action === 'deactivate') {
    // Switching an expired coupon back on would do nothing a buyer could use —
    // checkout checks the end date as well — so it is refused rather than left
    // to look as though it worked.
    if ($isExpired) api_json(['error' => 'expired'], 409);

    $active = $action === 'activate' ? 1 : 0;
    $pdo->prepare('UPDATE coupons SET active = ? WHERE id = ? AND business_id = ?')
        ->execute([$active, $id, $businessId]);

    api_json(['ok' => true, 'action' => $action, 'id' => $id, 'active' => $active === 1]);
}

// ── delete ───────────────────────────────────────────────────────────
// used_count in the WHERE rather than an if above it: coupon_uses rows cascade
// from this table, so deleting a coupon somebody has already redeemed would
// take the discount off an order that was placed with it. A used coupon is
// deactivated instead, which is what the website tells the vendor too.
$del = $pdo->prepare('DELETE FROM coupons WHERE id = ? AND business_id = ? AND used_count = 0');
$del->execute([$id, $businessId]);

if ($del->rowCount() === 0) {
    api_json(['error' => 'in_use', 'used_count' => (int)$existing['used_count']], 409);
}

api_json(['ok' => true, 'action' => 'delete', 'id' => $id, 'deleted' => true]);
