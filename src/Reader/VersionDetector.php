<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Reader;

use KraenzleRitter\EarkIp\Model\MetsDocument;
use KraenzleRitter\EarkIp\PackageType;
use KraenzleRitter\EarkIp\SpecificationVersion;

/**
 * Package type from mets/metsHdr/@csip:OAISPACKAGETYPE, version from
 * mets/@PROFILE. Only versioned profile URLs name the version; the SIP
 * profile became versioned with 2.2.0, so an unversioned SIP profile
 * means 2.1.0. For everything else the version stays undetected.
 */
final class VersionDetector
{
    public static function type(MetsDocument $mets): ?PackageType
    {
        $value = $mets->header?->oaisPackageType;

        if ($value !== null) {
            return PackageType::tryFrom(strtoupper(trim($value)));
        }

        // Without @csip:OAISPACKAGETYPE the profile still names the type.
        return preg_match('#/E-ARK-(SIP|AIP|DIP)(-v[0-9-]+)?\.xml$#', (string) $mets->profile, $match)
            ? PackageType::from($match[1])
            : null;
    }

    /**
     * The type whose specification the package claims to follow: from
     * mets/@PROFILE when it names SIP, AIP or DIP, else from
     *
     * @csip:OAISPACKAGETYPE. Used to pick the rules, so that a SIP
     * profile with a wrong package type fails SIP4 instead of being
     * judged as another type.
     */
    public static function claimedType(MetsDocument $mets): ?PackageType
    {
        return preg_match('#/E-ARK-(SIP|AIP|DIP)(-v[0-9-]+)?\.xml$#', (string) $mets->profile, $match)
            ? PackageType::from($match[1])
            : self::type($mets);
    }

    public static function version(MetsDocument $mets): ?SpecificationVersion
    {
        $profile = (string) $mets->profile;

        foreach (SpecificationVersion::cases() as $version) {
            if (str_ends_with($profile, '-'.$version->fileSuffix().'.xml')) {
                return $version;
            }
        }

        if (preg_match('#earksip\.dilcis\.eu/profile/E-ARK-SIP\.xml$#', $profile)) {
            return SpecificationVersion::V2_1_0;
        }

        return null;
    }
}
