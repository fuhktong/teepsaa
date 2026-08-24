-- Teepsaa — a prospect can sell more than one thing.
-- Run once.
--
-- Same shape as businesses.category: category *names*, comma separated, so a
-- prospect that signs up converts across without translation. 80 characters
-- fitted a single name; three or four need the room.
ALTER TABLE prospects
    MODIFY COLUMN category VARCHAR(255) NULL;
