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
