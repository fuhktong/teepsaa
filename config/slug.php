<?php

// Addresses that say what they point at.
//
// `/product/?id=8f14e45f-ab3c-4a3d-9f21-6d0c1b2a3e44` tells a person nothing
// and gives a search engine nothing to match a query against. This file turns
// it into `/product/silk-krama-scarf-8f14e45f-ab3c/`.
//
// The random ID stays. That was a deliberate decision — public addresses must
// not be guessable by counting upwards — and nothing here weakens it: the
// words are decoration, the ID is still what does the lookup. Only the first
// two blocks of the UUID travel in the address, which is 48 bits: across ten
// thousand listings the chance of any two sharing a prefix is about one in
// five million, and the lookup checks for it rather than assuming.
//
// Loaded from config/i18n.php so every page has it, the same reason subdomain
// routing loads there — config/db.php is unmanaged on the server and can't
// hold the require.

// How much of the UUID travels in the address. 13 characters is
// "8f14e45f-ab3c": two blocks and the dash between them. Left-anchored, so
// the LIKE lookup below is an index range scan, not a table sweep.
const PUBLIC_ID_TOKEN_LEN = 13;

// Words for a URL. Keeps ASCII letters and digits and the Khmer block, folds
// everything else to a single dash.
//
// Khmer is kept on purpose. Vendors type Khmer product names, and a Khmer
// address is worth more to a Khmer searcher than a stripped-empty one — the
// characters percent-encode on the wire and display as Khmer in the browser
// bar and in a search result. Callers get back a string that is already safe
// to drop into an href.
if (!function_exists('slugify')) {
    function slugify(string $text, int $maxLen = 60): string {
        $text = mb_strtolower(strip_tags($text), 'UTF-8');

        // Apostrophes vanish rather than becoming a separator, so "Men's"
        // is mens and "Kids' Shoes" is kids-shoes — not men-s and kids-shoes
        // sitting next to a stray dash.
        $text = str_replace(["'", "\u{2019}", "\u{2018}", '`', "\u{02BC}"], '', $text);

        // "Café" and "cafe" should not become two different addresses.
        $text = strtr($text, [
            'á'=>'a','à'=>'a','â'=>'a','ä'=>'a','ã'=>'a','å'=>'a','ā'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','ē'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','ī'=>'i',
            'ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o','ō'=>'o','ø'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ū'=>'u',
            'ñ'=>'n','ç'=>'c','ß'=>'ss','æ'=>'ae','œ'=>'oe',
        ]);

        // U+17D4-U+17DD is Khmer punctuation (។ ៕ ។ល។) — signs, not letters,
        // so they fold to a dash like a full stop does. U+17E0-U+17E9 are the
        // Khmer digits and stay.
        $text = preg_replace('/[^a-z0-9\x{1780}-\x{17D3}\x{17E0}-\x{17E9}]+/u', '-', $text);
        $text = trim((string)$text, '-');

        if (mb_strlen($text, 'UTF-8') > $maxLen) {
            $text = mb_substr($text, 0, $maxLen, 'UTF-8');
            // Don't end mid-word — but only if there's a word boundary to
            // fall back to, or a long Khmer name would truncate to nothing.
            $cut = mb_strrpos($text, '-', 0, 'UTF-8');
            if ($cut !== false && $cut > $maxLen / 2) $text = mb_substr($text, 0, $cut, 'UTF-8');
            $text = trim($text, '-');
        }
        return $text;
    }
}

// The part of a UUID that appears in an address.
if (!function_exists('public_id_token')) {
    function public_id_token(string $publicId): string {
        return substr($publicId, 0, PUBLIC_ID_TOKEN_LEN);
    }
}

// "/product/" + words + ID → the one canonical address for this row.
// $name is the English name on purpose: an address names a page, not a
// language, and both language versions of a product share one canonical.
if (!function_exists('pretty_path')) {
    function pretty_path(string $dir, string $publicId, string $name): string {
        $slug  = slugify($name);
        $token = public_id_token($publicId);
        $last  = $slug === '' ? $token : $slug . '-' . $token;
        // rawurlencode would eat the dashes' meaning nowhere but does encode
        // Khmer; the slug has no other reserved characters left in it.
        return '/' . trim($dir, '/') . '/' . rawurlencode($last) . '/';
    }
}

if (!function_exists('product_path')) {
    function product_path(array $p): string {
        return pretty_path('product', (string)$p['public_id'], (string)($p['name'] ?? ''));
    }
}

if (!function_exists('business_path')) {
    function business_path(array $b): string {
        return pretty_path('business', (string)$b['public_id'], (string)($b['name'] ?? ''));
    }
}

// Splits "silk-krama-scarf-8f14e45f-ab3c" back into its ID token. Reads from
// the right so a slug containing dashes (all of them) is no obstacle, and
// insists on the exact UUID shape so an ordinary word can't be mistaken for
// an ID.
if (!function_exists('token_from_slug')) {
    function token_from_slug(string $slugWithToken): string {
        $s = rawurldecode($slugWithToken);
        return preg_match('/([0-9a-f]{8}-[0-9a-f]{4})$/i', $s, $m) ? strtolower($m[1]) : '';
    }
}
