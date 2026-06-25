CREATE TABLE IF NOT EXISTS support_threads (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id   INT UNSIGNED NOT NULL,
    sender_role ENUM('buyer', 'vendor') NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    status      ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sender (sender_id, sender_role),
    INDEX idx_updated (updated_at)
);

CREATE TABLE IF NOT EXISTS support_messages (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    thread_id  INT UNSIGNED NOT NULL,
    sender     ENUM('buyer', 'vendor', 'admin') NOT NULL,
    body       TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at    DATETIME NULL,
    FOREIGN KEY (thread_id) REFERENCES support_threads(id) ON DELETE CASCADE,
    INDEX idx_thread (thread_id),
    INDEX idx_unread (thread_id, sender, read_at)
);
