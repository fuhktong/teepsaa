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
if (!isset($t)) {
    $_ol = $_SESSION['lang'] ?? 'km';
    $t = require __DIR__ . '/../lang/' . (in_array($_ol, ['en', 'km']) ? $_ol : 'en') . '.php';
}
$_osteps = [
    'pending'    => $t['ostatus_pending'],
    'paid'       => $t['ostatus_paid'],
    'dispatched' => $t['ostatus_dispatched'],
    'delivered'  => $t['ostatus_delivered'],
    'completed'  => $t['ostatus_completed'],
];
$_okeys       = array_keys($_osteps);
$_ocurrentIdx = array_search($orderStatus, $_okeys);
$_ocancelled  = $_ocurrentIdx === false;
?>
<?php if ($_ocancelled): ?>
<div class="ostatus-bar ostatus-cancelled"><?= $t['ostatus_cancelled'] ?></div>
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
