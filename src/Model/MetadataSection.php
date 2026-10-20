<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** The METS sections that point to metadata files, by element name. */
enum MetadataSection: string
{
    case Descriptive = 'dmdSec';
    case Technical = 'techMD';
    case Rights = 'rightsMD';
    case Source = 'sourceMD';
    case Provenance = 'digiprovMD';

    public function isAdministrative(): bool
    {
        return $this !== self::Descriptive;
    }
}
