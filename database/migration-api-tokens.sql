-- Teepsaa — Bearer tokens for the mobile apps
-- Run once.
--
-- The website authenticates with a session cookie set to SameSite=Strict. A
-- bundled Capacitor app runs from its own origin (https://localhost on Android,
-- capacitor://localhost on iOS), so the phone will not send that cookie. The
-- apps carry a token instead: minted at login, sent as
-- "Authorization: Bearer <token>" on every request, resolved by
-- api_require_vendor() in config/api.php.
--
-- Only a SHA-256 hash of the token is stored, never the token itself — the same
-- reasoning as a password column. The plaintext exists in exactly two places:
-- the reply to the login call, and the phone's own storage. If this table ever
-- leaks, every row in it is useless to the attacker.
--
-- No foreign key on user_id, and that is deliberate rather than an oversight.
-- buyers and vendors are separate tables with independent id sequences, so
-- user_id means "row 12 of whichever table `role` names" — a reference MySQL
-- has no way to express. Two consequences follow:
--   - `role` is half the key, never decoration. Every lookup must match on both
--     columns; matching on token_hash alone would let a buyer's token resolve
--     against a vendor account of the same id.
--   - Nothing cascades on delete. In practice the site only ever soft-deletes
--     (vendors.deleted_at), and api_require_vendor() re-checks deleted_at,
--     suspended and email_verified_at on every call, so access stops on the
--     next request. A genuine hard delete would need its tokens cleared by hand.

CREATE TABLE IF NOT EXISTS api_tokens (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    role         ENUM('buyer','vendor') NOT NULL,
    -- SHA-256 in hex is always exactly 64 characters. UNIQUE both enforces that
    -- two tokens can never collide and gives the lookup its index — this column
    -- is matched on every single API request, so it has to be the fast path.
    token_hash   CHAR(64) NOT NULL,
    -- Shown on a future "signed-in devices" screen so a vendor can recognise
    -- and revoke one. Free text from the app, so it is never trusted or matched
    -- on — display only, and escaped at render like any other user input.
    device_name  VARCHAR(120) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Written at most once every 5 minutes by api_require_vendor(), not on
    -- every call — the value only needs to be good enough to show "last used"
    -- and to prune abandoned tokens later.
    last_used_at DATETIME NULL,
    UNIQUE KEY uniq_token_hash (token_hash),
    -- Listing or revoking everything belonging to one account: "all of this
    -- vendor's devices". Ordered (user_id, role) because user_id alone is
    -- ambiguous across the two tables.
    KEY idx_owner (user_id, role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
