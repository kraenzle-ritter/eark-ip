<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

/** MIME type of a written file: fileinfo when available, else by extension, else octet-stream. */
final class MimeTypes
{
    private const BY_EXTENSION = [
        'xml' => 'text/xml', 'xsd' => 'application/xml', 'json' => 'application/json', 'txt' => 'text/plain',
        'csv' => 'text/csv', 'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png', 'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'jp2' => 'image/jp2',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'mp4' => 'video/mp4', 'html' => 'text/html',
        'zip' => 'application/zip', 'siard' => 'application/x-siard',
    ];

    public static function detect(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($extension, ['xml', 'xsd'], true)) {
            return self::BY_EXTENSION[$extension];
        }
        if (function_exists('finfo_open')) {
            $info = finfo_open(FILEINFO_MIME_TYPE);
            $type = $info === false ? false : finfo_file($info, $path);
            if (is_string($type) && $type !== '' && $type !== 'application/octet-stream' && str_contains($type, '/')) {
                return $type;
            }
        }

        return self::BY_EXTENSION[$extension] ?? 'application/octet-stream';
    }
}
