# Discount Codes / Coupons


## What it does

Buyers enter a promo code at checkout to get a discount. Admin creates and manages codes. Discount is applied to the order subtotal before delivery fee.


## Database

### `coupons` table
| column | type | notes |
|---|---|---|
| id | INT PK | |
| code | VARCHAR(32) UNIQUE | uppercase, e.g. SAVE10 |
| type | ENUM('percent','fixed') | percent = % off, fixed = $X off |
| value | DECIMAL(10,2) | e.g. 10.00 = 10% or $10 |
| min_order | DECIMAL(10,2) DEFAULT 0 | minimum subtotal to qualify |
| max_uses | INT NULL | NULL = unlimited |
| used_count | INT DEFAULT 0 | incremented on each use |
| starts_at | DATETIME NULL | NULL = active immediately |
| expires_at | DATETIME NULL | NULL = no expiry |
| active | TINYINT DEFAULT 1 | admin toggle |
| created_at | DATETIME | |

### `coupon_uses` table
| column | type | notes |
|---|---|---|
| id | INT PK | |
| coupon_id | INT FK → coupons | |
| buyer_id | INT FK → buyers | |
| order_id | INT FK → orders | |
| discount_amount | DECIMAL(10,2) | amount actually deducted |
| used_at | DATETIME | |

### `orders` table additions
- `coupon_id INT NULL FK → coupons`
- `coupon_code VARCHAR(32) NULL` — snapshotted at checkout
- `discount_amount DECIMAL(10,2) DEFAULT 0`


## Files to build

### Database
- `database/migration-coupons.sql`

### Admin
- `admin/coupons.php` — list all coupons: code, type, value, uses/max, expiry, active toggle, delete
- `admin/coupon-form.php` — create / edit form (code, type, value, min order, max uses, date range)
- `admin/coupon-action.php` — POST handler: create, edit, toggle active, delete

### API
- `api/coupon/validate.php` — POST `{ code, subtotal }` → returns `{ valid, discount_amount, message }` or error; checks active, dates, min_order, max_uses, one-use-per-buyer

### Checkout
- `checkout/index.php` — add coupon input field + Apply button; JS calls validate endpoint, shows discount line on summary
- `checkout/confirm.php` — re-validate coupon server-side, insert coupon_use row, snapshot code + discount on order, increment used_count


## Validation rules (enforce at both API and confirm.php)

1. Code exists and `active = 1`
2. `starts_at` is NULL or in the past
3. `expires_at` is NULL or in the future
4. `max_uses` is NULL or `used_count < max_uses`
5. Order subtotal ≥ `min_order`
6. Buyer has not already used this code (check `coupon_uses`)

Rule 6 is optional — decide whether codes are single-use-per-buyer or freely reusable.


## Discount calculation

```
subtotal = sum of (item price × quantity)
discount = type=percent  → subtotal × (value / 100), capped at subtotal
           type=fixed    → min(value, subtotal)
total = subtotal - discount + delivery_fee
```

Discount never reduces total below $0.


## Admin UI notes

- Codes stored and compared uppercase; trim and uppercase input on both ends
- Show "3 / 100 uses" in the list so admin knows uptake
- Expired codes are read-only (no edit, just delete)
- Add a Coupons tab to the admin panel alongside Vendors, Orders, Buyers, etc.


## Edge cases

- Coupon applied, then buyer removes items from cart reducing subtotal below min_order → re-validate on confirm
- Two buyers submit with the last remaining use simultaneously → `used_count` increment uses `UPDATE coupons SET used_count = used_count + 1 WHERE id = ? AND (max_uses IS NULL OR used_count < max_uses)` then check `rowCount()`
- Vendor royalty is calculated on the post-discount subtotal (or pre-discount — decide and document)
