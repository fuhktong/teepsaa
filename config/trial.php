<?php
// The promo-code trial: a shop that registered with a promo code pays 0%
// royalty from approval (admin/action.php) until BOTH the end date has passed
// AND completed sales have reached the threshold. A shop past the date but
// under the threshold is still on it.
//
// A strike ends the trial for good (config/strikes.php). A strike an admin
// removes was a mistake, so it does not count; one that has merely expired
// still does.
//
// While royalty is 0% — on the trial, or with the admin's "royalty waived"
// switch — a penalty is still charged on top of it: see royalty_is_free().

/**
 * The shop's trial, or null if it was never on one. 'active' is whether it
 * pays 0% today; 'ended_by_strike' is when a strike ended it, else null.
 */
function vendor_trial(PDO $pdo, int $businessId): ?array {
    $stmt = $pdo->prepare("
        SELECT b.trial_starts_at, b.trial_ends_at, b.royalty_free_threshold,
               (SELECT COALESCE(SUM(o.subtotal), 0) FROM orders o
                 WHERE o.business_id = b.id AND o.status IN ('delivered', 'completed')) AS completed_sales,
               (SELECT MIN(s.created_at) FROM vendor_strikes s
                 WHERE s.business_id = b.id AND s.revoked_at IS NULL) AS struck_at
          FROM businesses b
         WHERE b.id = ?
    ");
    $stmt->execute([$businessId]);
    $b = $stmt->fetch();
    if (!$b || !$b['trial_starts_at']) return null;

    $sales          = (float)$b['completed_sales'];
    $threshold      = (float)$b['royalty_free_threshold'];
    $withinTime     = strtotime($b['trial_ends_at']) > time();
    $belowThreshold = $sales < $threshold;

    return [
        'active'          => !$b['struck_at'] && ($withinTime || $belowThreshold),
        'starts_at'       => $b['trial_starts_at'],
        'ends_at'         => $b['trial_ends_at'],
        'sales'           => round($sales, 2),
        'threshold'       => round($threshold, 2),
        'within_time'     => $withinTime,
        'ended_by_strike' => $b['struck_at'],
    ];
}

/** Whether the shop pays 0% royalty today, by waiver or by trial. */
function royalty_is_free(PDO $pdo, int $businessId): bool {
    $stmt = $pdo->prepare('SELECT royalty_waived FROM businesses WHERE id = ?');
    $stmt->execute([$businessId]);
    if ((int)$stmt->fetchColumn() === 1) return true;
    $trial = vendor_trial($pdo, $businessId);
    return $trial !== null && $trial['active'];
}
