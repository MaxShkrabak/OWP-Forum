<?php
class MediaValidator
{
    private static array $limits = [
        'image' => [
            'maxBytes' => 5 * 1024 * 1024,
            'mimes'    => ['image/jpeg', 'image/png', 'image/webp'],
        ],
    ];

    public static function validateImagePath(string $path, int $bytes): array
    {
        $L = self::$limits['image'];

        if ($bytes > $L['maxBytes']) {
            return self::fail('File too large. Max ' . round($L['maxBytes'] / 1024 / 1024) . ' MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($path) ?: '';

        if (!str_starts_with($mime, 'image/')) {
            return self::fail('Only images are allowed.');
        }
        if (!in_array($mime, $L['mimes'], true)) {
            return self::fail('Unsupported format: ' . $mime);
        }

        return self::ok('image', ['mime' => $mime, 'bytes' => $bytes]);
    }

    private static function ok(string $kind, array $meta): array
    {
        return ['ok' => true, 'kind' => $kind, 'meta' => $meta];
    }
    private static function fail(string $msg): array
    {
        return ['ok' => false, 'error' => $msg];
    }
}
