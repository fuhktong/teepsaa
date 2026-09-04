-- Rate limiting: separate the flows, and limit per account as well as per IP.
--
-- Before this, every throttled endpoint wrote to one bucket keyed on IP alone,
-- so five job applications locked that address out of logging in for 15 minutes.
-- `kind` gives each flow its own budget; `identifier` (the email or user being
-- tried) is what actually catches a targeted brute force, and keeps working when
-- a proxy or NAT puts many people behind one address.
--
-- Existing rows all came from the login/reset/apply mix and are pruned within
-- 15 minutes anyway, so defaulting them to 'login' costs nothing.

ALTER TABLE login_attempts
    ADD COLUMN kind       VARCHAR(20)  NOT NULL DEFAULT 'login' AFTER ip,
    ADD COLUMN identifier VARCHAR(191) NOT NULL DEFAULT ''       AFTER kind,
    ADD INDEX idx_kind_ip_time (kind, ip, attempted_at),
    ADD INDEX idx_kind_id_time (kind, identifier, attempted_at);

-- idx_ip_time is now covered by idx_kind_ip_time.
ALTER TABLE login_attempts DROP INDEX idx_ip_time;
