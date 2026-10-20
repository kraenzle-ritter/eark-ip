<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Validation;

use DOMXPath;
use KraenzleRitter\EarkIp\PackageType;
use KraenzleRitter\EarkIp\SpecificationVersion;
use KraenzleRitter\EarkIp\Tests\TestCase;
use KraenzleRitter\EarkIp\Validation\Outcome;
use KraenzleRitter\EarkIp\Validation\Severity;
use KraenzleRitter\EarkIp\Validation\ValidationReport;
use KraenzleRitter\EarkIp\Validation\Validator;
use KraenzleRitter\EarkIp\Xml\Ns;
use PHPUnit\Framework\Attributes\DataProvider;

final class ValidatorTest extends TestCase
{
    use MutatesPackages;

    public function test_reference_packages_are_valid_without_warnings(): void
    {
        foreach (['sip-2.2.0' => SpecificationVersion::V2_2_0, 'sip-2.1.0' => SpecificationVersion::V2_1_0] as $name => $version) {
            $report = (new Validator)->validate(self::fixture('packages/'.$name));

            $this->assertTrue($report->isValid(), $name.': '.$this->describe($report));
            $this->assertSame([], $report->warnings(), $name.': '.$this->describe($report));
            $this->assertSame($version, $report->version);
            $this->assertTrue($report->versionDetected);
            $this->assertSame(PackageType::SIP, $report->type);
            $this->assertSame(['CSIP', 'SIP'], $report->specifications);
            $this->assertSame([], $report->withOutcome(Outcome::NotChecked));
        }
    }

    public function test_zipped_package_gives_the_same_result(): void
    {
        $zip = $this->temporaryFile();
        $archive = new \ZipArchive;
        $archive->open($zip, \ZipArchive::CREATE);
        $root = self::fixture('packages/sip-2.2.0');
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $archive->addFile($file->getPathname(), 'sip-2.2.0/'.substr($file->getPathname(), strlen($root) + 1));
        }
        $archive->addEmptyDir('sip-2.2.0/representations/rep1/metadata');
        $archive->close();

        $report = (new Validator)->validate($zip);

        $this->assertTrue($report->isValid(), $this->describe($report));
        $this->assertSame([], $report->warnings(), $this->describe($report));
        $this->assertSame(Outcome::Passed, $report->finding('CSIPSTR3')?->outcome);
    }

    /**
     * @return iterable<string, array{callable(DOMXPath): void, string, Severity, string}>
     */
    public static function packageMetsViolations(): iterable
    {
        $mets = '/mets:mets';
        $hdr = '/mets:mets/mets:metsHdr';
        $file = "/mets:mets/mets:fileSec/mets:fileGrp[@USE='Documentation']/mets:file";
        $dmd = '/mets:mets/mets:dmdSec';
        $dmdRef = '/mets:mets/mets:dmdSec/mets:mdRef';
        $provRef = '/mets:mets/mets:amdSec/mets:digiprovMD/mets:mdRef';
        $div = "/mets:mets/mets:structMap[@LABEL='CSIP']/mets:div";

        yield 'CSIP1 OBJID missing' => [fn ($x) => self::removeAttribute($x, $mets, 'OBJID'), 'CSIP1', Severity::Error, 'OBJID'];
        yield 'CSIP1 OBJID differs from folder' => [fn ($x) => self::setAttribute($x, $mets, 'OBJID', 'other'), 'CSIP1', Severity::Warning, 'root folder'];
        yield 'CSIPSTR2 folder not named after OBJID' => [fn ($x) => self::setAttribute($x, $mets, 'OBJID', 'other'), 'CSIPSTR2', Severity::Warning, 'not named'];
        yield 'CSIP2 TYPE not in vocabulary' => [fn ($x) => self::setAttribute($x, $mets, 'TYPE', 'Letters'), 'CSIP2', Severity::Error, 'Content Category'];
        yield 'CSIP2 Other without OTHERTYPE' => [fn ($x) => self::setAttribute($x, $mets, 'TYPE', 'OTHER'), 'CSIP2', Severity::Error, 'OTHERTYPE'];
        yield 'CSIP3 OTHERTYPE without Other' => [fn ($x) => self::element($x, $mets)->setAttributeNS(Ns::CSIP, 'csip:OTHERTYPE', 'Letters'), 'CSIP3', Severity::Warning, 'not "Other"'];
        yield 'CSIP4 content information type unknown' => [fn ($x) => self::element($x, $mets)->setAttributeNS(Ns::CSIP, 'csip:CONTENTINFORMATIONTYPE', 'SIARD9'), 'CSIP4', Severity::Error, 'SIARD9'];
        yield 'CSIP5 OTHER without other value' => [fn ($x) => self::removeAttribute($x, $mets, 'OTHERCONTENTINFORMATIONTYPE', Ns::CSIP), 'CSIP5', Severity::Error, 'OTHERCONTENTINFORMATIONTYPE'];
        yield 'CSIP6 PROFILE not a URL' => [fn ($x) => self::setAttribute($x, $mets, 'PROFILE', 'E-ARK SIP'), 'CSIP6', Severity::Error, 'not a URL'];
        yield 'CSIP117 no header' => [fn ($x) => self::remove($x, $hdr), 'CSIP117', Severity::Error, 'metsHdr'];
        yield 'CSIP7 no CREATEDATE' => [fn ($x) => self::removeAttribute($x, $hdr, 'CREATEDATE'), 'CSIP7', Severity::Error, 'CREATEDATE'];
        yield 'CSIP8 LASTMODDATE missing' => [fn ($x) => self::removeAttribute($x, $hdr, 'LASTMODDATE'), 'CSIP8', Severity::Warning, 'LASTMODDATE'];
        yield 'CSIP8 LASTMODDATE in the future' => [fn ($x) => self::setAttribute($x, $hdr, 'LASTMODDATE', '2999-01-01T00:00:00Z'), 'CSIP8', Severity::Error, 'future'];
        yield 'CSIP9 package type unknown' => [fn ($x) => self::element($x, $hdr)->setAttributeNS(Ns::CSIP, 'csip:OAISPACKAGETYPE', 'XIP'), 'CSIP9', Severity::Error, 'XIP'];
        yield 'CSIP11 no CREATOR' => [fn ($x) => self::remove($x, "{$hdr}/mets:agent[@ROLE='CREATOR']"), 'CSIP11', Severity::Error, 'CREATOR'];
        yield 'CSIP13 software agent without OTHERTYPE' => [fn ($x) => self::removeAttribute($x, "{$hdr}/mets:agent[@OTHERTYPE='SOFTWARE']", 'OTHERTYPE'), 'CSIP13', Severity::Error, 'SOFTWARE'];
        yield 'CSIP14 software agent without name' => [fn ($x) => self::remove($x, "{$hdr}/mets:agent[@OTHERTYPE='SOFTWARE']/mets:name"), 'CSIP14', Severity::Error, 'no name'];
        yield 'CSIP15 software agent without note' => [fn ($x) => self::remove($x, "{$hdr}/mets:agent[@OTHERTYPE='SOFTWARE']/mets:note"), 'CSIP15', Severity::Error, 'no note'];
        yield 'CSIP16 note without SOFTWARE VERSION' => [fn ($x) => self::removeAttribute($x, "{$hdr}/mets:agent[@OTHERTYPE='SOFTWARE']/mets:note", 'NOTETYPE', Ns::CSIP), 'CSIP16', Severity::Error, 'SOFTWARE VERSION'];
        yield 'CSIP17 no dmdSec despite descriptive files' => [fn ($x) => self::remove($x, $dmd), 'CSIP17', Severity::Error, 'metadata/descriptive'];
        yield 'CSIP19 dmdSec without CREATED' => [fn ($x) => self::removeAttribute($x, $dmd, 'CREATED'), 'CSIP19', Severity::Error, 'CREATED'];
        yield 'CSIP20 STATUS not in vocabulary' => [fn ($x) => self::setAttribute($x, $dmd, 'STATUS', 'OLD'), 'CSIP20', Severity::Error, 'OLD'];
        yield 'CSIP20 STATUS missing' => [fn ($x) => self::removeAttribute($x, $dmd, 'STATUS'), 'CSIP20', Severity::Warning, 'STATUS'];
        yield 'CSIP22 LOCTYPE not URL' => [fn ($x) => self::setAttribute($x, $dmdRef, 'LOCTYPE', 'URN'), 'CSIP22', Severity::Error, 'LOCTYPE'];
        yield 'CSIP24 mdRef to missing file' => [fn ($x) => self::element($x, $dmdRef)->setAttributeNS(Ns::XLINK, 'xlink:href', 'metadata/descriptive/missing.xml'), 'CSIP24', Severity::Error, 'not in the package'];
        yield 'CSIP24 mdRef outside the package' => [fn ($x) => self::element($x, $dmdRef)->setAttributeNS(Ns::XLINK, 'xlink:href', 'https://example.org/dc.xml'), 'CSIP24', Severity::Warning, 'not checked'];
        yield 'CSIP26 MIMETYPE malformed' => [fn ($x) => self::setAttribute($x, $dmdRef, 'MIMETYPE', 'xml'), 'CSIP26', Severity::Error, 'type/subtype'];
        yield 'CSIP27 size does not match' => [fn ($x) => self::setAttribute($x, $dmdRef, 'SIZE', '1'), 'CSIP27', Severity::Error, 'does not match'];
        yield 'CSIP29 checksum does not match' => [fn ($x) => self::setAttribute($x, $dmdRef, 'CHECKSUM', str_repeat('0', 64)), 'CSIP29', Severity::Error, 'does not match'];
        yield 'CSIP32 preservation file without digiprovMD' => [fn ($x) => self::remove($x, '/mets:mets/mets:amdSec'), 'CSIP32', Severity::Error, 'not referenced'];
        yield 'CSIP43 provenance checksum does not match' => [fn ($x) => self::setAttribute($x, $provRef, 'CHECKSUM', str_repeat('0', 64)), 'CSIP43', Severity::Error, 'does not match'];
        yield 'EARKIP-CHECKSUMTYPE not computable' => [fn ($x) => self::setAttribute($x, $provRef, 'CHECKSUMTYPE', 'HAVAL'), 'EARKIP-CHECKSUMTYPE', Severity::Warning, 'HAVAL'];
        yield 'CSIP59 fileSec without ID' => [fn ($x) => self::removeAttribute($x, '/mets:mets/mets:fileSec', 'ID'), 'CSIP59', Severity::Error, 'fileSec/@ID'];
        yield 'CSIP60 no Documentation group' => [fn ($x) => self::remove($x, "/mets:mets/mets:fileSec/mets:fileGrp[@USE='Documentation'] | {$div}/mets:div[@LABEL='Documentation']"), 'CSIP60', Severity::Warning, 'Documentation'];
        yield 'CSIP62 representation group without content information type' => [fn ($x) => self::removeAttribute($x, "/mets:mets/mets:fileSec/mets:fileGrp[@USE='Representations/rep1']", 'CONTENTINFORMATIONTYPE', Ns::CSIP), 'CSIP62', Severity::Error, 'CONTENTINFORMATIONTYPE'];
        yield 'CSIP64 USE outside vocabulary' => [fn ($x) => self::setAttribute($x, "/mets:mets/mets:fileSec/mets:fileGrp[@USE='Documentation']", 'USE', 'Manuals'), 'CSIP64', Severity::Error, 'does not begin'];
        yield 'CSIP64 USE names no folder' => [fn ($x) => self::setAttribute($x, "/mets:mets/mets:fileSec/mets:fileGrp[@USE='Representations/rep1']", 'USE', 'Representations/rep9'), 'CSIP64', Severity::Error, 'folder'];
        yield 'CSIP68 file without MIMETYPE' => [fn ($x) => self::removeAttribute($x, $file, 'MIMETYPE'), 'CSIP68', Severity::Error, 'MIMETYPE'];
        yield 'CSIP69 file size does not match' => [fn ($x) => self::setAttribute($x, $file, 'SIZE', '999'), 'CSIP69', Severity::Error, 'does not match'];
        yield 'CSIP71 file checksum does not match' => [fn ($x) => self::setAttribute($x, $file, 'CHECKSUM', str_repeat('a', 64)), 'CSIP71', Severity::Error, 'does not match'];
        yield 'CSIP72 file without CHECKSUMTYPE' => [fn ($x) => self::removeAttribute($x, $file, 'CHECKSUMTYPE'), 'CSIP72', Severity::Error, 'CHECKSUMTYPE'];
        yield 'CSIP77 FLocat LOCTYPE' => [fn ($x) => self::setAttribute($x, "{$file}/mets:FLocat", 'LOCTYPE', 'URN'), 'CSIP77', Severity::Error, 'LOCTYPE'];
        yield 'CSIP79 FLocat to missing file' => [fn ($x) => self::element($x, "{$file}/mets:FLocat")->setAttributeNS(Ns::XLINK, 'xlink:href', 'documentation/gone.txt'), 'CSIP79', Severity::Error, 'not in the package'];
        yield 'CSIP80 no CSIP structMap' => [fn ($x) => self::setAttribute($x, "/mets:mets/mets:structMap[@LABEL='CSIP']", 'LABEL', 'Physical'), 'CSIP80', Severity::Error, 'no structMap'];
        yield 'CSIP81 structMap TYPE' => [fn ($x) => self::setAttribute($x, "/mets:mets/mets:structMap[@LABEL='CSIP']", 'TYPE', 'LOGICAL'), 'CSIP81', Severity::Error, 'PHYSICAL'];
        yield 'CSIP88 no Metadata division' => [fn ($x) => self::remove($x, "{$div}/mets:div[@LABEL='Metadata']"), 'CSIP88', Severity::Error, 'Metadata'];
        yield 'CSIP91 ADMID does not list all' => [fn ($x) => self::removeAttribute($x, "{$div}/mets:div[@LABEL='Metadata']", 'ADMID'), 'CSIP91', Severity::Warning, 'ADMID'];
        yield 'CSIP92 DMDID lists unknown' => [fn ($x) => self::setAttribute($x, "{$div}/mets:div[@LABEL='Metadata']", 'DMDID', 'dmd-dc dmd-x'), 'CSIP92', Severity::Warning, 'dmd-x'];
        yield 'CSIP93 no Documentation division' => [fn ($x) => self::remove($x, "{$div}/mets:div[@LABEL='Documentation']"), 'CSIP93', Severity::Warning, 'Documentation'];
        yield 'CSIP96 Documentation group not referenced' => [fn ($x) => self::remove($x, "{$div}/mets:div[@LABEL='Documentation']/mets:fptr"), 'CSIP96', Severity::Warning, 'not referenced'];
        yield 'CSIP116 fptr to wrong group' => [fn ($x) => self::setAttribute($x, "{$div}/mets:div[@LABEL='Documentation']/mets:fptr", 'FILEID', 'grp-schemas'), 'CSIP116', Severity::Error, 'does not point'];
        yield 'CSIP107 label names missing METS' => [fn ($x) => self::setAttribute($x, "{$div}/mets:div[mets:mptr]", 'LABEL', 'Representations/rep9'), 'CSIP107', Severity::Error, 'not in the package'];
        yield 'CSIP108 title is not a group' => [fn ($x) => self::element($x, "{$div}/mets:div/mets:mptr")->setAttributeNS(Ns::XLINK, 'xlink:title', 'grp-x'), 'CSIP108', Severity::Error, 'grp-x'];
        yield 'CSIP110 href to missing file' => [fn ($x) => self::element($x, "{$div}/mets:div/mets:mptr")->setAttributeNS(Ns::XLINK, 'xlink:href', 'representations/rep9/METS.xml'), 'CSIP110', Severity::Error, 'does not point'];
        yield 'SIP2 wrong profile' => [fn ($x) => self::setAttribute($x, $mets, 'PROFILE', 'https://earkcsip.dilcis.eu/profile/E-ARK-CSIP.xml'), 'SIP2', Severity::Error, 'E-ARK-SIP-v2-2-0.xml'];
        yield 'SIP3 RECORDSTATUS unknown' => [fn ($x) => self::setAttribute($x, $hdr, 'RECORDSTATUS', 'FINAL'), 'SIP3', Severity::Warning, 'FINAL'];
        yield 'SIP11 archival creator type' => [fn ($x) => self::setAttribute($x, "{$hdr}/mets:agent[@ROLE='ARCHIVIST']", 'TYPE', 'OTHER'), 'SIP11', Severity::Error, 'archival creator'];
        yield 'SIP14 archival creator note type' => [fn ($x) => self::removeAttribute($x, "{$hdr}/mets:agent[@ROLE='ARCHIVIST']/mets:note", 'NOTETYPE', Ns::CSIP), 'SIP14', Severity::Error, 'IDENTIFICATIONCODE'];
        yield 'SIP15 no submitting agent' => [fn ($x) => self::remove($x, "{$hdr}/mets:agent[@ROLE='CREATOR'][@TYPE='ORGANIZATION']"), 'SIP15', Severity::Error, 'no submitting agent'];
        yield 'SIP18 submitting agent without name' => [fn ($x) => self::remove($x, "{$hdr}/mets:agent[@ROLE='CREATOR'][@TYPE='ORGANIZATION']/mets:name"), 'SIP18', Severity::Error, 'no name'];
        yield 'SIP28 preservation agent type' => [fn ($x) => self::setAttribute($x, "{$hdr}/mets:agent[@ROLE='PRESERVATION']", 'TYPE', 'INDIVIDUAL'), 'SIP28', Severity::Error, 'ORGANIZATION'];
        yield 'SIP32 empty file format name' => [fn ($x) => self::element($x, $file)->setAttributeNS(Ns::SIP, 'sip:FILEFORMATNAME', ''), 'SIP32', Severity::Warning, 'FILEFORMATNAME'];
    }

    /** @param  callable(DOMXPath): void  $change */
    #[DataProvider('packageMetsViolations')]
    public function test_package_mets_violation(callable $change, string $id, Severity $severity, string $message): void
    {
        $root = $this->copyFixture('sip-2.2.0');
        $this->editXml($root.'/METS.xml', fn (DOMXPath $xpath) => $change($xpath));

        $this->assertViolation((new Validator)->validate($root), $id, $severity, $message);
    }

    public function test_representation_mets_must_state_content_information_type(): void
    {
        $root = $this->copyFixture('sip-2.2.0');
        $this->editXml($root.'/representations/rep1/METS.xml', fn (DOMXPath $x) => self::removeAttribute($x, '/mets:mets', 'CONTENTINFORMATIONTYPE', Ns::CSIP));

        $this->assertViolation((new Validator)->validate($root), 'CSIP4', Severity::Error, 'representation METS');
    }

    public function test_representation_mets_objid_should_match_folder(): void
    {
        $root = $this->copyFixture('sip-2.2.0');
        $this->editXml($root.'/representations/rep1/METS.xml', fn (DOMXPath $x) => self::setAttribute($x, '/mets:mets', 'OBJID', 'representation-one'));

        $this->assertViolation((new Validator)->validate($root), 'CSIP1', Severity::Warning, 'representation folder');
    }

    public function test_changed_data_file_breaks_the_representation_checksum(): void
    {
        $root = $this->copyFixture('sip-2.2.0');
        file_put_contents($root.'/representations/rep1/data/report.txt', "Annual report 2026\n");

        $report = (new Validator)->validate($root);

        $this->assertFalse($report->isValid());
        $this->assertViolation($report, 'CSIP71', Severity::Error, 'representations/rep1/data/report.txt');
    }

    public function test_unreadable_representation_mets(): void
    {
        $root = $this->copyFixture('sip-2.2.0');
        file_put_contents($root.'/representations/rep1/METS.xml', '<mets>');

        $report = (new Validator)->validate($root);

        $this->assertViolation($report, 'EARKIP-XML', Severity::Error, '');
        $this->assertViolation($report, 'EARKIP-REPRESENTATION', Severity::Error, 'cannot be read');
    }

    public function test_unreferenced_file(): void
    {
        $root = $this->copyFixture('sip-2.2.0');
        file_put_contents($root.'/representations/rep1/data/extra.txt', 'x');

        $this->assertViolation((new Validator)->validate($root), 'EARKIP-UNREFERENCED', Severity::Warning, 'extra.txt');
    }

    public function test_schema_error(): void
    {
        $root = $this->copyFixture('sip-2.2.0');
        $this->editXml($root.'/METS.xml', fn (DOMXPath $x, \DOMDocument $d) => self::element($x, '/mets:mets')->appendChild($d->createElementNS(Ns::METS, 'unknown')));

        $this->assertViolation((new Validator)->validate($root), 'EARKIP-XSD', Severity::Error, 'unknown');
    }

    public function test_structure_violations(): void
    {
        $root = $this->copyFixture('sip-2.2.0');
        rename($root.'/schemas/xlink.xsd', $root.'/documentation/xlink.xsd');

        $report = (new Validator)->validate($root);

        $this->assertViolation($report, 'CSIPSTR15', Severity::Warning, 'not in a schemas folder');
        $this->assertViolation($report, 'CSIP79', Severity::Error, 'schemas/xlink.xsd');
    }

    public function test_missing_mets(): void
    {
        $root = $this->temporaryDirectory();
        file_put_contents($root.'/readme.txt', 'x');

        $report = (new Validator)->validate($root);

        $this->assertFalse($report->isValid());
        $this->assertViolation($report, 'CSIPSTR4', Severity::Error, 'METS.xml');
    }

    public function test_malformed_mets(): void
    {
        $root = $this->temporaryDirectory();
        file_put_contents($root.'/METS.xml', '<mets');

        $this->assertViolation((new Validator)->validate($root), 'EARKIP-XML', Severity::Error, '');
    }

    public function test_zip_without_root_folder(): void
    {
        $report = (new Validator)->validate($this->zip(['METS.xml' => (string) file_get_contents(self::fixture('packages/sip-2.2.0/METS.xml'))]));

        $this->assertViolation($report, 'CSIPSTR1', Severity::Error, 'single root folder');
    }

    public function test_version_can_be_forced(): void
    {
        $report = (new Validator)->validate(self::fixture('packages/sip-2.1.0'), SpecificationVersion::V2_2_0);

        $this->assertSame(SpecificationVersion::V2_2_0, $report->version);
        $this->assertViolation($report, 'SIP2', Severity::Error, 'E-ARK-SIP-v2-2-0.xml');
    }

    public function test_report_as_json(): void
    {
        $report = (new Validator)->validate(self::fixture('packages/sip-2.2.0'));
        $data = json_decode($report->toJson(), true);

        $this->assertSame('1.0', $data['report_version']);
        $this->assertTrue($data['valid']);
        $this->assertSame(['CSIP', 'SIP'], $data['specification']['names']);
        $this->assertSame('2.2.0', $data['specification']['version']);
        $this->assertSame('sip-2.2.0', $data['package']['objid']);
        $this->assertSame(0, $data['summary']['errors']);
        $this->assertSame('CSIPSTR1', $data['findings'][0]['id']);
    }

    private function assertViolation(ValidationReport $report, string $id, Severity $severity, string $message): void
    {
        $finding = $report->finding($id);
        $this->assertNotNull($finding, "No finding for {$id}");
        $this->assertSame(Outcome::Failed, $finding->outcome, "{$id} did not fail. ".$this->describe($report));
        $this->assertSame($severity, $finding->severity(), "{$id}: ".implode(' | ', array_map(fn ($v) => $v->message, $finding->violations)));
        $messages = implode(' | ', array_map(fn ($v) => $v->message, $finding->violations));
        $this->assertStringContainsString($message, $messages);
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
