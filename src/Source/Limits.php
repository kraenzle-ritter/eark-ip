<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Source;

/**
 * Limits for packages from elsewhere. A ZIP that exceeds them is refused
 * before any entry is read.
 */
final class Limits
{
    public function __construct(
        public readonly int $maxEntries = 1_000_000,
        public readonly ?int $maxUncompressedBytes = null,
    ) {}
}
