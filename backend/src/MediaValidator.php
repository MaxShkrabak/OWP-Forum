<?php
// backend/src/MediaValidator.php

use getID3;

/**
 * MediaValidator
 * - Strictly validates uploaded images/videos.
 * - Returns ['ok'=>true, 'kind'=>'image'|'video', 'meta'=>...] or ['ok'=>false, 'error'=>'...'].
 */
class MediaValidator
{
    // Tweak these to your rules (server caps must allow >= these)
    private static array $limits = [
        'image' => [
            'maxBytes' => 5 * 1024 * 1024,              // 5 MB
            'maxW'     => 4096,
            'maxH'     => 4096,
            'mimes'    => ['image/jpeg','image/png','image/webp'], // disallow GIFs by default
        ],
        'video' => [
            'maxBytes' => 100 * 1024 * 1024,            // 100 MB
            'maxW'     => 1920,                         // 1080p
            'maxH'     => 1080,
            'maxSec'   => 60,                           // 1 minute
            'mimes'    => ['video/mp4','video/webm'],
        ],
    ];

    public static function validateUploadedFile(array $file): array
    {
        // Basic shape check
        if (!isset($file['tmp_name'], $file['name'], $file['size']) || !is_uploaded_file($file['tmp_name'])) {
            return self::fail('No file uploaded.');
        }

        $tmp  = $file['tmp_name'];
        $size = (int)$file['size'];

        // Real MIME by signature (never trust client)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmp) ?: '';

        $isImage = str_starts_with($mime, 'image/');
        $isVideo = str_starts_with($mime, 'video/');

        if (!$isImage && !$isVideo) {
            return self::fail('Only images or videos are allowed.');
        }

        $L = self::$limits[$isImage ? 'image' : 'video'];

        if ($size > $L['maxBytes']) {
            return self::fail('File too large. Max ' . round($L['maxBytes']/1024/1024) . ' MB.');
        }
        if (!in_array($mime, $L['mimes'], true)) {
            return self::fail('Unsupported format: ' . $mime);
        }

        if ($isImage) {
            $info = @getimagesize($tmp);
            if ($info === false) return self::fail('Could not read image.');
            [$w, $h] = [$info[0] ?? 0, $info[1] ?? 0];
            if ($w > $L['maxW'] || $h > $L['maxH']) {
                return self::fail("Image too large ({$w}×{$h}). Max {$L['maxW']}×{$L['maxH']}.");
            }
            return self::ok('image', [
                'mime' => $mime, 'bytes' => $size, 'width' => $w, 'height' => $h,
            ]);
        }

        // Video: read metadata cross-platform with getID3
        $getID3   = new getID3();
        $details  = @$getID3->analyze($tmp);
        $width    = (int)($details['video']['resolution_x'] ?? 0);
        $height   = (int)($details['video']['resolution_y'] ?? 0);
        $duration = (float)($details['playtime_seconds'] ?? 0);

        if ($duration && $duration > $L['maxSec']) {
            return self::fail('Video too long (' . round($duration) . 's). Max ' . $L['maxSec'] . 's.');
        }
        if ($width > $L['maxW'] || $height > $L['maxH']) {
            return self::fail("Video too large ({$width}×{$height}). Max {$L['maxW']}×{$L['maxH']}.");
        }

        return self::ok('video', [
            'mime' => $mime, 'bytes' => $size,
            'width' => $width, 'height' => $height, 'seconds' => $duration,
        ]);
    }

    // Helpers
    private static function ok(string $kind, array $meta): array { return ['ok'=>true, 'kind'=>$kind, 'meta'=>$meta]; }
    private static function fail(string $msg): array { return ['ok'=>false, 'error'=>$msg]; }
}
