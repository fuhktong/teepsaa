# teepsaa — Editable Static Pages (lightweight CMS)

Goal: move the hardcoded static content pages into the database so **admin employees can edit them** (bilingual EN + KM) without touching code. The current hand-translated pages become the seed data.

Status: **PLANNED** (not started). Created 2026-07-02.

---

## Why

- Hand off content edits to admin staff (legal/policy/help updates over time).
- Keep EN + KM versions editable in one place.
- Legal pages will be reviewed by a Khmer speaker later — a CMS lets them edit directly instead of via code.

## Scope — pages covered

| Page | Slug | Current state |
|------|------|---------------|
| Privacy Policy | `privacy` | hardcoded bilingual (done) → migrate to DB |
| Terms of Service | `terms` | hardcoded bilingual (done) → migrate to DB |
| Shipping | `shipping` | hardcoded bilingual (done) → migrate to DB |
| Returns | `returns` | (in progress by hand) → migrate to DB |
| About | `about` | uses `$t` keys — could stay or migrate |
| Help / FAQ | `help` | **structured Q&A → separate table** (see below) |

---

## Data model

### `content_pages` (simple prose pages)
```
id           INT UNSIGNED PK
slug         VARCHAR(50) UNIQUE   -- privacy | terms | shipping | returns
title_en     VARCHAR(150)
title_km     VARCHAR(150)
body_en      MEDIUMTEXT           -- HTML or Markdown (decide below)
body_km      MEDIUMTEXT
updated_at   DATETIME
updated_by   INT UNSIGNED         -- admin id
```
Migration seeds current EN/KM bodies as the initial rows.

### `faq_items` (the Help page — structured Q&A)
```
id           INT UNSIGNED PK
section      VARCHAR(100)         -- grouping (Orders, Delivery, Payment, …)
question_en, question_km  VARCHAR(255)
answer_en,   answer_km    TEXT
sort_order   TINYINT UNSIGNED
active       TINYINT(1)
```
Admin gets add / edit / reorder / show-hide (same pattern as banners/careers).

---

## Public side

Each page (`privacy/index.php`, etc.) becomes ~3 lines:
1. `SELECT * FROM content_pages WHERE slug = ?`
2. Pick `body_km` when `$_SESSION['lang'] === 'km'` **and** it's non-empty, else `body_en` (English fallback — same rule as bilingual banners).
3. Render (see sanitization note).

Wrap the query in try/catch → fall back to a "content unavailable" notice if the table is missing.

## Admin side

- New **Content** tab (under Marketing section, or its own section) → list of pages → edit screen with **EN and KM fields side by side**, Save.
- FAQ manager screen: list items, add/edit/reorder/hide.
- Follows the existing admin CRUD pattern (`banners.php` + `banner-action.php`, `careers.php` + `careers-action.php`): csrf, auth guard, flash messages.

---

## Decisions to make before building

1. **Editor format — pick one:**
   - **Markdown** (recommended for safety/simplicity): store raw, render server-side (e.g. Parsedown), no XSS surface. Slightly less friendly for non-technical staff.
   - **WYSIWYG** (Trix/Quill): friendliest for staff, but stores HTML → **must sanitize on output** with a tag whitelist even though editors are trusted (defense in depth).
2. **About page:** leave on `$t` keys, or fold into `content_pages` for consistency? (Low priority.)
3. **Access:** gate the Content editor behind the future admin RBAC "Marketing/Content" role — see `teepsaa-checklist-admin-rbac.md`.

---

## Build steps (when started)

- [ ] `database/migration-content-pages.sql` — `content_pages` + `faq_items` tables
- [ ] Seed migration: insert current privacy/terms/shipping/returns EN+KM bodies; seed FAQ from `help/index.php`
- [ ] Choose editor format (markdown vs WYSIWYG) + add sanitizer if HTML
- [ ] `admin/content.php` + `admin/content-action.php` (page editor, EN/KM)
- [ ] `admin/faq.php` + `admin/faq-action.php` (FAQ manager)
- [ ] Add **Content** tab to `admin/admin-tabs.php`
- [ ] Rewrite `privacy|terms|shipping|returns/index.php` to read from DB
- [ ] Rewrite `help/index.php` to read from `faq_items`
- [ ] Gate behind RBAC capability once RBAC exists
- [ ] Test EN/KM render + fallback + admin edit round-trip

## Notes
- The hardcoded bilingual pages already written are the seed content — no rework lost.
- Ties into **admin RBAC** (`teepsaa-checklist-admin-rbac.md`): "manage_content" is a capability there.
