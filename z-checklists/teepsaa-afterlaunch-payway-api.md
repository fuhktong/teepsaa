# Teepsaa — ABA PayWay API Integration (Future)

This method replaces the manual payment flow once a registered business and ABA PayWay merchant account are acquired.

---

## Prerequisites

- [ ] Register Teepsaa as a legal business in Cambodia (Ministry of Commerce — moc.gov.kh)
- [ ] Open an ABA PayWay merchant account using business registration documents
- [ ] Obtain ABA PayWay API credentials and review their developer documentation
- [ ] Confirm exact callback/webhook format from ABA PayWay docs

---

## How It Replaces the Manual Flow

| Manual step | PayWay replacement |
|-------------|-------------------|
| Static QR code image on checkout | Dynamically generated QR code via PayWay API — unique per order |
| Buyer clicks "I've paid" | PayWay sends a payment callback to the site automatically |
| Admin manually confirms payment | Site auto-confirms on callback receipt |
| Admin email alert | Still useful as a backup notification |

---

## Build Steps (once API docs are in hand)

- [ ] Install or build a PayWay API client in PHP
- [ ] On checkout, call PayWay API to generate a unique QR code for the order amount
- [ ] Build a webhook endpoint (`/api/payway/callback.php`) to receive payment confirmation
- [ ] On valid callback, mark order as `paid` and notify vendor automatically
- [ ] Remove "I've paid" button and manual admin confirmation step
- [ ] Remove static Teepsaa QR code from checkout

---

## Notes

- ABA PayWay developer docs are only accessible after merchant account approval
- Contact ABA directly before registering to confirm callback/webhook support and get a sample of the API spec
- Vendor payouts remain manual unless ABA offers a payout API (confirm this separately)
