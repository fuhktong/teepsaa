ALTER TABLE categories
    ADD COLUMN parent_id INT UNSIGNED NULL AFTER id,
    ADD CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL;

ALTER TABLE categories
    MODIFY COLUMN royalty_rate DECIMAL(5,4) NOT NULL DEFAULT 0.0500;
