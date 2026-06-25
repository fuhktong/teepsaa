<?php
// Expects $orderStatus (string) to be set before including.
$_oRefundStatuses = ['refund_requested', 'return_approved', 'return_dispatched', 'return_received', 'refunded', 'refund_rejected'];
if (in_array($orderStatus, $_oRefundStatuses)) {
    $refundStatus = $orderStatus;
    require __DIR__ . '/../refund-status/refund-status.php';
    unset($_oRefundStatuses);
    return;
}
unset($_oRefundStatuses);
$_osteps = [
    'pending'    => 'Payment<br>submitted',
    'paid'       => 'Payment<br>confirmed',
    'dispatched' => 'Dispatched',
    'delivered'  => 'Delivered',
    'completed'  => 'Completed',
];
$_okeys       = array_keys($_osteps);
$_ocurrentIdx = array_search($orderStatus, $_okeys);
$_ocancelled  = $_ocurrentIdx === false;
?>
<?php if ($_ocancelled): ?>
<div class="ostatus-bar ostatus-cancelled">Order cancelled</div>
<?php else: ?>
<div class="ostatus-bar">
    <?php foreach ($_okeys as $_oi => $_okey): ?>
    <?php
    if ($_oi < $_ocurrentIdx)       $_ocls = 'done';
    elseif ($_oi === $_ocurrentIdx) $_ocls = 'active';
    else                            $_ocls = 'upcoming';
    ?>
    <div class="ostatus-step <?= $_ocls ?>">
        <div class="ostatus-dot"></div>
        <span class="ostatus-label"><?= $_osteps[$_okey] ?></span>
    </div>
    <?php if ($_oi < count($_okeys) - 1): ?>
    <div class="ostatus-line <?= $_oi < $_ocurrentIdx ? 'done' : '' ?>"></div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php unset($_osteps, $_okeys, $_ocurrentIdx, $_ocancelled, $_oi, $_okey, $_ocls); ?>
