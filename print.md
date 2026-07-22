# Reset a refund test order (phpMyAdmin)

Resets an order that's mid-refund back to a fresh "delivered" state so you can
re-run the whole refund flow without creating a new order.

## Step 1 — find the order

Run in phpMyAdmin's SQL tab:

```sql
SELECT id, public_id, status, delivered_at
FROM orders
WHERE status IN ('refund_requested','return_approved','return_dispatched','return_received','refunded','refund_rejected')
ORDER BY id DESC;
```

## Step 2 — reset it (swap in the id from step 1)

**Replace `123` with the real order id from Step 1**, or it will match nothing
and report "0 rows affected" (see Troubleshooting below).

```sql
UPDATE orders
SET status              = 'delivered',
    delivered_at        = NOW(),
    refund_reason       = NULL,
    refund_requested_at = NULL,
    refunded_at         = NULL,
    return_tracking_url = NULL
WHERE id = 123;
```

Setting `delivered_at = NOW()` reopens the 24h refund window so the "Request
refund" button reappears. If you leave an old `delivered_at`, the window is
already closed and the button won't show.

## Alternative — edit the row by hand (no SQL)

1. Left sidebar → click the **`orders`** table.
2. **Browse** tab → find your test order (sort by `id` descending, or use the
   **Search** tab to filter by status).
3. Click the **pencil / Edit** icon on that row.
4. **`status`** — ENUM dropdown → pick **`delivered`**.
5. **`delivered_at`** — in the **Function** column dropdown, choose **`NOW()`**
   (or type a recent date-time). This reopens the 24h refund window.
6. **`refund_reason`**, **`refund_requested_at`**, **`refunded_at`**,
   **`return_tracking_url`** — tick the **`NULL`** checkbox for each.
   (Tick NULL — just clearing the text can leave an empty string, not NULL.)
7. Click **Go**.

## Troubleshooting — "nothing changed"

- **Did it say "0 rows affected"?** The `WHERE id =` didn't match. You probably
  left the literal `123` instead of your real order id. Re-run Step 1 to get the
  id, then use that number.
- **Right database?** If you're testing on the live site, run this in the live
  Hostinger phpMyAdmin — not a local one (and vice versa).

To test the **window-expired** case instead, use:

```sql
delivered_at = NOW() - INTERVAL 2 DAY
```

## Optional — clear the test bell notifications

Safe on a test DB (it clears these types for all users):

```sql
DELETE FROM notifications
WHERE type IN ('refund_requested','refund_approved','refund_rejected','refund_sent','return_dispatched','return_received');
```

## Caution (live server)

Always run the Step 1 `SELECT` first and delete/update by a specific `id`.
The `UPDATE` would rewind any real customer's in-progress refund too if one
existed — not a concern pre-launch with only your test data, but the reason to
target one `id` rather than running it blind.
