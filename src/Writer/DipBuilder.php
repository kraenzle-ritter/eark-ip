<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

use KraenzleRitter\EarkIp\Reader\PackageReader;
use KraenzleRitter\EarkIp\Source\PackageSource;
use KraenzleRitter\EarkIp\Source\Sources;

/**
 * Builds a DIP from an AIP: the current descriptive metadata (DIP4) and
 * the chosen representations. Preservation metadata comes along unless
 * left out.
 */
final class DipBuilder
{
    /** @param  list<string>|null  $representations  names to deliver, null for all */
    public static function fromAip(
        string|PackageSource $aip,
        string $dipId,
        ?array $representations = null,
        bool $preservation = true,
    ): PackageBuilder {
        $source = Sources::open($aip);
        $package = (new PackageReader)->read($source);

        $dip = PackageBuilder::dip($dipId);
        Derivation::copy($package, $source, $dip, $representations, $preservation, onlyCurrent: true);

        return $dip;
    }
}
