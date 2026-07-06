# Teepsaa — Manual Payment Flow

This is the current payment flow using a static ABA QR code and manual admin confirmation. No merchant account or API required.

---

## Buyer

1. Buyer adds items to cart
2. Buyer goes to checkout — sees order summary and total
3. Checkout displays Teepsaa's static ABA QR code image
4. Buyer scans QR with their banking app and pays the exact total
5. Buyer clicks "I've paid" — order status changes to `pending_confirmation`

---

## Admin (Teepsaa)

1. ABA app sends a push notification — payment received
2. Admin logs into admin panel, sees the pending payment
3. Admin verifies the amount matches in the ABA app
4. Admin clicks Confirm Payment — payment status → `confirmed`, orders → `paid`
5. Vendors are notified of their confirmed orders

---

## Vendor

1. Vendor sees the order appear in their dashboard (status: `paid`)
2. Vendor books Grab delivery manually via the Grab app ⚠️ *Grab business API not publicly available*
3. Vendor marks order as `dispatched` in their dashboard
4. Buyer is notified that the order is on the way

---

## Delivery Confirmation

1. Buyer receives order
2. Buyer clicks Confirm Delivery in their account — status → `delivered`
3. Admin receives notification to pay out the vendor
4. Admin opens vendor profile in admin panel — vendor ABA QR code displayed with exact payout amount
5. Admin sends vendor payment (order total minus Teepsaa commission) via ABA app
6. Admin marks order as `completed`

---

## Notes

- Teepsaa's static ABA QR code is stored at `/uploads/aba-qr.png`
- Each vendor uploads their ABA QR code when submitting their business
- Manual confirmation adds delay — buyer expectation should be set at under 1 hour
- Vendor payout SLA: 2–3 business days after delivery confirmation
- See `teepsaa-afterlaunch-payway-api.md` to upgrade this flow with the ABA PayWay API later
