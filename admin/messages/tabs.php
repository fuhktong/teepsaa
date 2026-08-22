<?php
// Shared page head + tab strip for every page under /admin/messages/.
// Included by index.php, thread.php, emails.php, email-edit.php,
// announcements.php and announcement-edit.php so the tabs are reachable from
// anywhere in the section — no page is a dead end needing a back link.
//
// Set $amsgTab before including: 'buyer' | 'vendor' | 'guest' |
// 'announcements' | 'emails'. Optionally set $amsgStatus to keep the current
// status filter on the three role tabs (only the thread list has one).
// Needs $pdo.

$amsgTab    = $amsgTab    ?? '';
$amsgStatus = $amsgStatus ?? 'pending';

// Threads still needing attention, for the tab badges. index.php has already
// worked these out for its own use; don't ask the database twice.
if (!isset($roleCounts)) {
    $roleCounts = ['buyer' => 0, 'vendor' => 0, 'guest' => 0];
    foreach ($pdo->query("SELECT sender_role, COUNT(*) AS c FROM support_threads WHERE status IN ('pending','open') GROUP BY sender_role") as $rc) {
        $roleCounts[$rc['sender_role']] = (int)$rc['c'];
    }
}

$amsgRoleTabs = ['buyer' => 'Buyers', 'vendor' => 'Vendors', 'guest' => 'Contact Form'];
?>
<div class="amsg-page-head">
    <h1>Messages</h1>
</div>

<div class="amsg-role-tabs">
    <?php foreach ($amsgRoleTabs as $role => $label): ?>
    <a href="/admin/messages/?role=<?= $role ?>&status=<?= urlencode($amsgStatus) ?>"
       class="amsg-role-tab <?= $amsgTab === $role ? 'active' : '' ?>"><?= $label ?><?php
        if ($roleCounts[$role] > 0): ?> <span class="admin-tab-badge"><?= $roleCounts[$role] ?></span><?php endif;
    ?></a>
    <?php endforeach; ?>
    <a href="/admin/messages/announcements.php" class="amsg-role-tab <?= $amsgTab === 'announcements' ? 'active' : '' ?>">Announcements</a>
    <a href="/admin/messages/emails.php" class="amsg-role-tab <?= $amsgTab === 'emails' ? 'active' : '' ?>">Email templates</a>
</div>
