-- Teepsaa — one row per phone that has agreed to receive push notifications
-- Run once.
--
-- Firebase calls this a "registration token". It identifies an install of the
-- app on one device, not a person: reinstalling the app, clearing its data or
-- restoring a backup onto a new phone all produce a new one, and the old one
-- goes dead without telling us. Two rules follow from that and both are
-- enforced here rather than left to the callers.
--
--   - The same token can arrive again at any time. The app re-registers on
--     every login and whenever Firebase rotates it, so the endpoint upserts on
--     fcm_token instead of inserting. Without the UNIQUE key below, a vendor
--     who logs in fifty times gets fifty rows and fifty identical dings.
--
--   - A token can outlive the account it was registered under. A vendor logs
--     out, a second vendor logs in on the same phone, and that one token now
--     belongs to the new account — so the upsert overwrites user_id and role
--     rather than adding a row, and logout deletes the row outright. If it did
--     not, the first vendor's orders would ding on the second vendor's phone.
--
-- No foreign key on user_id, for the reason set out at length in
-- migration-api-tokens.sql: buyers and vendors are separate tables with
-- independent id sequences, so user_id only means anything alongside `role`.
-- Every lookup must match on both columns.
--
-- `role` is already here although only the vendor app exists today. The buyer
-- app is Chapter 16 of the bundler and will register tokens into this same
-- table; adding the column now costs nothing and avoids a migration then.

CREATE TABLE IF NOT EXISTS device_tokens (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    role        ENUM('buyer','vendor') NOT NULL,
    -- Firebase documents no maximum. Real tokens are around 160 characters and
    -- have been for years; 255 leaves room without pushing the UNIQUE index
    -- past what InnoDB allows for utf8mb4. If Google ever issues a longer one
    -- the insert fails loudly here rather than silently storing a truncated
    -- token that would never receive anything.
    fcm_token   VARCHAR(255) NOT NULL,
    platform    ENUM('android','ios') NOT NULL,
    -- Which language to write the notification in. It cannot be looked up from
    -- the account: the site keeps the language choice in the session
    -- (config/i18n.php), and a push is sent from a request belonging to someone
    -- else entirely — the buyer who placed the order, or cron, which has no
    -- session at all. So the phone states its own language when it registers
    -- and updates the row when the vendor changes it in Settings.
    lang        ENUM('en','km') NOT NULL DEFAULT 'en',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Refreshed by the register endpoint, not by sending. Firebase stops
    -- delivering to a token after a long silence, so a row that has not been
    -- seen for months is almost certainly dead weight and can be pruned.
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_fcm_token (fcm_token),
    -- The send path's only query: "every phone belonging to this account".
    KEY idx_owner (role, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
