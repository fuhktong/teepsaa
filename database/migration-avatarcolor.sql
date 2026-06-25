-- Avatar support for admin + avatar color selection for all roles
ALTER TABLE admins  ADD COLUMN avatar       VARCHAR(120)     NULL DEFAULT NULL;
ALTER TABLE admins  ADD COLUMN avatar_color TINYINT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE vendors ADD COLUMN avatar_color TINYINT UNSIGNED NULL DEFAULT NULL;
ALTER TABLE buyers  ADD COLUMN avatar_color TINYINT UNSIGNED NULL DEFAULT NULL;
