<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests;

use KraenzleRitter\EarkIp\Exception\UnsupportedVersionException;
use KraenzleRitter\EarkIp\PackageType;
use KraenzleRitter\EarkIp\Resources;
use KraenzleRitter\EarkIp\Specification;
use KraenzleRitter\EarkIp\SpecificationVersion;

final class SpecificationTest extends TestCase
{
    public function test_sip_profile_url_is_versioned_from_2_2_0(): void
    {
        $this->assertSame('https://earksip.dilcis.eu/profile/E-ARK-SIP.xml', Specification::SIP->profileUrl(SpecificationVersion::V2_1_0));
        $this->assertSame('https://earksip.dilcis.eu/profile/E-ARK-SIP-v2-2-0.xml', Specification::SIP->profileUrl(SpecificationVersion::V2_2_0));
    }

    public function test_aip_accepts_the_misspelt_profile_url_of_the_specification(): void
    {
        $this->assertContains(
            'https://earkdip.dilcis.eu/profile/E-ARK-AIP-v2-2-0.xml',
            Specification::AIP->acceptedProfileUrls(SpecificationVersion::V2_2_0),
        );
    }

    public function test_there_is_no_aip_2_1_0(): void
    {
        $this->assertFalse(Specification::AIP->supports(SpecificationVersion::V2_1_0));

        $this->expectException(UnsupportedVersionException::class);
        Specification::AIP->profileUrl(SpecificationVersion::V2_1_0);
    }

    public function test_package_type_maps_to_specification(): void
    {
        $this->assertSame(Specification::DIP, Specification::forPackageType(PackageType::DIP));
        $this->assertSame(Specification::CSIP, Specification::forPackageType(PackageType::AIC));
    }

    public function test_every_profile_file_is_shipped(): void
    {
        foreach (SpecificationVersion::cases() as $version) {
            foreach (Specification::cases() as $specification) {
                if ($specification->supports($version)) {
                    $this->assertFileExists(Resources::specPath($version, 'profiles/'.$specification->profileFile($version)));
                }
            }
        }
    }
}
