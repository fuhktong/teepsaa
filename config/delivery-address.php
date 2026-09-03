<?php
// One rule for what makes a buyer deliverable-to, shared by the address book
// (so an incomplete address can never be saved), the cart, and checkout (so a
// buyer whose address predates the rule is sent back to fix it). Keeping the
// list in one place is what stops the three from disagreeing.
//
// Field keys: street, khan, sangkat, pin, phone.

// The action scripts don't include the header, so they have no $t of their own
function delivery_lang(): array
{
    static $t = null;
    if ($t === null) {
        $lang = $_SESSION['lang'] ?? 'km';
        $t    = require __DIR__ . '/../lang/' . (in_array($lang, ['en', 'km'], true) ? $lang : 'en') . '.php';
    }
    return $t;
}

// What a saved address needs. House number, floor/notes and label stay
// optional — the pin carries the precision, and the label falls back to khan.
function address_missing_fields(?string $address, ?string $khan, ?string $sangkat, $lat, $lng): array
{
    $missing = [];
    if (!$address) { $missing[] = 'street'; }
    if (!$khan)    { $missing[] = 'khan'; }
    if (!$sangkat) { $missing[] = 'sangkat'; }
    if ($lat === null || $lat === '' || $lng === null || $lng === '') { $missing[] = 'pin'; }
    return $missing;
}

// Same check against a buyers-table row, plus the phone the driver calls on
function buyer_missing_fields(array $buyer): array
{
    $missing = address_missing_fields(
        $buyer['address'] ?? null,
        $buyer['khan']    ?? null,
        $buyer['sangkat'] ?? null,
        $buyer['lat']     ?? null,
        $buyer['lng']     ?? null
    );
    if (empty($buyer['phone'])) { $missing[] = 'phone'; }
    return $missing;
}

// "street, sangkat and map pin" in the buyer's language. Phone lives on the
// Account tab rather than the address form, so it says where to find it
// whenever the buyer is being sent to the address tab for something else.
function missing_fields_list(array $keys, bool $phoneOnOtherTab = false): string
{
    $t     = delivery_lang();
    $names = [
        'street'  => $t['missing_street'],
        'khan'    => $t['missing_khan'],
        'sangkat' => $t['missing_sangkat'],
        'pin'     => $t['missing_pin'],
        'phone'   => $phoneOnOtherTab ? $t['missing_phone_other_tab'] : $t['missing_phone'],
    ];

    $parts = [];
    foreach ($keys as $k) {
        if (isset($names[$k])) { $parts[] = $names[$k]; }
    }

    if (count($parts) <= 1) { return $parts[0] ?? ''; }
    $last = array_pop($parts);
    return implode(', ', $parts) . ' ' . $t['missing_and'] . ' ' . $last;
}

// Builds the whole "you can't check out yet" sentence and the settings URL
// that lands the buyer on the right tab with the right form already open.
// $context is 'cart' or 'checkout'. Returns [message, redirect url].
function missing_fields_prompt(array $keys, string $context, ?int $defaultAddressId = null): array
{
    $t         = delivery_lang();
    $onlyPhone = $keys === ['phone'];

    $message = sprintf(
        $context === 'cart' ? $t['missing_intro_cart'] : $t['missing_intro_checkout'],
        missing_fields_list($keys, !$onlyPhone)
    );

    if ($onlyPhone) {
        return [$message, '/settings-buyer/?tab=account'];
    }

    // Point at the address that is actually incomplete — the default one, which
    // is what the buyers table mirrors — so the edit form opens prefilled
    $url = '/settings-buyer/?tab=address'
         . ($defaultAddressId ? '&edit=' . $defaultAddressId : '')
         . '&fix=' . implode(',', array_diff($keys, ['phone']));

    return [$message, $url];
}

// The default address is the one mirrored onto the buyers table, so it is the
// one a buyer has to fix
function buyer_default_address_id(PDO $pdo, int $userId): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM buyer_addresses WHERE buyer_user_id = ? AND is_default = 1');
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    return $id ? (int)$id : null;
}
