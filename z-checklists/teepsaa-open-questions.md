# Teepsaa — Open Questions

Items that need a policy decision or are explicitly deferred before building.

---

## Refund royalty policy

If an order is refunded, does Teepsaa return its royalty cut to the vendor?

- Current behaviour: royalty is deducted from vendor payout at the time the order completes. The refund flow refunds the buyer's subtotal but does not adjust the vendor payout.
- Decision needed before building any automated royalty clawback.

---

## Category browse filter

Let buyers filter products by category on the browse/map page.

- Deferred to post-launch.
- Categories table and leaf-node structure are already in place — this is a front-end filter addition only.

---

## Payment intermediary license (moved from launch-priorities, 2026-07-10)

Does acting as a payment intermediary (collecting buyer payments, paying out vendors) require a financial license in Cambodia?

- See item #4 in `teepsaa-notes-open-questions.md`.
- Also related to the refund royalty policy above — both shape how money flows through the platform.

---

## "Buy again" homepage row (moved from launch-priorities, 2026-07-10)

Deferred post-launch build: a homepage row of products the buyer has ordered before.

- New query only, no new tables.
- Verified 2026-07-09: not built — the homepage currently has category grid, featured, best sellers, trending, new arrivals, top rated, under-$15, you-might-like, and recently-viewed.

---

## Security — hosting-level decisions (moved from launch-priorities, 2026-07-10)

- [ ] **SSH key authentication** — in hPanel, if SSH access is enabled, switch it to key-based auth (or keep SSH disabled); a weak SSH password is full-account access
- [ ] **Shared hosting risk** — on shared hosting a breach of a neighboring site can expose your database; revisit moving to a VPS once the site has real revenue (accepted risk for launch)
- [ ] **Extra Basic Auth on `admin.teepsaa.com`** — second lock on the admin door: same `.htpasswd` technique as the pre-launch gate but scoped by host (`SetEnvIf Host ^admin\.teepsaa\.com ADMIN_HOST`). Best added AT launch, when the site-wide pre-launch gate comes off — doing it earlier means two password prompts on admin
