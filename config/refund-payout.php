<?php
// What a refunded order still owes the vendor.
//
// The vendor paid the Grab driver or courier out of their own pocket, and the
// buyer's delivery fee is never refunded, so it is always the vendor's —
// even when they refused the return. After a part refund the vendor also
// gets their share of the price the buyer didn't get back, with royalty and
// any coupon of their own taken off in the same proportion as at checkout.
//
// Paid from admin/order.php through admin/payouts-action.php like any other
// payout. The order stays 'refunded'; paid_out_at marks it as paid.

/**
 * SQL for unpaid refunded orders with something owed — the same rule as
 * refund_vendor_payout()['total'] > 0. $t is the orders table alias, if any.
 */
function refund_payout_due_sql(string $t = ''): string {
    $c = $t !== '' ? $t . '.' : '';
    return "({$c}status = 'refunded' AND {$c}paid_out_at IS NULL"
         . " AND ({$c}delivery_fee > 0 OR {$c}refund_paid < {$c}subtotal - {$c}discount_amount))";
}

/**
 * Needs subtotal, discount_amount, delivery_fee, vendor_payout and refund_paid.
 * Returns ['goods_paid', 'refunded', 'kept_share', 'delivery', 'total'].
 */
function refund_vendor_payout(array $o): array {
    $delivery = round((float)$o['delivery_fee'], 2);
    $paid     = round((float)$o['subtotal'] - (float)$o['discount_amount'], 2);
    // Refunded orders from before refund_paid existed were always full refunds.
    $refunded = $o['refund_paid'] !== null ? round((float)$o['refund_paid'], 2) : $paid;
    $kept     = max(0.0, $paid - $refunded);
    // vendor_payout is the vendor's cut of the price plus delivery.
    $share    = max(0.0, (float)$o['vendor_payout'] - $delivery);
    $keptShare = $paid > 0 ? round($share * $kept / $paid, 2) : 0.0;

    return [
        'goods_paid' => $paid,
        'refunded'   => $refunded,
        'kept_share' => $keptShare,
        'delivery'   => $delivery,
        'total'      => round($delivery + $keptShare, 2),
    ];
}
