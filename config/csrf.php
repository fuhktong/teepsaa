<?php
// Subdomain routing must also load on pages that use csrf.php without
// db.php (login/register/forgot-password portals, incl. login-admin).
require_once __DIR__ . '/subdomain.php';

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

// The check on its own. JSON endpoints need to reject with a JSON body, so
// they call this rather than csrf_verify(), which answers in plain text.
function csrf_valid(): bool {
    return is_string($_POST['csrf_token'] ?? null)
        && $_POST['csrf_token'] !== ''
        && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
}

function csrf_verify(): void {
    if (!csrf_valid()) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}
