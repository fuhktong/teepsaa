<?php

// Self-dealing detection — is the buyer on this order also the vendor being
// paid for it?
//
// Advisory only. Nothing here blocks an order: a vendor legitimately buying
// from a neighbouring shop, or two family members running one storefront, are
// normal in a small marketplace. The point is that money should never leave
// for what is plainly someone's own shop *without anyone noticing*, which is
// the shape the Amazon vendor-fraud case took. The flags are stored on the
// order and shown on the payout screen.

// Free mailbox providers. A shared domain here means nothing — half the
// country is on Gmail — so it is excluded from the domain-match rule to keep
// the signal worth reading.
const SELF_DEAL_PUBLIC_DOMAINS = [
    'gmail.com', 'googlemail.com', 'yahoo.com', 'yahoo.com.kh', 'hotmail.com',
    'outlook.com', 'live.com', 'icloud.com', 'me.com', 'aol.com', 'proton.me',
    'protonmail.com', 'mail.com', 'yandex.com', 'zoho.com',
];

const SELF_DEAL_LABELS = [
    'same_email'        => 'Buyer and vendor use the same email address',
    'same_email_domain' => 'Buyer and vendor share a private email domain',
    'same_phone'        => 'Buyer and vendor share a phone number',
    'same_address'      => 'Delivery address matches the vendor’s business address',
];

function self_deal_label(string $code): string {
    return SELF_DEAL_LABELS[$code] ?? $code;
}

// Digits only, so "012 345 678", "012-345-678" and "+855 12 345 678" compare
// equal on their last 8 digits (Cambodian subscriber number length).
function _sd_phone(?string $phone): string {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    return strlen($digits) >= 8 ? substr($digits, -8) : '';
}

// Lowercase, collapse whitespace and drop punctuation, so "No. 12, St. 271"
// and "no 12 st 271" compare equal.
function _sd_addr(array $parts): string {
    $joined = strtolower(implode(' ', array_filter(array_map('trim', $parts))));
    $joined = preg_replace('/[^a-z0-9\x{1780}-\x{17FF}]+/u', ' ', $joined);
    return trim(preg_replace('/\s+/', ' ', $joined));
}

function _sd_domain(?string $email): string {
    $parts = explode('@', strtolower(trim((string)$email)));
    return count($parts) === 2 ? $parts[1] : '';
}

// $buyer  needs: email, phone, address, khan, sangkat (house_number optional)
// $vendor needs: email, phone, biz_house_number, biz_address, biz_khan, biz_sangkat
// Returns a list of reason codes, empty when nothing matches.
function self_deal_flags(array $buyer, array $vendor): array {
    $flags = [];

    $buyerEmail  = strtolower(trim((string)($buyer['email']  ?? '')));
    $vendorEmail = strtolower(trim((string)($vendor['email'] ?? '')));

    if ($buyerEmail !== '' && $buyerEmail === $vendorEmail) {
        $flags[] = 'same_email';
    } else {
        $bd = _sd_domain($buyerEmail);
        $vd = _sd_domain($vendorEmail);
        if ($bd !== '' && $bd === $vd && !in_array($bd, SELF_DEAL_PUBLIC_DOMAINS, true)) {
            $flags[] = 'same_email_domain';
        }
    }

    $bp = _sd_phone($buyer['phone']  ?? null);
    $vp = _sd_phone($vendor['phone'] ?? null);
    if ($bp !== '' && $bp === $vp) {
        $flags[] = 'same_phone';
    }

    $buyerAddr = _sd_addr([
        $buyer['house_number'] ?? '', $buyer['address'] ?? '',
        $buyer['sangkat'] ?? '', $buyer['khan'] ?? '',
    ]);
    $bizAddr = _sd_addr([
        $vendor['biz_house_number'] ?? '', $vendor['biz_address'] ?? '',
        $vendor['biz_sangkat'] ?? '', $vendor['biz_khan'] ?? '',
    ]);
    // Require some substance — two blank-ish addresses must not match.
    if ($buyerAddr !== '' && strlen($buyerAddr) > 6 && $buyerAddr === $bizAddr) {
        $flags[] = 'same_address';
    }

    return $flags;
}
