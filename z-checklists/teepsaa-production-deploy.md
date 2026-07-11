# Teepsaa — Production Deployment (open tasks)

Everything else from the original deployment checklist is done and archived in
`teepsaa-completed.md` (Launch Priorities + Production Deployment sections, verified
against the live server 2026-07-09/10). What remains before removing the pre-launch gate:

---

## Final test — run the full order flow (on the LIVE site, before launch)

Covered in detail by `teepsaa-todos-functional-testing.md` — this is the condensed version:

- [ ] Register a vendor → submit a business → upload ABA QR
- [ ] Log in as admin → approve the business
- [ ] Add a product as vendor
- [ ] Register a buyer → add to cart → checkout → "I've paid"
- [ ] Log in as admin → confirm payment
- [ ] Log in as vendor → mark dispatched
- [ ] Log in as buyer → confirm delivery
- [ ] Log in as admin → process payout → mark completed
- [ ] Trigger `cron/auto-confirm.php` manually and confirm admin email arrives

---

## At launch (from the original gate setup)

- [ ] Remove the pre-launch gate: delete the Basic Auth block from `.htaccess` and the `.htpasswd` file on the server (the exposure fix this was waiting on — z-checklists/ + database/ removal — was completed 2026-07-10, see `teepsaa-completed.md`)
- [ ] Optional: add the host-scoped extra Basic Auth on `admin.teepsaa.com` at the same time (see `teepsaa-open-questions.md`, Security section)
