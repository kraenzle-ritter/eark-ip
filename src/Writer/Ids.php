<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

/**
 * XML IDs for METS elements. Derived from the package identifier, the
 * kind of element and its path, so that writing the same package again
 * yields the same IDs.
 */
final class Ids
{
    public static function for(string $kind, string $objId, string $path): string
    {
        // commons-ip requires AIP file IDs to start with "ID" (its AIP9); all IDs do.
        return 'ID-'.$kind.'-'.substr(hash('sha256', $objId."\0".$kind."\0".$path), 0, 16);
    }
}
