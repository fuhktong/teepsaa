<?php

// Validates image by magic bytes — cannot be spoofed unlike mime_content_type()
// Returns 'image/jpeg', 'image/png', or false
function image_type_from_magic(string $tmp): string|false {
    $bytes = @file_get_contents($tmp, false, null, 0, 8);
    if ($bytes === false) return false;
    if (str_starts_with($bytes, "\xFF\xD8\xFF"))                        return 'image/jpeg';
    if (str_starts_with($bytes, "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"))  return 'image/png';
    return false;
}

// ── Smaller copies ───────────────────────────────────────────────────
//
// Vendors upload whatever came off their phone — the uploads folder holds
// PNGs of 1.7 MB — and every one of them was being served at full size into a
// card 200 pixels wide. On a Phnom Penh mobile connection a homepage of eight
// carousels was several megabytes of pictures nobody can see the detail of.
//
// So each upload also gets two WebP copies: 400px wide for card grids and
// 1200px for the product page and shop banner. They live in uploads/w400/
// and uploads/w1200/ under the same base name, and the original is kept
// untouched — it is what the lightbox opens, and the thing to re-derive from
// if these ever need regenerating at a different size.
//
// Everything degrades: if GD has no WebP support, or the source is a format
// GD can't read, no copy is written and image_variant() keeps returning the
// original. Nothing breaks, it is just as heavy as it was before.

const IMG_SIZES  = ['w400' => 400, 'w1200' => 1200];
const IMG_QUALITY = 82;

// Above roughly this many pixels, decoding the source alone can exhaust the
// memory limit on shared hosting and take the request down with it. A photo
// that large is skipped rather than risked; it still uploads and still works.
const IMG_MAX_PIXELS = 40000000;

function upload_dir(): string {
    return __DIR__ . '/../uploads';
}

// Writes uploads/w400/<name>.webp and uploads/w1200/<name>.webp.
// Never upscales: the 1200px copy of an 800px original is 800px wide, so the
// file always exists and image_variant() never has to guess.
// Returns how many copies it managed to write.
function image_make_derivatives(string $srcPath, string $filename): int {
    if (!function_exists('imagewebp')) return 0;

    $info = @getimagesize($srcPath);
    if (!$info) return 0;
    [$w, $h] = $info;
    if ($w < 1 || $h < 1 || $w * $h > IMG_MAX_PIXELS) return 0;

    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($srcPath),
        IMAGETYPE_PNG  => @imagecreatefrompng($srcPath),
        IMAGETYPE_WEBP => @imagecreatefromwebp($srcPath),
        default        => null,
    };
    if (!$src) return 0;

    $base    = preg_replace('/\.[^.]+$/', '', basename($filename));
    $written = 0;

    foreach (IMG_SIZES as $dirName => $target) {
        $dir = upload_dir() . '/' . $dirName;
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) continue;

        $tw = min($target, $w);
        $th = max(1, (int)round($h * $tw / $w));

        $dst = imagecreatetruecolor($tw, $th);
        // PNGs with transparent backgrounds are common in logos and cut-outs;
        // without these two the transparent areas come out black.
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);

        $out = $dir . '/' . $base . '.webp';
        if (@imagewebp($dst, $out, IMG_QUALITY)) {
            // Flat graphics — a QR code, a logo of three colours — often come
            // out *bigger* as WebP than the PNG they started as. Keeping one
            // of those would make the page slower, so it is thrown away and
            // image_variant() falls back to the original.
            if ($tw < $w || @filesize($out) < @filesize($srcPath)) {
                $written++;
            } else {
                @unlink($out);
            }
        }
        imagedestroy($dst);
    }

    imagedestroy($src);
    return $written;
}

// The address to actually put in an <img src>. Falls back to the original
// whenever the smaller copy isn't there — which is every image uploaded
// before this existed, until database/backfill-image-derivatives.php has run.
function image_variant(?string $filename, string $size = 'w400'): string {
    $filename = basename(trim((string)$filename));
    if ($filename === '' || $filename === '.' || $filename === '..') return '';

    static $cache = [];
    $key = $size . '/' . $filename;
    if (!array_key_exists($key, $cache)) {
        $candidate = $size . '/' . preg_replace('/\.[^.]+$/', '', $filename) . '.webp';
        $cache[$key] = isset(IMG_SIZES[$size]) && is_file(upload_dir() . '/' . $candidate)
            ? $candidate
            : $filename;
    }
    return '/uploads/' . $cache[$key];
}

// Call this wherever the original is unlinked, or the small copies outlive it
// and the folder slowly fills with orphans nobody can trace back to a product.
function image_delete_derivatives(?string $filename): void {
    $filename = basename(trim((string)$filename));
    if ($filename === '' || $filename === '.' || $filename === '..') return;

    $base = preg_replace('/\.[^.]+$/', '', $filename);
    foreach (array_keys(IMG_SIZES) as $sizeDir) {
        $path = upload_dir() . '/' . $sizeDir . '/' . $base . '.webp';
        if (is_file($path)) @unlink($path);
    }
}
