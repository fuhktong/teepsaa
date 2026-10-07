<?php
// The oldest build of each app that is still allowed to run. Read by
// api/v1/app-version.php, which both apps ask on every launch; an app older
// than its number here shows "Please update" and nothing else.
//
// The number is the BUILD number, not the "1.0" name people see:
//   Android — versionCode in android/app/build.gradle
//   iPhone  — CURRENT_PROJECT_VERSION in Xcode (the "Build" field)
// A whole number is compared, so "1.10" vs "1.9" can never be got wrong.
//
// How to use it: ship the new build, wait until it is LIVE in that store, then
// raise the number here and deploy. Raising it before the store has the new
// build locks everyone out with nothing to update to. Android and iPhone are
// separate for exactly that reason — Apple's review can lag Google's by days.
//
// store_url is where the Update button goes. null hides the button (the iPhone
// apps have no App Store address until they are listed) and the screen just
// says to update from the store.
return [
    'vendor' => [
        'android' => ['min_build' => 1, 'store_url' => 'https://play.google.com/store/apps/details?id=com.teepsaa.vendor'],
        'ios'     => ['min_build' => 1, 'store_url' => null],
    ],
    'buyer' => [
        'android' => ['min_build' => 1, 'store_url' => 'https://play.google.com/store/apps/details?id=com.teepsaa.buyer'],
        'ios'     => ['min_build' => 1, 'store_url' => null],
    ],
];
