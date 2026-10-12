<?php
// The "this sign-up matches a suspended account" card on admin/buyer.php and
// admin/vendor.php. Set before requiring: $fpRole ('buyer'|'vendor'), $fpId.
// Fingerprints and matching are explained in config/fingerprint.php.

$fpMatches = fingerprint_matches_for($pdo, $fpRole, $fpId);
if (!$fpMatches) return;

$fpField = $fpRole . '_id';
?>
<div class="detail-card account-matches">
    <div class="detail-card-title">Matches a suspended account</div>
    <p class="account-matches-hint">Same email or phone as an account that was suspended. Not proof — a shared or recycled phone matches too. Nothing is blocked; decide here.</p>
    <?php foreach ($fpMatches as $m): ?>
    <div class="account-match<?= $m['reviewed_at'] ? ' account-match--reviewed' : '' ?>">
        <div>
            <strong><?= $m['kind'] === 'email' ? 'Email' : 'Phone' ?></strong>
            matches suspended <?= htmlspecialchars($m['old_role']) ?>
            <a href="/admin/<?= $m['old_role'] === 'vendor' ? 'vendor' : 'buyer' ?>.php?id=<?= (int)$m['old_id'] ?>">#<?= (int)$m['old_id'] ?></a>
            <?php if ($m['reason']): ?> — <?= htmlspecialchars($m['reason']) ?><?php endif; ?>
        </div>
        <div class="account-match-meta">
            Flagged <?= date('M j, Y', strtotime($m['created_at'])) ?>
            <?php if ($m['reviewed_at']): ?> · reviewed <?= date('M j, Y', strtotime($m['reviewed_at'])) ?><?php endif; ?>
        </div>
        <?php if (!$m['reviewed_at']): ?>
        <form method="POST" action="/admin/<?= $fpRole ?>-action.php">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="match_reviewed">
            <input type="hidden" name="<?= $fpField ?>" value="<?= $fpId ?>">
            <input type="hidden" name="match_id" value="<?= (int)$m['id'] ?>">
            <button type="submit" class="btn-approve">Mark reviewed</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
