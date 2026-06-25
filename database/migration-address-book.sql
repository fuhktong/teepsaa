CREATE TABLE buyer_addresses (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_user_id  INT UNSIGNED NOT NULL,
    label          VARCHAR(100) NOT NULL DEFAULT '',
    house_number   VARCHAR(50)  NULL DEFAULT NULL,
    address        VARCHAR(255) NULL DEFAULT NULL,
    address_notes  VARCHAR(255) NULL DEFAULT NULL,
    khan           VARCHAR(100) NULL DEFAULT NULL,
    sangkat        VARCHAR(100) NULL DEFAULT NULL,
    lat            DECIMAL(10,7) NULL DEFAULT NULL,
    lng            DECIMAL(10,7) NULL DEFAULT NULL,
    is_default     TINYINT(1) NOT NULL DEFAULT 0,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_user_id) REFERENCES buyers(id) ON DELETE CASCADE
);
