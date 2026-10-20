<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation\Rules;

use KraenzleRitter\EarkIp\Resources;

/** The IANA media type registry, shipped as resources/iana-media-types.txt. */
final class MediaTypes
{
    private const TOP_LEVEL = ['application', 'audio', 'example', 'font', 'haptics', 'image', 'message', 'model', 'multipart', 'text', 'video'];

    /** @var array<string, true>|null */
    private static ?array $registered = null;

    public static function isTopLevel(string $type): bool
    {
        return in_array(strtolower($type), self::TOP_LEVEL, true);
    }

    public static function isRegistered(string $mediaType): bool
    {
        if (self::$registered === null) {
            $lines = file(Resources::path('iana-media-types.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            self::$registered = array_fill_keys(array_filter($lines, fn (string $line) => ! str_starts_with($line, '#')), true);
        }

        return isset(self::$registered[strtolower($mediaType)]);
    }
}
