<?php
// Shared helper for the mobile apps' endpoints under /api/v1/. Every endpoint
// there requires this file and nothing else — it pulls in db.php itself.
//
// Why these endpoints exist at all: the website authenticates with a session
// cookie set to SameSite=Strict. A bundled Capacitor app runs from its own
// origin (https://localhost on Android, capacitor://localhost on iOS), so the
// phone will not send that cookie. Auth here is a bearer token instead.
//
// Two rules follow from there, and they are the reason this file does NOT look
// like the rest of the site:
//   - No session_start(). There is no session; everything comes from the token.
//   - No CSRF check. CSRF is an attack on ambient cookie authority — a browser
//     attaching a cookie to a request the user did not mean to make. No cookie
//     is sent here, so there is nothing to forge. A CSRF token would only be
//     theatre, and it cannot work anyway: minting one needs the session.
//
// What does NOT change: every other check the equivalent website page performs
// — ownership, required fields, limits, rate limiting — must be repeated in the
// endpoint. An endpoint is a form with no browser in front of it.

require_once __DIR__ . '/db.php';

// ── 1. CORS ──────────────────────────────────────────────────────────
//
// A browser (and the phone's WebView) refuses a cross-origin call unless the
// server names the caller back. Exactly three origins, listed literally — never
// '*', and never anything derived from the request. The website's own pages are
// same-origin and never reach this list.
const API_ALLOWED_ORIGINS = [
    'https://localhost',        // the Android app
    'capacitor://localhost',    // the iPhone app
    'http://localhost:5173',    // npm run dev, in the Mac browser
];

$apiOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($apiOrigin !== '' && in_array($apiOrigin, API_ALLOWED_ORIGINS, true)) {
    header('Access-Control-Allow-Origin: ' . $apiOrigin);
}
// The reply differs per origin, so a cache must key on it. Sent even when the
// origin was rejected, so a rejected reply is never stored and replayed to an
// allowed one.
header('Vary: Origin');

// No Access-Control-Allow-Credentials on purpose. Cookies are not used here,
// and switching it on would forbid the wildcard-free setup from ever being
// relaxed safely later.

// ── 2. The preflight check ───────────────────────────────────────────
//
// Before a request that carries an Authorization header, the browser sends an
// OPTIONS request asking permission. It expects headers and no body. Answer and
// stop — never let OPTIONS fall through into an endpoint's real work.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Max-Age: 86400');   // cache permission for a day
    http_response_code(204);
    exit;
}

// ── 3. Always reply in JSON ──────────────────────────────────────────

/**
 * Send $data as JSON and stop. Every exit from an endpoint goes through here,
 * so a caller never has to guess whether a reply is JSON or an HTML error page.
 */
function api_json(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * The request body, decoded. The app sends JSON, not form fields, so $_POST is
 * empty on these endpoints — read the raw body instead. Returns [] for an empty
 * or malformed body; endpoints validate their own fields either way.
 */
function api_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * A PHP warning or uncaught error would otherwise print HTML into the middle of
 * a JSON reply, and the app would report a parse error pointing nowhere near
 * the real fault. Convert both into a clean 500. DEV_MODE keeps the detail
 * local; production says nothing, since the message can name table columns.
 */
set_exception_handler(function (Throwable $e): void {
    error_log('api: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    api_json(['error' => 'server_error'] + (DEV_MODE ? ['detail' => $e->getMessage()] : []), 500);
});

// ── 4. The token ─────────────────────────────────────────────────────

/**
 * The bearer token from the Authorization header, or '' if absent.
 *
 * Apache does not always expose Authorization in $_SERVER — mod_rewrite strips
 * it unless it is explicitly passed through, which is why the REDIRECT_ copy and
 * apache_request_headers() are both checked. Without these fallbacks every call
 * would return 401 on the live server while working perfectly on localhost.
 */
function api_bearer_token(): string {
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if ($header === '' && function_exists('apache_request_headers')) {
        foreach (apache_request_headers() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) { $header = $value; break; }
        }
    }

    return preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m) ? $m[1] : '';
}

/**
 * Resolve the token to a vendor, or reply 401 and stop. Returns the vendor's
 * row (id, name, lang) — the id is what every ownership check downstream uses.
 *
 * The suspended / email_verified_at / deleted_at checks are repeated from
 * login-vendor.php deliberately. Login only proves the account was usable at
 * the moment the token was minted, and a token outlives that moment: an account
 * suspended or deleted afterwards must stop working on the next call, not
 * whenever the vendor happens to log in again.
 *
 * vendors and buyers are separate tables with their own id sequences, so
 * vendor 12 and buyer 12 are different people. The role column is half the
 * key, never decoration — matching on token alone would let a buyer's token
 * resolve to a vendor account.
 */
function api_require_vendor(PDO $pdo): array {
    $token = api_bearer_token();
    if ($token === '') api_json(['error' => 'unauthorized'], 401);

    $stmt = $pdo->prepare('
        SELECT t.id AS token_id, v.id, v.name, v.lang
          FROM api_tokens t
          JOIN vendors v ON v.id = t.user_id
         WHERE t.token_hash = ?
           AND t.role = ?
           AND v.deleted_at IS NULL
           AND v.suspended = 0
           AND v.email_verified_at IS NOT NULL
    ');
    $stmt->execute([hash('sha256', $token), 'vendor']);
    $row = $stmt->fetch();

    if (!$row) api_json(['error' => 'unauthorized'], 401);

    // Enough to show "last used" on a devices screen and to prune dead tokens,
    // without a write on every single request.
    $pdo->prepare('
        UPDATE api_tokens SET last_used_at = NOW()
         WHERE id = ?
           AND (last_used_at IS NULL OR last_used_at < NOW() - INTERVAL 5 MINUTE)
    ')->execute([$row['token_id']]);

    unset($row['token_id']);
    return $row;
}

/**
 * The buyer app's twin of api_require_vendor(): resolve the token to a buyer,
 * or reply 401 and stop. Same re-checks for the same reason — a token outlives
 * the moment it was minted — and the same role-as-half-the-key rule, the other
 * way round: a vendor's token must never resolve to a buyer.
 */
function api_require_buyer(PDO $pdo): array {
    $buyer = api_lookup_buyer($pdo);
    if (!$buyer) api_json(['error' => 'unauthorized'], 401);
    return $buyer;
}

/**
 * The buyer, or null — never a 401. For the public shop endpoints, which answer
 * anyone but add a little for a signed-in buyer (is this on their wishlist).
 *
 * A token that no longer works is treated as no token rather than refused.
 * Browsing must not break because a sign-in went stale; the app finds out the
 * next time it calls something that needs the account, and that 401 is what
 * clears the token.
 */
function api_optional_buyer(PDO $pdo): ?array {
    return api_bearer_token() === '' ? null : api_lookup_buyer($pdo);
}

function api_lookup_buyer(PDO $pdo): ?array {
    $token = api_bearer_token();
    if ($token === '') return null;

    $stmt = $pdo->prepare('
        SELECT t.id AS token_id, b.id, b.name, b.lang
          FROM api_tokens t
          JOIN buyers b ON b.id = t.user_id
         WHERE t.token_hash = ?
           AND t.role = ?
           AND b.deleted_at IS NULL
           AND b.suspended = 0
           AND b.email_verified_at IS NOT NULL
    ');
    $stmt->execute([hash('sha256', $token), 'buyer']);
    $row = $stmt->fetch();

    if (!$row) return null;

    $pdo->prepare('
        UPDATE api_tokens SET last_used_at = NOW()
         WHERE id = ?
           AND (last_used_at IS NULL OR last_used_at < NOW() - INTERVAL 5 MINUTE)
    ')->execute([$row['token_id']]);

    unset($row['token_id']);
    return $row;
}

/**
 * Reject anything that is not the method an endpoint expects. A GET arriving at
 * a write endpoint is a bug worth surfacing, not something to half-run.
 */
function api_require_method(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        api_json(['error' => 'method_not_allowed'], 405);
    }
}
