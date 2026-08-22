-- Teepsaa — Canvassing (vendor prospects)
-- Run once.
--
-- A prospect is a shop you have walked into, not an account. It deliberately
-- lives apart from vendors/businesses: most prospects never sign up, and
-- folding them into `businesses` would pollute every vendor query on the site.
-- When one does sign up, converted_vendor_id links the two.

CREATE TABLE IF NOT EXISTS prospects (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_name       VARCHAR(160) NOT NULL,
    business_name_km    VARCHAR(160) NULL,
    owner_name          VARCHAR(120) NULL,
    phone               VARCHAR(30)  NULL,
    telegram            VARCHAR(60)  NULL,
    category            VARCHAR(80)  NULL,
    address             VARCHAR(255) NULL,
    lat                 DECIMAL(10,7) NULL,
    lng                 DECIMAL(10,7) NULL,
    status              ENUM('to_visit','pitched','interested','signed_up','not_interested','closed_down')
                        NOT NULL DEFAULT 'to_visit',
    next_followup_at    DATE NULL,
    notes               TEXT NULL,
    converted_vendor_id INT UNSIGNED NULL,
    created_by          INT UNSIGNED NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    KEY idx_followup (next_followup_at),
    KEY idx_geo (lat, lng)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per time you walked in. The prospect's `status` is the latest
-- outcome; this is the history behind it ("12 Aug — owner not in, come back
-- mornings"), which a single status column can't hold.
CREATE TABLE IF NOT EXISTS prospect_visits (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prospect_id INT UNSIGNED NOT NULL,
    admin_id    INT UNSIGNED NULL,
    visited_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    outcome     ENUM('to_visit','pitched','interested','signed_up','not_interested','closed_down') NOT NULL,
    note        TEXT NULL,
    -- Where you were standing, not where the shop is — useful for spotting a
    -- pin that was dropped from the wrong side of town.
    lat         DECIMAL(10,7) NULL,
    lng         DECIMAL(10,7) NULL,
    KEY idx_prospect (prospect_id, visited_at),
    CONSTRAINT fk_pvisit_prospect FOREIGN KEY (prospect_id)
        REFERENCES prospects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prospect_photos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prospect_id INT UNSIGNED NOT NULL,
    visit_id    INT UNSIGNED NULL,
    filename    VARCHAR(120) NOT NULL,
    caption     VARCHAR(160) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_prospect (prospect_id, id),
    CONSTRAINT fk_pphoto_prospect FOREIGN KEY (prospect_id)
        REFERENCES prospects(id) ON DELETE CASCADE,
    CONSTRAINT fk_pphoto_visit FOREIGN KEY (visit_id)
        REFERENCES prospect_visits(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
