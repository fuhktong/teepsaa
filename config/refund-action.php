<?php
// Which refunds wait on the vendor, for the "waiting on you" counts in the
// header, the vendor menu, the Refunds pill and the app. Its own file so the
// header can load it without the rest of config/refund-evidence.php.
//
// Two things wait on the vendor: a return on its way back (Received, or "used,
// empty or different"), and a dispute still open for the vendor's reply.

const REFUND_REPLY_HOURS = 48;

// Expects the orders table as `o`.
// A return the buyer says was refused is the admin's, not the vendor's.
const VENDOR_REFUND_ACTION_SQL = "((o.status = 'return_dispatched' AND o.return_bounced_at IS NULL)
    OR (o.status = 'refund_requested' AND o.vendor_reply_at IS NULL
        AND o.refund_requested_at > NOW() - INTERVAL " . REFUND_REPLY_HOURS . " HOUR))";

// What waits on an admin: a dispute to decide, a return to refund, and a
// return held because the buyer says it was refused or the shop never
// answered (config/refund-evidence.php). No table alias.
const ADMIN_REFUND_ACTION_SQL = "(status IN ('refund_requested', 'return_received')
    OR (status = 'return_dispatched' AND (return_bounced_at IS NOT NULL OR return_overdue_at IS NOT NULL)))";
