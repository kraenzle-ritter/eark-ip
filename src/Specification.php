<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp;

use KraenzleRitter\EarkIp\Exception\UnsupportedVersionException;

/**
 * The four E-ARK specifications. CSIP is the common base; SIP, AIP and
 * DIP add requirements for their package type.
 */
enum Specification: string
{
    case CSIP = 'CSIP';
    case SIP = 'SIP';
    case AIP = 'AIP';
    case DIP = 'DIP';

    public static function forPackageType(PackageType $type): self
    {
        return match ($type) {
            PackageType::SIP => self::SIP,
            PackageType::AIP => self::AIP,
            PackageType::DIP => self::DIP,
            PackageType::AIU, PackageType::AIC => self::CSIP,
        };
    }

    public function supports(SpecificationVersion $version): bool
    {
        return $this !== self::AIP || $version === SpecificationVersion::V2_2_0;
    }

    public function assertSupports(SpecificationVersion $version): void
    {
        if (! $this->supports($version)) {
            throw new UnsupportedVersionException(sprintf(
                'E-ARK %s %s does not exist. Supported versions: 2.2.0.',
                $this->value,
                $version->value,
            ));
        }
    }

    /**
     * The value a package writes into mets/@PROFILE, as the profile of
     * this version prescribes it.
     */
    public function profileUrl(SpecificationVersion $version): string
    {
        $this->assertSupports($version);

        return match ($this) {
            self::CSIP => 'https://earkcsip.dilcis.eu/profile/E-ARK-CSIP.xml',
            self::SIP => $version === SpecificationVersion::V2_1_0
                ? 'https://earksip.dilcis.eu/profile/E-ARK-SIP.xml'
                : 'https://earksip.dilcis.eu/profile/E-ARK-SIP-v2-2-0.xml',
            self::AIP => 'https://earkaip.dilcis.eu/profile/E-ARK-AIP-v2-2-0.xml',
            self::DIP => 'https://earkdip.dilcis.eu/profile/E-ARK-DIP.xml',
        };
    }

    /**
     * Values accepted in mets/@PROFILE when reading. The specifications
     * disagree with themselves in places: the AIP profile names itself
     * under earkdip.dilcis.eu, the DIP profile names a versioned file
     * while its requirement DIP2 names the unversioned one.
     *
     * @return list<string>
     */
    public function acceptedProfileUrls(SpecificationVersion $version): array
    {
        return match ($this) {
            self::AIP => [
                $this->profileUrl($version),
                'https://earkdip.dilcis.eu/profile/E-ARK-AIP-v2-2-0.xml',
            ],
            self::DIP => $version === SpecificationVersion::V2_2_0
                ? [$this->profileUrl($version), 'https://earkdip.dilcis.eu/profile/E-ARK-DIP-v2-2-0.xml']
                : [$this->profileUrl($version)],
            default => [$this->profileUrl($version)],
        };
    }

    /** File name of the METS profile under resources/spec/<version>/profiles/. */
    public function profileFile(SpecificationVersion $version): string
    {
        $this->assertSupports($version);

        return sprintf('E-ARK-%s-%s.xml', $this->value, $version->fileSuffix());
    }
}
