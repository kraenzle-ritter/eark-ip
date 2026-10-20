<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Source;

use KraenzleRitter\EarkIp\Exception\PackageNotFoundException;

/** Opens a package from a path: a folder or a ZIP. */
final class Sources
{
    public static function open(string|PackageSource $package, ?Limits $limits = null): PackageSource
    {
        if ($package instanceof PackageSource) {
            return $package;
        }
        if (is_dir($package)) {
            return new DirectorySource($package);
        }
        if (is_file($package)) {
            return new ZipSource($package, $limits);
        }

        throw PackageNotFoundException::forPath($package);
    }
}
