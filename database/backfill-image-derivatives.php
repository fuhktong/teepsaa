<?php
// One-time backfill: makes the 400px and 1200px WebP copies for every image
// already in uploads/. New uploads get theirs at upload time (see
// image_make_derivatives() in config/upload.php) — this catches the 16 MB
// that went up before that existed.
//
// Safe to re-run: a file that already has both copies is skipped, so if the
// script times out halfway through you just run it again and it picks up
// where it stopped. Nothing is ever deleted or overwritten, and the originals
// are left exactly as they are — they're what the lightbox opens.
//
// Run it from the command line if you have SSH:
//     php database/backfill-image-derivatives.php
// or upload this one file to the server and open it in a browser once, then
// delete it. It prints a line per image and a summary at the end.
//
// If a huge photo kills the process with an out-of-memory error, that one
// file is skipped by the pixel guard on the next run — raise
// IMG_MAX_PIXELS in config/upload.php only if you actually need it converted.

require __DIR__ . '/../config/upload.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    // Long job on a lot of files — don't let the web server give up at 30s.
    @set_time_limit(0);
}
@ini_set('memory_limit', '512M');

$dir = upload_dir();
if (!is_dir($dir)) {
    exit("No uploads folder at {$dir}\n");
}
if (!function_exists('imagewebp')) {
    exit("This PHP build has no WebP support in GD — nothing to do.\n"
       . "Ask Hostinger to enable it, or the site simply keeps serving originals.\n");
}

$files = glob($dir . '/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE) ?: [];
sort($files);

$done = $skipped = $failed = 0;
$before = $after = 0;

foreach ($files as $path) {
    $name = basename($path);
    $base = preg_replace('/\.[^.]+$/', '', $name);

    // Already converted on an earlier run (or deliberately dropped because
    // WebP came out bigger — either way there's nothing left to do).
    $existing = 0;
    foreach (array_keys(IMG_SIZES) as $sizeDir) {
        if (is_file($dir . '/' . $sizeDir . '/' . $base . '.webp')) $existing++;
    }
    if ($existing === count(IMG_SIZES)) {
        $skipped++;
        continue;
    }

    $written = image_make_derivatives($path, $name);
    if ($written > 0) {
        $done++;
        $small = $dir . '/w400/' . $base . '.webp';
        $before += filesize($path);
        $after  += is_file($small) ? filesize($small) : filesize($path);
        printf("  %-40s %7s KB -> %6s KB (%d copies)\n",
            $name,
            number_format(filesize($path) / 1024),
            number_format((is_file($small) ? filesize($small) : filesize($path)) / 1024),
            $written);
    } else {
        $failed++;
        printf("  %-40s skipped (too large, unreadable, or WebP was no smaller)\n", $name);
    }
}

echo "\n";
echo "converted: {$done}   already done: {$skipped}   skipped: {$failed}\n";
if ($before > 0) {
    printf("card-grid images: %s KB -> %s KB (%d%% smaller)\n",
        number_format($before / 1024),
        number_format($after / 1024),
        (int)round(100 - ($after / $before * 100)));
}
