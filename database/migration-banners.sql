CREATE TABLE IF NOT EXISTS banners (
    id             INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    title          VARCHAR(150)    NULL,
    subtitle       VARCHAR(255)    NULL,
    link_url       VARCHAR(500)    NULL,
    image_filename VARCHAR(255)    NOT NULL,
    sort_order     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    active         TINYINT(1)      NOT NULL DEFAULT 1,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP
);
