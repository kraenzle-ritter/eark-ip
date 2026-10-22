<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp;

/**
 * What a representation is for. CSIP does not say; this is proposed
 * from PREMIS or the name (see RepresentationRoles).
 */
enum RepresentationFunction: string
{
    /** The files to preserve, e.g. after a normalisation. */
    case Master = 'master';

    /** The files as delivered, kept beside the master. */
    case Original = 'original';

    /** Files derived for use, e.g. JPEG from TIFF. */
    case Access = 'access';

    case Unknown = 'unknown';
}
