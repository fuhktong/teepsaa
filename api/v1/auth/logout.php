<?php
// Deletes the token this request arrived with. Nothing else — one device signs
// out, any others the vendor has stay signed in.
//
// Note it does NOT call api_require_vendor(). Logging out is not a privileged
// action: destroying a token requires already holding it, and the phone is
// discarding its copy either way. Gating it would mean a vendor whose account
// was suspended since login could never clear the token off their device, and a
// failed logout would leave the app in the worse of the two possible states.
// So the reply is always ok, whether a row was deleted or the token was already
// gone — there is nothing useful the app could do differently.

require __DIR__ . '/../../../config/api.php';

api_require_method('POST');

$token = api_bearer_token();

if ($token !== '') {
    $pdo->prepare('DELETE FROM api_tokens WHERE token_hash = ? AND role = ?')
        ->execute([hash('sha256', $token), 'vendor']);
}

api_json(['ok' => true]);
