<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp;

/**
 * Values of mets/metsHdr/@csip:OAISPACKAGETYPE (CSIP vocabulary
 * OAISPackageType).
 */
enum PackageType: string
{
    case SIP = 'SIP';
    case AIP = 'AIP';
    case DIP = 'DIP';
    case AIU = 'AIU';
    case AIC = 'AIC';
}
