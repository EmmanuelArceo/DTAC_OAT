<?php

// Simple image-average-hash (aHash) + hamming distance utilities.
// Used for lightweight face similarity checking (not a production-grade FR model).

function image_ahash_from_path(string $path): ?string {
    if (!file_exists($path)) return null;
    $data = @file_get_contents($path);
    return $data ? image_ahash_from_string($data) : null;
}

function image_ahash_from_string(string $data): ?string {
    $im = @imagecreatefromstring($data);
    if (!$im) return null;
    $small = imagescale($im, 8, 8, IMG_BICUBIC_FIXED);
    if (!$small) { imagedestroy($im); return null; }

    $pixels = [];
    $total = 0;
    for ($y = 0; $y < 8; $y++) {
        for ($x = 0; $x < 8; $x++) {
            $rgb = imagecolorat($small, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $gray = (int)(($r + $g + $b) / 3);
            $pixels[] = $gray;
            $total += $gray;
        }
    }
    $avg = $total / 64.0;
    $bits = '';
    foreach ($pixels as $p) { $bits .= ($p >= $avg) ? '1' : '0'; }
    imagedestroy($small);
    imagedestroy($im);
    return $bits; // 64-char '0'/'1' string
}

function hamming_distance(string $a, string $b): int {
    if (strlen($a) !== strlen($b)) return PHP_INT_MAX;
    $diff = 0;
    $len = strlen($a);
    for ($i = 0; $i < $len; $i++) if ($a[$i] !== $b[$i]) $diff++;
    return $diff;
}

/**
 * Compare two images (path or raw binary). Returns ['match'=>bool,'distance'=>int]
 * threshold default 12 (0..64) — tune to your needs.
 */
function compare_images_by_ahash($imgA, $imgB, int $threshold = 12): array {
    $hashA = null;
    $hashB = null;

    if (is_string($imgA) && file_exists($imgA)) $hashA = image_ahash_from_path($imgA);
    elseif (is_string($imgA)) $hashA = image_ahash_from_string($imgA);

    if (is_string($imgB) && file_exists($imgB)) $hashB = image_ahash_from_path($imgB);
    elseif (is_string($imgB)) $hashB = image_ahash_from_string($imgB);

    if (!$hashA || !$hashB) return ['match' => false, 'distance' => PHP_INT_MAX];

    $dist = hamming_distance($hashA, $hashB);
    return ['match' => ($dist <= $threshold), 'distance' => $dist];
}
?>