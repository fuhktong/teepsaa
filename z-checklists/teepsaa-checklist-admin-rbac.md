# teepsaa — Tiered Admin Access (RBAC)

Goal: replace the current **all-or-nothing** admin (`role='admin'` + `is_admin=1`) with **tiered admin accounts** — one super-admin who controls everything, plus limited admins scoped to specific functions (marketing, messaging, orders).

Status: **PLANNED** (not started). Created 2026-07-02.

---

## Current state

- Admins = `role='admin'` + `is_admin=1` → binary, full access to all of `/admin/*`.
- Admin nav is organized into sections already: **Admin** (vendors/buyers/products/categories/reviews), **Orders** (orders/refunds/accounting/payments/payouts), **Marketing** (promo-codes/banners/careers/maps), **Messages**.
- These existing sections map cleanly onto roles.

## Target model (recommended: fixed roles, not granular caps)

Add an **`admin_role`** column to the admin account.

| Role | Access |
|------|--------|
| **super** | Everything **+ manage other admin accounts** |
| **marketing** | Promo codes, banners, careers, content pages, maps |
| **support** | Messages only |
| **orders** | Orders, refunds, accounting, payments, payouts |

- **super** bypasses all checks and is the only role that can create/edit admins and assign roles.
- Start with this fixed set (an enum). Only move to granular named capabilities (`manage_banners`, `reply_messages`, …) in a join table if the team later needs finer control — probably unnecessary at current size.

---

## The gate (most important part)

A helper, e.g. `admin_can(string $section): bool`, checked in **two places for every protected surface**:

1. **Top of each admin page** — redirect / 403 if not allowed (also drives which nav tabs show).
2. **Inside every action handler** (the `*-action.php` POST processors).

> ⚠️ **Hiding a nav link is NOT security.** A scoped admin could still POST directly to `/admin/banner-action.php`. Enforcement MUST be server-side in the action handlers, not just the page render. This is the bigger/fussier half of the work.

Nav filtering: `admin/admin-tabs.php` and the header admin nav render only the sections the current admin's role permits.

---

## Super-admin: managing admins

- New **Admins** screen (super only): list admins, create new, set role, deactivate.
- Creating an admin sets `role='admin'`, `is_admin=1`, and `admin_role=<tier>`.
- Guard against removing the last super-admin.

---

## Build steps (when started)

- [ ] `database/migration-admin-roles.sql` — add `admin_role ENUM('super','marketing','support','orders')` to admins (default 'super' for existing accounts so nothing breaks)
- [ ] `config/admin-auth.php` (or extend existing) — `admin_can($section)` + a map of role → allowed sections
- [ ] Thread `admin_can()` guard into **every** `/admin/*.php` page (redirect/403)
- [ ] Thread `admin_can()` guard into **every** `/admin/*-action.php` handler (the critical part)
- [ ] Filter nav in `admin/admin-tabs.php` + header admin section by role
- [ ] `admin/admins.php` + `admin/admins-action.php` — super-only admin management (create/edit/role/deactivate)
- [ ] Prevent deleting/demoting the last super-admin
- [ ] Test each role: allowed sections work, disallowed pages 403, disallowed **actions** 403 even when POSTed directly

---

## Sequencing notes

- Do the **gate + role column** first (core), then the **Admins management** screen.
- Ties into the **content CMS** (`teepsaa-checklist-content-cms.md`): the Content editor sits under the **marketing** role.
- Audit-friendly extra (optional, later): an `admin_actions` log (who did what, when).
