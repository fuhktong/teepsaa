ALTER TABLE admins
    MODIFY COLUMN admin_role ENUM('super', 'custom') NOT NULL DEFAULT 'super';

CREATE TABLE IF NOT EXISTS admin_permissions (
    admin_id INT UNSIGNED NOT NULL,
    section  VARCHAR(30) NOT NULL,
    PRIMARY KEY (admin_id, section),
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
);
