<?php
// GET /api/v1/lang.php?l=en  →  every word the app shows, in one reply.
//
// The point of this endpoint is that there is only ever one set of
// translations. The website's pages do `$t = require lang/en.php` and read
// `$t['nav_orders']`; the app asks here and reads `t('nav_orders')`. Add a
// word to lang/en.php and lang/km.php and both get it — they cannot drift.
//
// Two things make this endpoint unlike the others under /api/v1/:
//
//   - No token. The login screen needs words before anyone has signed in, so
//     this has to answer an app that has no token yet. Nothing here is
//     private: it is the same text a visitor reads on teepsaa.com.
//
//   - GET, not POST. It reads and changes nothing.
//
// No rate limit, for the same reason the website's own pages have none: this
// writes nothing and sends no mail. It is a static file read.
//
// `v` is how the app avoids downloading 73 KB of Khmer on every launch. The
// version is a hash of the language file's contents, so it changes when the
// words change and not when the site is deployed. The app stores the strings
// and the version it got them with, sends that version back, and when it
// still matches it gets a one-line "fresh" reply instead of the whole set.
require_once __DIR__ . '/../../config/api.php';

api_require_method('GET');

$lang = $_GET['l'] ?? '';
if ($lang !== 'en' && $lang !== 'km') {
    $lang = DEFAULT_LANG;
}

$file    = __DIR__ . '/../../lang/' . $lang . '.php';
$version = substr(md5_file($file), 0, 12);

if (($_GET['v'] ?? '') === $version) {
    api_json(['lang' => $lang, 'version' => $version, 'fresh' => true]);
}

api_json([
    'lang'    => $lang,
    'version' => $version,
    'strings' => require $file,
]);
