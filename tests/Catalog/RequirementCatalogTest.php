<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Catalog;

use KraenzleRitter\EarkIp\Catalog\Level;
use KraenzleRitter\EarkIp\Catalog\RequirementCatalog;
use KraenzleRitter\EarkIp\Catalog\Vocabulary;
use KraenzleRitter\EarkIp\Exception\EarkIpException;
use KraenzleRitter\EarkIp\SpecificationVersion;
use KraenzleRitter\EarkIp\Tests\TestCase;

final class RequirementCatalogTest extends TestCase
{
    public function test_requirement_from_the_profile(): void
    {
        $requirement = RequirementCatalog::for(SpecificationVersion::V2_2_0)->get('CSIP68');

        $this->assertSame(Level::MUST, $requirement->level);
        $this->assertSame('File mimetype', $requirement->name);
        $this->assertSame('mets/fileSec/fileGrp/file/@MIMETYPE', $requirement->location);
        $this->assertSame('1..1', $requirement->cardinality);
    }

    public function test_levels_differ_between_versions(): void
    {
        $this->assertSame(Level::MUST, RequirementCatalog::for(SpecificationVersion::V2_1_0)->get('CSIP96')->level);
        $this->assertSame(Level::SHOULD, RequirementCatalog::for(SpecificationVersion::V2_2_0)->get('CSIP96')->level);
        $this->assertSame(Level::MAY, RequirementCatalog::for(SpecificationVersion::V2_1_0)->get('SIP12')->level);
        $this->assertSame(Level::MUST, RequirementCatalog::for(SpecificationVersion::V2_2_0)->get('SIP12')->level);
    }

    public function test_structure_and_own_requirements_are_included(): void
    {
        $catalog = RequirementCatalog::for(SpecificationVersion::V2_1_0);

        $this->assertSame(Level::MUST, $catalog->get('CSIPSTR4')->level);
        $this->assertSame(Level::SHOULD, $catalog->get('CSIPSTR2')->level);
        $this->assertSame(Level::MUST, $catalog->get('EARKIP-XSD')->level);
    }

    public function test_aip_requirements_only_in_2_2_0(): void
    {
        $this->assertCount(7, RequirementCatalog::for(SpecificationVersion::V2_2_0)->all('AIP'));
        $this->assertSame([], RequirementCatalog::for(SpecificationVersion::V2_1_0)->all('AIP'));
    }

    public function test_unknown_requirement(): void
    {
        $this->expectException(EarkIpException::class);

        RequirementCatalog::for(SpecificationVersion::V2_2_0)->get('CSIP999');
    }

    public function test_vocabulary(): void
    {
        $this->assertTrue(Vocabulary::contains(SpecificationVersion::V2_2_0, Vocabulary::CONTENT_CATEGORY, 'Mixed'));
        $this->assertTrue(Vocabulary::contains(SpecificationVersion::V2_2_0, Vocabulary::CONTENT_INFORMATION_TYPE, 'cits3dpm_v1_0'));
        $this->assertFalse(Vocabulary::contains(SpecificationVersion::V2_1_0, Vocabulary::CONTENT_INFORMATION_TYPE, 'cits3dpm_v1_0'));
        $this->assertSame(['SIP', 'AIP', 'DIP', 'AIU', 'AIC'], Vocabulary::terms(SpecificationVersion::V2_2_0, Vocabulary::OAIS_PACKAGE_TYPE));
    }
}
