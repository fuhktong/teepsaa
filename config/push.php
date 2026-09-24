<?php
// Sending a notification to a phone, through Firebase Cloud Messaging.
//
// Shaped deliberately like send_email() in mail.php, and for the same reason:
// every caller is in the middle of doing something else — taking payment,
// dispatching an order, running cron — and none of them can afford to care
// whether Google answered. So send_push() returns void, never throws, and
// writes its failures to push.log the way send_email() writes to mail.log. A
// dead phone must never cost a vendor a sale.
//
// It is called from exactly one place, notify() in notify.php, which is the
// single funnel every notification on the site already passes through. There
// is no list of events here and there never should be: add a notify() call
// anywhere and it pushes for free.
//
// The credential is a Google service account key — the JSON file downloaded
// from Firebase → Project settings → Service accounts. It is a password in
// file form: anyone holding it can send notifications to every teepsaa app
// install. It lives only on the server, is in .gitignore, is excluded from
// deploy-sftp.sh alongside config/smtp.php, and sits in config/ which is
// "Deny from all". With the file absent — every developer machine — send_push()
// logs what it would have sent and returns, so local work is unaffected.

require_once __DIR__ . '/notify.php';

const FCM_KEY_FILE = __DIR__ . '/fcm-service-account.json';

// Google's OAuth token endpoint and the scope FCM wants. Both are fixed.
const FCM_TOKEN_URL = 'https://oauth2.googleapis.com/token';
const FCM_SCOPE     = 'https://www.googleapis.com/auth/firebase.messaging';

// Tight on purpose. notify() runs inside ordinary page requests — a buyer
// confirming checkout is waiting on it — so a slow or unreachable Google must
// cost that buyer a couple of seconds, not thirty. A push that times out is
// lost, and that is the right trade: the notification row is already committed
// and the vendor sees it in the app's bell either way.
const FCM_TIMEOUT = 4;

if (!function_exists('send_push')) {

/**
 * Ding every phone belonging to one account.
 *
 * Mirrors notify()'s arguments because notify() is the only caller: $message is
 * the stored English fallback and $data the parts needed to re-render it, so
 * the text each phone receives is built here in that phone's own language.
 */
function send_push(PDO $pdo, string $role, int $userId, string $type, string $message, ?string $link = null, ?array $data = null): void {
    try {
        $rows = $pdo->prepare('SELECT id, fcm_token, lang FROM device_tokens WHERE role = ? AND user_id = ?');
        $rows->execute([$role, $userId]);
        $devices = $rows->fetchAll(PDO::FETCH_ASSOC);
        if (!$devices) return;

        // No key on this machine: say what would have been sent and stop. Same
        // contract as send_email() with an empty SMTP_PASS.
        if (!is_readable(FCM_KEY_FILE)) {
            push_log(sprintf("WOULD SEND to %s#%d (%d device(s))\nTYPE: %s\nTEXT: %s\nLINK: %s",
                $role, $userId, count($devices), $type, $message, $link ?? '-'));
            return;
        }

        $key = json_decode((string)file_get_contents(FCM_KEY_FILE), true);
        if (!is_array($key) || empty($key['project_id']) || empty($key['private_key']) || empty($key['client_email'])) {
            push_log('KEY FILE UNREADABLE OR INCOMPLETE: ' . FCM_KEY_FILE);
            return;
        }

        $access = fcm_access_token($key);
        if ($access === '') return;   // already logged

        $url = 'https://fcm.googleapis.com/v1/projects/' . $key['project_id'] . '/messages:send';

        foreach ($devices as $device) {
            $body = push_text($type, $message, $data, $device['lang']);
            fcm_send_one($pdo, $url, $access, $device, $body, $link, $type);
        }
    } catch (Throwable $e) {
        // Including, in particular, "table device_tokens doesn't exist" on a
        // server where the migration has not been applied yet. notify() must
        // still insert its row.
        push_log('SEND FAILED: ' . $e->getMessage());
    }
}

/**
 * The sentence the phone shows, in that phone's language.
 *
 * notification_text() is the same function the website's bell and the app's
 * notifications screen use, so a push can never word an event differently from
 * the list it appears in two seconds later.
 */
function push_text(string $type, string $message, ?array $data, string $lang): string {
    static $dicts = [];
    if (!isset($dicts[$lang])) {
        $file = __DIR__ . '/../lang/' . ($lang === 'km' ? 'km' : 'en') . '.php';
        $dicts[$lang] = is_readable($file) ? (require $file) : [];
    }
    // notification_text() reads a database row, so hand it one in that shape
    // rather than teaching it a second set of arguments.
    return notification_text([
        'type'    => $type,
        'message' => $message,
        'data'    => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
    ], $dicts[$lang]);
}

/**
 * A Google access token, good for an hour.
 *
 * Minting one is a signature plus a round trip to Google, which is far too
 * much to repeat for every notification, so the result is cached in a file
 * under the system temp directory. The cache holds an access token, not the
 * key — it is already short-lived, and the private key never leaves config/.
 *
 * Returns '' on any failure, having logged it. The caller stops.
 */
function fcm_access_token(array $key): string {
    $cache = sys_get_temp_dir() . '/teepsaa-fcm-' . md5($key['client_email']) . '.json';

    // 60 seconds of slack so a token cannot expire in flight.
    if (is_readable($cache)) {
        $hit = json_decode((string)file_get_contents($cache), true);
        if (is_array($hit) && ($hit['expires'] ?? 0) > time() + 60 && !empty($hit['token'])) {
            return (string)$hit['token'];
        }
    }

    $now    = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $claims = [
        'iss'   => $key['client_email'],
        'scope' => FCM_SCOPE,
        'aud'   => FCM_TOKEN_URL,
        'iat'   => $now,
        'exp'   => $now + 3600,
    ];

    $signing = base64url(json_encode($header)) . '.' . base64url(json_encode($claims));
    $sig     = '';
    if (!openssl_sign($signing, $sig, $key['private_key'], OPENSSL_ALGO_SHA256)) {
        push_log('JWT SIGNING FAILED — is private_key intact in ' . FCM_KEY_FILE . '?');
        return '';
    }

    [$status, $reply] = http_post_form(FCM_TOKEN_URL, [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion'  => $signing . '.' . base64url($sig),
    ]);

    $json = json_decode((string)$reply, true);
    if ($status !== 200 || empty($json['access_token'])) {
        push_log("TOKEN REQUEST FAILED (HTTP $status): " . substr((string)$reply, 0, 400));
        return '';
    }

    // Written via a temp file and renamed so two requests minting at once
    // cannot leave a half-written cache behind for a third to read.
    $tmp = $cache . '.' . getmypid();
    @file_put_contents($tmp, json_encode([
        'token'   => $json['access_token'],
        'expires' => $now + (int)($json['expires_in'] ?? 3600),
    ]));
    @chmod($tmp, 0600);
    @rename($tmp, $cache);

    return (string)$json['access_token'];
}

/**
 * One phone. Deletes the row when Google says the token is dead.
 */
function fcm_send_one(PDO $pdo, string $url, string $access, array $device, string $body, ?string $link, string $type): void {
    $payload = [
        'message' => [
            'token'        => $device['fcm_token'],
            'notification' => ['title' => 'teepsaa', 'body' => $body],
            // Read by the app when the notification is tapped, to open the
            // right screen. Every value in an FCM data block must be a string;
            // numbers are rejected by the API, so `link` is sent as given and
            // `type` alongside it for screens that route on the event itself.
            'data'    => ['link' => (string)($link ?? ''), 'type' => $type],
            'android' => [
                // An order is worth waking the phone for; Android delays
                // normal-priority messages when the device is dozing.
                'priority'     => 'HIGH',
                'notification' => ['sound' => 'default'],
            ],
        ],
    ];

    [$status, $reply] = http_post_json($url, json_encode($payload, JSON_UNESCAPED_UNICODE), [
        'Authorization: Bearer ' . $access,
    ]);

    if ($status === 200) return;

    // UNREGISTERED means the app was uninstalled or the token rotated;
    // INVALID_ARGUMENT on a token means it was never valid. Either way the row
    // is junk and would otherwise be retried for every notification forever.
    $dead = $status === 404
        || ($status === 400 && str_contains((string)$reply, 'INVALID_ARGUMENT'));

    if ($dead) {
        $pdo->prepare('DELETE FROM device_tokens WHERE id = ?')->execute([$device['id']]);
        push_log("DEAD TOKEN REMOVED (id {$device['id']}, HTTP $status)");
        return;
    }

    push_log("PUSH FAILED (HTTP $status) for device {$device['id']}: " . substr((string)$reply, 0, 400));
}

// ── plumbing ────────────────────────────────────────────────────────────────

function base64url(string $raw): string {
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function http_post_form(string $url, array $fields): array {
    return curl_exchange($url, http_build_query($fields), ['Content-Type: application/x-www-form-urlencoded']);
}

function http_post_json(string $url, string $json, array $headers): array {
    return curl_exchange($url, $json, array_merge($headers, ['Content-Type: application/json']));
}

function curl_exchange(string $url, string $body, array $headers): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => FCM_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => FCM_TIMEOUT,
    ]);
    $reply  = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($reply === false) {
        push_log('CURL ERROR: ' . curl_error($ch));
        $reply = '';
    }
    curl_close($ch);
    return [$status, (string)$reply];
}

function push_log(string $line): void {
    @file_put_contents(
        __DIR__ . '/../push.log',
        sprintf("[%s] %s\n%s\n", date('Y-m-d H:i:s'), $line, str_repeat('-', 60)),
        FILE_APPEND | LOCK_EX
    );
}

}
