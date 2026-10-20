<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp;

use KraenzleRitter\EarkIp\Exception\UnsupportedVersionException;

/**
 * Versions of the E-ARK specifications (CSIP, SIP, AIP, DIP) this
 * package reads, validates and writes. All four specifications share
 * the version number; there is no AIP 2.1.0.
 */
enum SpecificationVersion: string
{
    case V2_1_0 = '2.1.0';
    case V2_2_0 = '2.2.0';

    public static function default(): self
    {
        return self::V2_2_0;
    }

    public static function fromString(string $version): self
    {
        return self::tryFrom($version) ?? throw new UnsupportedVersionException(sprintf(
            'E-ARK version %s is not supported. Supported versions: %s.',
            $version,
            implode(', ', array_map(fn (self $case) => $case->value, self::cases())),
        ));
    }

    /** The suffix the DILCIS Board uses in versioned profile file names, e.g. "v2-2-0". */
    public function fileSuffix(): string
    {
        return 'v'.str_replace('.', '-', $this->value);
    }
}
