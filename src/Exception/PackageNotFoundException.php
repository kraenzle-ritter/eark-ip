<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Exception;

final class PackageNotFoundException extends EarkIpException
{
    public static function forPath(string $path): self
    {
        return new self("No package found at {$path}.");
    }
}
