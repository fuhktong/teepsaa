<?php
// SMTP credentials — leave SMTP_PASS blank for local dev (emails will be logged to mail.log in project root)
// On the server, set SMTP_PASS to the contact@teepsaa.com mailbox password from hPanel
// Do NOT commit this file with a real password
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'contact@teepsaa.com');
define('SMTP_PASS', '');
define('MAIL_FROM',      'contact@teepsaa.com');
define('MAIL_FROM_NAME', "teepsaa");
