-- Private access tokens for guest (contact form) support threads.
-- The token gates /support-thread/?t=<token> — the guest's only way back
-- into the conversation (guests have no account; email is only a doorbell).
-- Run once in phpMyAdmin.
ALTER TABLE support_threads
    ADD COLUMN guest_token VARCHAR(64) NULL DEFAULT NULL AFTER guest_email,
    ADD UNIQUE KEY idx_guest_token (guest_token);
