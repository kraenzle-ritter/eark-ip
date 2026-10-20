<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation\Rules;

/**
 * Compares an identifier with a folder name. CSIP1 allows that an
 * identifier such as "urn:uuid:…" cannot be used as a folder name as it
 * is, so characters that file systems refuse count as equal to any
 * replacement.
 */
final class Names
{
    public static function match(string $identifier, string $folder): bool
    {
        if ($identifier === $folder) {
            return true;
        }

        $mask = fn (string $value) => preg_replace('#[^A-Za-z0-9._-]#', '_', $value);

        return $mask($identifier) === $mask($folder);
    }
}
