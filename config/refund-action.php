<?php
// Which refunds wait on the vendor, for the "waiting on you" counts in the
// header, the vendor menu, the Refunds pill and the app. Its own file so the
// header can load it without the rest of config/refund-evidence.php.
//
// Two things wait on the vendor: a return on its way back (Received, or "used,
// empty or different"), and a dispute still open for the vendor's reply.

const REFUND_REPLY_HOURS = 48;

// Expects the orders table as `o`.
const VENDOR_REFUND_ACTION_SQL = "(o.status = 'return_dispatched'
    OR (o.status = 'refund_requested' AND o.vendor_reply_at IS NULL
        AND o.refund_requested_at > NOW() - INTERVAL " . REFUND_REPLY_HOURS . " HOUR))";
