<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Writer;

use KraenzleRitter\EarkIp\Exception\EarkIpException;
use KraenzleRitter\EarkIp\Exception\UnsupportedVersionException;
use KraenzleRitter\EarkIp\Model\MetadataSection;
use KraenzleRitter\EarkIp\PackageType;
use KraenzleRitter\EarkIp\Reader\PackageReader;
use KraenzleRitter\EarkIp\SpecificationVersion;
use KraenzleRitter\EarkIp\Tests\TestCase;
use KraenzleRitter\EarkIp\Validation\Outcome;
use KraenzleRitter\EarkIp\Validation\ValidationReport;
use KraenzleRitter\EarkIp\Validation\Validator;
use KraenzleRitter\EarkIp\Writer\MetadataFile;
use KraenzleRitter\EarkIp\Writer\PackageBuilder;
use KraenzleRitter\EarkIp\Writer\PackageWriter;
use KraenzleRitter\EarkIp\Writer\PremisBuilder;
use KraenzleRitter\EarkIp\Writer\RepresentationBuilder;
use KraenzleRitter\EarkIp\Writer\StringContent;

final class PackageWriterTest extends TestCase
{
    private const ID = 'urn:uuid:5b2e7c1a-0000-4000-8000-000000000001';

    public function test_sip_as_zip_is_valid_without_warnings(): void
    {
        $zip = (new PackageWriter)->write($this->sip(), $this->temporaryFile());

        $report = (new Validator)->validate($zip);

        $this->assertClean($report);
        $this->assertSame(SpecificationVersion::V2_2_0, $report->version);
        $this->assertSame(PackageType::SIP, $report->type);
    }

    public function test_zip_has_a_single_root_folder_named_after_the_identifier(): void
    {
        $zip = new \ZipArchive;
        $zip->open((new PackageWriter)->write($this->sip(), $this->temporaryFile()));

        $roots = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $roots[explode('/', (string) $zip->getNameIndex($i))[0]] = true;
        }
        $this->assertSame([self::ID], array_keys($roots));
        $this->assertNotFalse($zip->locateName(self::ID.'/representations/rep1/metadata/'));
    }

    public function test_sip_as_folder(): void
    {
        $target = $this->temporaryDirectory().'/'.self::ID;

        (new PackageWriter)->write($this->sip(), $target);

        $this->assertFileExists($target.'/METS.xml');
        $this->assertFileExists($target.'/representations/rep1/data/reports/report 2025.txt');
        $this->assertFileExists($target.'/schemas/DILCISExtensionSIPMETS.xsd');
        $this->assertFileExists($target.'/schemas/premis-v3-0.xsd');
        $this->assertClean((new Validator)->validate($target));
    }

    public function test_written_package_reads_back(): void
    {
        $package = (new PackageReader)->read((new PackageWriter)->write($this->sip(), $this->temporaryFile()));

        $this->assertSame(self::ID, $package->objId());
        $this->assertSame('Annual report 2025', $package->mets->label);
        $this->assertSame('SA-1', $package->header()?->altRecordId('SUBMISSIONAGREEMENT'));
        $this->assertSame('Example Office', $package->header()?->agentsWithRole('ARCHIVIST')[0]->name);
        $types = array_map(fn ($reference) => $reference->type(), $package->mets->metadata(MetadataSection::Descriptive));
        $this->assertSame(['DC', 'anton-import-format'], $types);
        $this->assertSame('0.5', $package->mets->metadata(MetadataSection::Descriptive)[1]->mdTypeVersion);
        $this->assertSame('application/json', $package->mets->metadata(MetadataSection::Descriptive)[1]->mimeType);
        $this->assertSame(['representations/rep1/data/reports/report 2025.txt'], $package->representation('rep1')?->dataFiles());
        $this->assertSame('text/plain', $package->representation('rep1')?->mets?->files()[0]->mimeType);
    }

    public function test_writing_twice_gives_the_same_mets(): void
    {
        $created = new \DateTimeImmutable('2025-03-01T10:00:00Z');
        $first = $this->temporaryDirectory().'/a';
        $second = $this->temporaryDirectory().'/b';

        (new PackageWriter)->write($this->sip()->created($created), $first);
        (new PackageWriter)->write($this->sip()->created($created), $second);

        $this->assertSame(
            $this->withoutFileDates((string) file_get_contents($first.'/METS.xml')),
            $this->withoutFileDates((string) file_get_contents($second.'/METS.xml')),
        );
    }

    public function test_sip_2_1_0(): void
    {
        $zip = (new PackageWriter)->write($this->sip()->version(SpecificationVersion::V2_1_0), $this->temporaryFile());

        $report = (new Validator)->validate($zip);

        $this->assertClean($report);
        $this->assertSame(SpecificationVersion::V2_1_0, $report->version);
    }

    public function test_dip_and_aip_written_directly(): void
    {
        $aip = PackageBuilder::aip('aip-1')->premis($this->premis());
        $aip->representation('rep1', fn (RepresentationBuilder $r) => $r->file(new StringContent('x', 'a.txt'))->contentInformationType('OTHER', 'Plain files'));
        $report = (new Validator)->validate((new PackageWriter)->write($aip, $this->temporaryFile()));
        $this->assertTrue($report->isValid(), $this->describe($report));
        $this->assertSame(Outcome::Passed, $report->finding('AIPM7')?->outcome);

        $dip = PackageBuilder::dip('dip-1')->descriptiveMetadata(MetadataFile::of(new StringContent('<dc/>', 'dc.xml'), 'DC'));
        $dip->representation('rep1', fn (RepresentationBuilder $r) => $r->file(new StringContent('x', 'a.txt')));
        $report = (new Validator)->validate((new PackageWriter)->write($dip, $this->temporaryFile()));
        $this->assertTrue($report->isValid(), $this->describe($report));
        $this->assertSame(Outcome::Passed, $report->finding('DIP4')?->outcome);
    }

    public function test_package_without_documentation_warns_about_csip60_only(): void
    {
        $builder = PackageBuilder::sip('sip-1')->submitter('Office')->premis($this->premis())
            ->descriptiveMetadata(MetadataFile::of(new StringContent('<dc/>', 'dc.xml'), 'DC'));
        $builder->representation('rep1', fn (RepresentationBuilder $r) => $r->file(new StringContent('x', 'a.txt')));

        $report = (new Validator)->validate((new PackageWriter)->write($builder, $this->temporaryFile()));

        $this->assertTrue($report->isValid(), $this->describe($report));
        $this->assertSame(['CSIP60'], array_map(fn ($finding) => $finding->requirement->id, $report->warnings()));
    }

    public function test_documentation_folders_exist_even_when_empty(): void
    {
        $builder = PackageBuilder::sip('sip-1')->submitter('Office');
        $builder->representation('rep1', fn (RepresentationBuilder $r) => $r->file(new StringContent('x', 'a.txt')));

        $zip = new \ZipArchive;
        $zip->open((new PackageWriter)->write($builder, $this->temporaryFile()));

        // The Documentation division of the structMap names this folder (commons-ip, CSIP107).
        $this->assertNotFalse($zip->locateName('sip-1/documentation/'));
        $this->assertNotFalse($zip->locateName('sip-1/representations/rep1/documentation/'));
    }

    public function test_unregistered_media_type_becomes_octet_stream_and_the_puid_stays(): void
    {
        $builder = PackageBuilder::sip('sip-wav')->submitter('Office');
        $builder->representation('rep1', fn (RepresentationBuilder $r) => $r
            ->file(new StringContent('RIFF', 'interview.wav'), null, 'audio/x-wav', 'fmt/141')
            ->file(new StringContent('%PDF', 'brief.pdf'), null, 'application/pdf', 'fmt/276'));
        $zip = (new PackageWriter)->write($builder, $this->temporaryFile());

        $files = [];
        foreach ((new PackageReader)->read($zip)->representation('rep1')?->mets?->files() ?? [] as $file) {
            $files[basename((string) $file->href)] = $file;
        }
        $this->assertSame('application/octet-stream', $files['interview.wav']->mimeType);
        $this->assertSame('fmt/141', $files['interview.wav']->formatKey);
        $this->assertSame('PRONOM', $files['interview.wav']->formatRegistry);
        $this->assertSame('application/pdf', $files['brief.pdf']->mimeType);

        $report = (new Validator)->validate($zip);
        $this->assertTrue($report->isValid(), $this->describe($report));
        $this->assertSame(Outcome::Passed, $report->finding('SIP35')?->outcome);
        $this->assertNotSame(Outcome::Failed, $report->finding('CSIP68')?->outcome);
    }

    public function test_format_attributes_only_in_a_sip(): void
    {
        $builder = PackageBuilder::dip('dip-wav');
        $builder->representation('rep1', fn (RepresentationBuilder $r) => $r->file(new StringContent('RIFF', 'a.wav'), null, 'audio/x-wav', 'fmt/141'));
        $zip = (new PackageWriter)->write($builder, $this->temporaryFile());

        $file = (new PackageReader)->read($zip)->representation('rep1')?->mets?->files()[0];
        $this->assertSame('application/octet-stream', $file?->mimeType);
        $this->assertNull($file?->formatKey);
    }

    public function test_no_aip_2_1_0(): void
    {
        $this->expectException(UnsupportedVersionException::class);

        PackageBuilder::aip('aip-1')->version(SpecificationVersion::V2_1_0);
    }

    public function test_same_file_twice_is_refused(): void
    {
        $builder = PackageBuilder::sip('sip-1')
            ->documentation(new StringContent('a', 'readme.txt'))
            ->documentation(new StringContent('b', 'other.txt'), 'readme.txt');

        $this->assertCount(1, $builder->documentation);

        $builder->representation('rep1')->file(new StringContent('x', 'a.txt'))->file(new StringContent('y', 'b.txt'), 'a.txt');
        $this->assertCount(1, $builder->representations['rep1']->data);
    }

    public function test_target_must_be_new_or_empty(): void
    {
        $target = $this->temporaryDirectory();
        file_put_contents($target.'/x', 'x');

        $this->expectException(EarkIpException::class);

        (new PackageWriter)->write($this->sip(), $target);
    }

    public function test_representation_path_must_stay_inside(): void
    {
        $this->expectException(EarkIpException::class);

        (new RepresentationBuilder('rep1'))->file(new StringContent('x', 'a.txt'), '../a.txt');
    }

    public function test_representation_name_must_be_a_folder_name(): void
    {
        $this->expectException(EarkIpException::class);

        new RepresentationBuilder('a/b');
    }

    public function test_representation_from_a_folder(): void
    {
        $source = $this->temporaryDirectory('files');
        $this->writeFiles($source, ['a.txt' => 'a', 'letters/1901.txt' => 'b']);

        $representation = (new RepresentationBuilder('rep1'))->directory($source);

        $this->assertSame(['a.txt', 'letters/1901.txt'], array_keys($representation->data));
    }

    private function sip(): PackageBuilder
    {
        $fixture = self::fixture('packages/sip-2.2.0');
        $builder = PackageBuilder::sip(self::ID)
            ->label('Annual report 2025')
            ->contentCategory('Textual works – Digital')
            ->contentInformationType('OTHER', 'Plain files')
            ->software('eark-ip tests', '1.0')
            ->archivalCreator('Example Office', identificationCode: 'EX-1')
            ->submitter('Example Office')
            ->contactPerson('Jane Doe', ['jane@example.org'])
            ->preservationAgent('Example Archives')
            ->submissionAgreement('SA-1')
            ->descriptiveMetadata(MetadataFile::of($fixture.'/metadata/descriptive/dc.xml', 'DC'))
            ->descriptiveMetadata(MetadataFile::of(new StringContent('{"version":"0.5"}', 'metadata.json'), 'anton-import-format', '0.5'))
            ->premis($this->premis())
            ->documentation($fixture.'/documentation/readme.txt');
        $builder->representation('rep1', fn (RepresentationBuilder $r) => $r->file($fixture.'/representations/rep1/data/report.txt', 'reports/report 2025.txt'));

        return $builder;
    }

    private function premis(): PremisBuilder
    {
        return (new PremisBuilder)
            ->agent('eark-ip', 'eark-ip', 'software', '1.0')
            ->file('representations/rep1/data/report.txt', 19, str_repeat('0', 64), 'x-fmt/111')
            ->event('ev-1', 'creation', new \DateTimeImmutable('2025-03-01T10:00:00Z'), agents: ['eark-ip' => 'executing program']);
    }

    private function withoutFileDates(string $mets): string
    {
        return (string) preg_replace('/ CREATED="[^"]*"/', '', $mets);
    }

    private function assertClean(ValidationReport $report): void
    {
        $this->assertTrue($report->isValid(), $this->describe($report));
        $this->assertSame([], $report->warnings(), $this->describe($report));
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
