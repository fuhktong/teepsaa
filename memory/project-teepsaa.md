---
name: project-teepsaa
description: Core context for the teepsaa marketplace project — tech stack, role system, key patterns
metadata:
  type: project
---

Teepsaa is a Cambodian marketplace (Phnom Penh only) built in vanilla PHP + MySQL. No framework. Pages follow CLAUDE.md's file structure rule: each page in its own folder with its own PHP and CSS. Global styles in style.css (reset only). DB config in /config/db.php.

**Role system:** buyers (`role=buyer`) login at /login-buyer/, vendors (`role=vendor`) at /login-vendor/, admins (`is_admin=1`) at /login-admin/. Hard role separation — vendors can't buy, buyers can't sell.

**Email verification:** Both buyers and vendors must verify email before accessing the dashboard. After registration, session only gets `user_id` + `pending_role` (not `role`) — the header checks for `role` to show the dropdown, so unverified users see no nav. After verification click, they're redirected to login. Login checks `email_verified_at` — if null, sets `pending_role` and redirects to /resend-verification/.

**Key tables:** buyers, vendors, businesses, products, orders, order_items, support_threads, support_messages, vendor_penalties, vendor_notifications, categories

**Vendor suspension:** vendors table has banned, ban_reason, banned_at columns (added 2026-05-28). Admin suspends from /admin/ vendor popup → /admin/vendor-action.php. Login checks banned flag. Takes effect on next login (no session invalidation).

**Address structure:** Phnom Penh only. Separate house_number, address (street), khan, sangkat fields. Khan/sangkat cascade via JS using /config/phnom-penh-locations.php. Buyers store in buyers table; vendors store in businesses table.

**Why:** Multi-step fix session from 2026-05-28 covering email verification enforcement, vendor suspension, products category column, admin refund items display, submit business address overhaul.
