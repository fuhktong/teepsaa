CREATE TABLE vendor_penalties (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id  INT UNSIGNED NOT NULL,
    rate_increase DECIMAL(5,4) NOT NULL,
    admin_note   TEXT NULL,
    start_date   DATE NOT NULL,
    end_date     DATE NULL,
    created_by   INT UNSIGNED NOT NULL,
    cleared_at   DATETIME NULL,
    notified_at  DATETIME NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_penalties_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vendor_notifications (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_user_id INT UNSIGNED NOT NULL,
    message        TEXT NOT NULL,
    read_at        DATETIME NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_vendor FOREIGN KEY (vendor_user_id) REFERENCES vendors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
