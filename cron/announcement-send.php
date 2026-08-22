<?php
// Drains the announcement queue. Schedule every 5 minutes:
//   */5 * * * * /usr/bin/php /home/USER/domains/teepsaa.com/public_html/cron/announcement-send.php
//
// Each pass sends batches until the queue is empty or the time budget runs out,
// so the next pass picks up where this one stopped. config/mail.php opens one
// SMTP connection per message, which is why this can't live in a web request.

require __DIR__ . '/../config/db.php';
require __DIR__ . '/../config/announcements.php';

const ANNOUNCEMENT_RUN_SECONDS = 240;

$start = time();
$sent = $failed = $skipped = 0;

while (time() - $start < ANNOUNCEMENT_RUN_SECONDS) {
    $r = announcement_process_batch($pdo);
    if ($r === null) break;              // nothing queued

    $sent    += $r['sent'];
    $failed  += $r['failed'];
    $skipped += $r['skipped'];

    if ($r['finished'] && $r['remaining'] === 0) {
        // That announcement is done; loop again in case another is waiting.
        continue;
    }
    if ($r['sent'] === 0 && $r['failed'] === 0 && $r['skipped'] === 0) {
        break;                            // no progress — don't spin
    }
}

if ($sent || $failed || $skipped) {
    echo date('Y-m-d H:i:s') . " announcements: {$sent} sent, {$failed} failed, {$skipped} skipped\n";
}
