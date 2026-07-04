<?php
// Expects $refundStatus (string) to be set before including.
if (!isset($t)) {
    $_rl = $_SESSION['lang'] ?? 'km';
    $t = require __DIR__ . '/../lang/' . (in_array($_rl, ['en', 'km']) ? $_rl : 'en') . '.php';
}
$_rrejected = ($refundStatus === 'refund_rejected');
$_rsteps = [
    'refund_requested'  => $t['rstatus_requested'],
    'return_approved'   => $t['rstatus_approved'],
    'return_dispatched' => $t['rstatus_dispatched'],
    'return_received'   => $t['rstatus_received'],
    'refunded'          => $t['rstatus_refunded'],
];
$_rkeys       = array_keys($_rsteps);
$_rcurrentIdx = array_search($refundStatus, $_rkeys);
?>
<?php if ($_rrejected): ?>
<div class="ostatus-bar ostatus-cancelled"><?= $t['rstatus_rejected'] ?></div>
<?php else: ?>
<div class="ostatus-bar">
    <?php foreach ($_rkeys as $_ri => $_rkey): ?>
    <?php
    if ($_ri < $_rcurrentIdx)        $_rcls = 'done';
    elseif ($_ri === $_rcurrentIdx)  $_rcls = 'active refund-active';
    else                             $_rcls = 'upcoming';
    ?>
    <div class="ostatus-step <?= $_rcls ?>">
        <div class="ostatus-dot"></div>
        <span class="ostatus-label"><?= $_rsteps[$_rkey] ?></span>
    </div>
    <?php if ($_ri < count($_rkeys) - 1): ?>
    <div class="ostatus-line <?= $_ri < $_rcurrentIdx ? 'done' : '' ?>"></div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php unset($_rrejected, $_rsteps, $_rkeys, $_rcurrentIdx, $_ri, $_rkey, $_rcls); ?>
