<?php
// Seed / re-sync the email_templates table from config/email-templates.php.
// Inserts missing templates; does NOT overwrite rows staff have already edited
// (matched by template_key). Run: php database/seed-email-templates.php
require __DIR__ . '/../config/db.php';

$defaults = require __DIR__ . '/../config/email-templates.php';

$insert = $pdo->prepare(
    'INSERT INTO email_templates
        (template_key, label, tokens, subject_km, subject_en, heading_km, heading_en, body_km, body_en, cta_km, cta_en, sort_order)
     VALUES (:k, :label, :tokens, :skm, :sen, :hkm, :hen, :bkm, :ben, :ckm, :cen, :sort)
     ON DUPLICATE KEY UPDATE label = VALUES(label), tokens = VALUES(tokens)'
);

$sort = 0;
foreach ($defaults as $key => $t) {
    $insert->execute([
        ':k'      => $key,
        ':label'  => $t['label'],
        ':tokens' => $t['tokens'] ?? null,
        ':skm'    => $t['subject_km'],
        ':sen'    => $t['subject_en'],
        ':hkm'    => $t['heading_km'],
        ':hen'    => $t['heading_en'],
        ':bkm'    => $t['body_km'],
        ':ben'    => $t['body_en'],
        ':ckm'    => $t['cta_km'] ?? null,
        ':cen'    => $t['cta_en'] ?? null,
        ':sort'   => $sort++,
    ]);
    echo "seeded: {$key}\n";
}
echo "Done — " . count($defaults) . " templates.\n";
