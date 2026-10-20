<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Reader;

use DOMXPath;
use KraenzleRitter\EarkIp\Exception\EarkIpException;
use KraenzleRitter\EarkIp\Exception\XmlException;
use KraenzleRitter\EarkIp\Model\MetadataSection;
use KraenzleRitter\EarkIp\PackageType;
use KraenzleRitter\EarkIp\Reader\PackageReader;
use KraenzleRitter\EarkIp\Specification;
use KraenzleRitter\EarkIp\SpecificationVersion;
use KraenzleRitter\EarkIp\Tests\TestCase;
use KraenzleRitter\EarkIp\Tests\Validation\MutatesPackages;
use KraenzleRitter\EarkIp\Xml\Ns;

final class PackageReaderTest extends TestCase
{
    use MutatesPackages;

    public function test_reads_the_package_model(): void
    {
        $package = (new PackageReader)->read(self::fixture('packages/sip-2.2.0'));

        $this->assertSame('sip-2.2.0', $package->name);
        $this->assertSame('sip-2.2.0', $package->objId());
        $this->assertSame(PackageType::SIP, $package->type);
        $this->assertSame(Specification::SIP, $package->specification());
        $this->assertSame(SpecificationVersion::V2_2_0, $package->version);
        $this->assertTrue($package->versionDetected);
        $this->assertSame('Annual report 2025', $package->mets->label);
        $this->assertSame('Textual works – Digital', $package->mets->type);
        $this->assertSame('OTHER', $package->mets->contentInformationType);
        $this->assertSame('Plain files', $package->mets->otherContentInformationType);
    }

    public function test_reads_the_header(): void
    {
        $header = (new PackageReader)->read(self::fixture('packages/sip-2.2.0'))->header();

        $this->assertNotNull($header);
        $this->assertSame('2025-03-01T10:00:00Z', $header->createDate);
        $this->assertSame('NEW', $header->recordStatus);
        $this->assertSame('SIP', $header->oaisPackageType);
        $this->assertSame('SA-2025-01', $header->altRecordId('SUBMISSIONAGREEMENT'));
        $this->assertCount(4, $header->agents);
        $software = $header->agentsWithRole('CREATOR')[0];
        $this->assertSame('eark-ip fixtures', $software->name);
        $this->assertSame('1.0', $software->note('SOFTWARE VERSION'));
        $this->assertSame('EX-1', $header->agentsWithRole('ARCHIVIST')[0]->note('IDENTIFICATIONCODE'));
    }

    public function test_reads_metadata_references(): void
    {
        $mets = (new PackageReader)->read(self::fixture('packages/sip-2.2.0'))->mets;

        $descriptive = $mets->metadata(MetadataSection::Descriptive);
        $this->assertCount(1, $descriptive);
        $this->assertSame('DC', $descriptive[0]->type());
        $this->assertSame('metadata/descriptive/dc.xml', $descriptive[0]->href);
        $this->assertSame('CURRENT', $descriptive[0]->status);
        $this->assertSame(64, strlen((string) $descriptive[0]->checksum));
        $this->assertSame('PREMIS', $mets->metadata(MetadataSection::Provenance)[0]->mdType);
        $this->assertTrue(MetadataSection::Provenance->isAdministrative());
    }

    public function test_reads_files_and_structure(): void
    {
        $mets = (new PackageReader)->read(self::fixture('packages/sip-2.2.0'))->mets;

        $this->assertCount(3, $mets->fileGroups);
        $this->assertTrue($mets->fileGroup('Representations/rep1')?->isRepresentations());
        $readme = $mets->fileGroup('Documentation')?->files[0];
        $this->assertSame('documentation/readme.txt', $readme?->href);
        $this->assertSame('text/plain', $readme?->mimeType);
        $this->assertSame('SHA-256', $readme?->checksumType);
        $this->assertSame(filesize(self::fixture('packages/sip-2.2.0/documentation/readme.txt')), $readme?->size);

        $structMap = $mets->structMap('CSIP');
        $this->assertNotNull($structMap);
        $this->assertSame('Metadata', $structMap->divisions[0]->children[0]->label);
        $this->assertSame(['grp-documentation'], $structMap->divisions[0]->child('Documentation')?->fileIds);
        $this->assertSame('representations/rep1/METS.xml', $structMap->pointers()[0]->href);
    }

    public function test_reads_representations(): void
    {
        $package = (new PackageReader)->read(self::fixture('packages/sip-2.2.0'));

        $this->assertCount(1, $package->representations);
        $representation = $package->representation('rep1');
        $this->assertNotNull($representation);
        $this->assertSame('representations/rep1', $representation->path);
        $this->assertSame('rep1', $representation->mets?->objId);
        $this->assertSame(['representations/rep1/data/report.txt'], $representation->dataFiles());
        $this->assertSame('representations/rep1', $representation->mets?->directory());
        $this->assertSame('representations/rep1/data/report.txt', $representation->mets?->resolve('data/report.txt'));
    }

    public function test_reads_an_invalid_package(): void
    {
        $root = $this->copyFixture('sip-2.2.0');
        $this->editXml($root.'/METS.xml', fn (DOMXPath $x) => self::removeAttribute($x, '/mets:mets', 'OBJID'));
        file_put_contents($root.'/representations/rep1/METS.xml', '<broken');

        $package = (new PackageReader)->read($root);

        $this->assertNull($package->objId());
        $this->assertNull($package->representation('rep1')?->mets);
        $this->assertStringContainsString('not well-formed', (string) $package->representation('rep1')?->metsError);
    }

    public function test_version_from_unversioned_sip_profile(): void
    {
        $this->assertSame(SpecificationVersion::V2_1_0, (new PackageReader)->read(self::fixture('packages/sip-2.1.0'))->version);
    }

    public function test_undetected_version_falls_back_to_the_default_or_the_given_one(): void
    {
        $root = $this->copyFixture('sip-2.2.0');
        $this->editXml($root.'/METS.xml', fn (DOMXPath $x) => self::setAttribute($x, '/mets:mets', 'PROFILE', 'https://example.org/profile.xml'));

        $package = (new PackageReader)->read($root);
        $this->assertFalse($package->versionDetected);
        $this->assertSame(SpecificationVersion::V2_2_0, $package->version);
        $this->assertSame(SpecificationVersion::V2_1_0, (new PackageReader)->read($root, SpecificationVersion::V2_1_0)->version);
    }

    public function test_aip_with_parent_and_submission(): void
    {
        $root = $this->copyFixture('sip-2.2.0');
        mkdir($root.'/submission');
        foreach (['METS.xml', 'documentation', 'schemas', 'metadata', 'representations'] as $item) {
            exec('cp -R '.escapeshellarg($root.'/'.$item).' '.escapeshellarg($root.'/submission/'.$item));
        }
        $this->editXml($root.'/METS.xml', function (DOMXPath $x, \DOMDocument $document) {
            self::element($x, '/mets:mets/mets:metsHdr')->setAttributeNS(Ns::CSIP, 'csip:OAISPACKAGETYPE', 'AIP');
            $map = $document->createElementNS(Ns::METS, 'structMap');
            $map->setAttribute('LABEL', 'parent AIP');
            $map->setAttribute('TYPE', 'PHYSICAL');
            $div = $map->appendChild($document->createElementNS(Ns::METS, 'div'));
            $mptr = $div->appendChild($document->createElementNS(Ns::METS, 'mptr'));
            assert($mptr instanceof \DOMElement);
            $mptr->setAttribute('LOCTYPE', 'OTHER');
            $mptr->setAttributeNS(Ns::XLINK, 'xlink:href', 'urn:uuid:3a487ce5-63cf-4000-9522-7288e208e2bc');
            self::element($x, '/mets:mets')->appendChild($map);
        });

        $package = (new PackageReader)->read($root);

        $this->assertSame(PackageType::AIP, $package->type);
        $this->assertSame(['urn:uuid:3a487ce5-63cf-4000-9522-7288e208e2bc'], $package->parentAips);
        $this->assertSame(PackageType::SIP, $package->submission?->type);
        $this->assertSame('sip-2.2.0', $package->submission?->objId());
    }

    public function test_zip_and_folder_give_the_same_model(): void
    {
        $root = self::fixture('packages/sip-2.2.0');
        $zip = $this->temporaryFile();
        $archive = new \ZipArchive;
        $archive->open($zip, \ZipArchive::CREATE);
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $archive->addFile($file->getPathname(), 'sip-2.2.0/'.substr($file->getPathname(), strlen($root) + 1));
        }
        $archive->close();

        $fromFolder = (new PackageReader)->read($root);
        $fromZip = (new PackageReader)->read($zip);

        $this->assertEquals($fromFolder->mets, $fromZip->mets);
        $this->assertEquals($fromFolder->representations, $fromZip->representations);
        $this->assertSame($fromFolder->name, $fromZip->name);
    }

    public function test_package_without_mets(): void
    {
        $this->expectException(EarkIpException::class);
        $this->expectExceptionMessage('no METS.xml');

        (new PackageReader)->read($this->temporaryDirectory());
    }

    public function test_malformed_package_mets(): void
    {
        $root = $this->temporaryDirectory();
        file_put_contents($root.'/METS.xml', '<mets');

        $this->expectException(XmlException::class);

        (new PackageReader)->read($root);
    }
}
