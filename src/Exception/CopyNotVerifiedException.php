<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Exception;

/**
 * A file could not be written into a package, or what was written does not
 * match what was read: a short read, a failed write (disk full), or a copy
 * whose checksum differs. Nothing is left at the target.
 */
final class CopyNotVerifiedException extends EarkIpException
{
    public static function failed(string $source, string $target, string $reason): self
    {
        return new self("Could not copy {$source} to {$target}: {$reason}.");
    }

    public static function mismatch(string $source, string $target, string $expected, string $actual): self
    {
        return new self("Copy of {$source} at {$target} does not match its source (SHA-256 {$expected}, copy {$actual}).");
    }
}
