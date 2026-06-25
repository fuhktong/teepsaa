CREATE TABLE IF NOT EXISTS product_option_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    name VARCHAR(64) NOT NULL,
    display_order TINYINT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_option_values (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    option_type_id INT UNSIGNED NOT NULL,
    label VARCHAR(64) NOT NULL,
    display_order TINYINT DEFAULT 0,
    FOREIGN KEY (option_type_id) REFERENCES product_option_types(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Maps each variant (combination) to its selected option values
CREATE TABLE IF NOT EXISTS product_variant_options (
    variant_id INT UNSIGNED NOT NULL,
    option_value_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (variant_id, option_value_id),
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    FOREIGN KEY (option_value_id) REFERENCES product_option_values(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
