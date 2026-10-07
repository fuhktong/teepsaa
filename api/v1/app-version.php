<?php
// GET /api/v1/app-version.php?app=vendor&platform=android
//   →  {"min_build": 1, "store_url": "https://play.google.com/…"}
//
// Both apps ask this on launch and refuse to run when their own build number is
// below min_build. It is how a change that old phones cannot survive gets made
// at all: raise the number in config/app-versions.php, and every out-of-date
// copy shows "Please update" instead of breaking in some stranger way.
//
// No token: the check runs before anyone has signed in, and a signed-out buyer
// has none. Nothing here is private.
//
// An app or platform this does not know gets min_build 0 — never blocked. The
// apps also let themselves through when this cannot be reached, so a server
// problem can never lock everyone out.
require __DIR__ . '/../../config/api.php';

api_require_method('GET');

$versions = require __DIR__ . '/../../config/app-versions.php';
$entry = $versions[$_GET['app'] ?? ''][$_GET['platform'] ?? ''] ?? null;

api_json([
    'min_build' => (int)($entry['min_build'] ?? 0),
    'store_url' => $entry['store_url'] ?? null,
]);
