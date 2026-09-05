-- SEO: real "last modified" dates for the sitemap.
--
-- sitemap.php was reporting <lastmod> from created_at, so a listing edited
-- yesterday still told Google it had not changed since the day it was first
-- posted. Crawlers use that date to decide whether re-fetching a page is
-- worth it, so edits went unnoticed.
--
-- ON UPDATE CURRENT_TIMESTAMP means MySQL maintains this itself — no
-- application code has to remember to set it.
--
-- Existing rows are backfilled to created_at, which is the honest answer for
-- a row we have no edit history for.

ALTER TABLE products
    ADD COLUMN updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        AFTER created_at;

UPDATE products SET updated_at = created_at WHERE created_at IS NOT NULL;

ALTER TABLE businesses
    ADD COLUMN updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        AFTER created_at;

UPDATE businesses SET updated_at = created_at WHERE created_at IS NOT NULL;
