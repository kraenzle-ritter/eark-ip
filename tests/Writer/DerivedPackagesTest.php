<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Writer;

use KraenzleRitter\EarkIp\Model\MetadataSection;
use KraenzleRitter\EarkIp\PackageType;
use KraenzleRitter\EarkIp\Reader\PackageReader;
use KraenzleRitter\EarkIp\Tests\TestCase;
use KraenzleRitter\EarkIp\Validation\Outcome;
use KraenzleRitter\EarkIp\Validation\ValidationReport;
use KraenzleRitter\EarkIp\Validation\Validator;
use KraenzleRitter\EarkIp\Writer\AipBuilder;
use KraenzleRitter\EarkIp\Writer\DipBuilder;
use KraenzleRitter\EarkIp\Writer\PackageBuilder;
use KraenzleRitter\EarkIp\Writer\PackageWriter;
use KraenzleRitter\EarkIp\Writer\RepresentationBuilder;
use KraenzleRitter\EarkIp\Writer\StringContent;

final class DerivedPackagesTest extends TestCase
{
    private const AIP = 'urn:uuid:aaaaaaaa-0000-4000-8000-000000000002';

    public function test_aip_from_sip(): void
    {
        $aip = $this->aip();

        $report = (new Validator)->validate($aip);
        $this->assertTrue($report->isValid(), $this->describe($report));
        $this->assertSame([], $report->warnings(), $this->describe($report));
        $this->assertSame(['CSIP', 'AIP'], $report->specifications);
        foreach (['AIPM2', 'AIPM3', 'AIPM5', 'AIPM6', 'AIPM7'] as $id) {
            $this->assertSame(Outcome::Passed, $report->finding($id)?->outcome, $id);
        }

        $package = (new PackageReader)->read($aip);
        $this->assertSame(PackageType::AIP, $package->type);
        $this->assertSame(['urn:uuid:bbbbbbbb-0000-4000-8000-000000000003'], $package->parentAips);
        $this->assertSame('sip-2.2.0', $package->submission?->objId());
        $this->assertSame('Annual report 2025', $package->mets->label);
        $this->assertSame(['representations/rep1/data/report.txt'], $package->representation('rep1')?->dataFiles());
        $provenance = array_map(fn ($reference) => basename((string) $reference->href), $package->mets->metadata(MetadataSection::Provenance));
        $this->assertSame(['premis.xml', 'aip-premis.xml'], $provenance);
    }

    public function test_aip_without_submission(): void
    {
        $target = $this->temporaryFile();
        (new PackageWriter)->write(AipBuilder::fromSip(self::fixture('packages/sip-2.2.0'), self::AIP, keepSubmission: false), $target);

        $this->assertNull((new PackageReader)->read($target)->submission);
        $this->assertTrue((new Validator)->validate($target)->isValid());
    }

    public function test_dip_from_aip_with_chosen_representation(): void
    {
        $sip = PackageBuilder::sip('sip-two')->submitter('Office');
        $sip->representation('master', fn (RepresentationBuilder $r) => $r->file(new StringContent('tiff', 'a.tif')));
        $sip->representation('access', fn (RepresentationBuilder $r) => $r->file(new StringContent('jpeg', 'a.jpg')));
        $sipPath = (new PackageWriter)->write($sip, $this->temporaryFile());
        $aipPath = (new PackageWriter)->write(AipBuilder::fromSip($sipPath, 'aip-two'), $this->temporaryFile());

        $dipPath = (new PackageWriter)->write(DipBuilder::fromAip($aipPath, 'dip-two', ['access']), $this->temporaryFile());

        $dip = (new PackageReader)->read($dipPath);
        $this->assertSame(PackageType::DIP, $dip->type);
        $this->assertSame(['access'], array_map(fn ($representation) => $representation->name, $dip->representations));
        $this->assertNull($dip->submission);
        $report = (new Validator)->validate($dipPath);
        $this->assertTrue($report->isValid(), $this->describe($report));
    }

    public function test_dip_from_the_aip_of_the_reference_sip(): void
    {
        $dip = (new PackageWriter)->write(DipBuilder::fromAip($this->aip(), 'dip-1'), $this->temporaryFile());

        $report = (new Validator)->validate($dip);

        $this->assertTrue($report->isValid(), $this->describe($report));
        $this->assertSame([], $report->warnings(), $this->describe($report));
        $this->assertSame(Outcome::Passed, $report->finding('DIP4')?->outcome);
    }

    private function aip(): string
    {
        return (new PackageWriter)->write(
            AipBuilder::fromSip(
                self::fixture('packages/sip-2.2.0'),
                self::AIP,
                parentAip: 'urn:uuid:bbbbbbbb-0000-4000-8000-000000000003',
                ingested: new \DateTimeImmutable('2025-04-01T00:00:00Z'),
            ),
            $this->temporaryFile(),
        );
    }

    private function describe(ValidationReport $report): string
    {
        $lines = [];
        foreach ($report->withOutcome(Outcome::Failed) as $finding) {
            foreach ($finding->violations as $violation) {
                $lines[] = "{$finding->requirement->id}: {$violation->message}";
            }
        }

        return implode("\n", $lines);
    }
}
