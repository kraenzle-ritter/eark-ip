<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

use Composer\InstalledVersions;
use KraenzleRitter\EarkIp\Exception\EarkIpException;
use KraenzleRitter\EarkIp\Model\Agent;
use KraenzleRitter\EarkIp\Model\AltRecordId;
use KraenzleRitter\EarkIp\PackageType;
use KraenzleRitter\EarkIp\Source\PackageSource;
use KraenzleRitter\EarkIp\Specification;
use KraenzleRitter\EarkIp\SpecificationVersion;

/**
 * Describes a package to write. Everything METS needs that can be
 * derived (checksums, sizes, IDs, the structural map) is left to the
 * writer.
 *
 *     $builder = PackageBuilder::sip('urn:uuid:…')
 *         ->label('Annual report 2025')
 *         ->submitter('Example Office')
 *         ->descriptiveMetadata(MetadataFile::of('ead.xml', 'EAD', '3'))
 *         ->representation('rep1', fn (RepresentationBuilder $rep) => $rep->directory('/path/to/files'));
 *     (new PackageWriter)->write($builder, '/out/package.zip');
 */
final class PackageBuilder
{
    public SpecificationVersion $version;

    public ?string $label = null;

    public string $contentCategory = 'Mixed';

    public ?string $otherContentCategory = null;

    public string $contentInformationType = 'MIXED';

    public ?string $otherContentInformationType = null;

    public ?string $recordStatus = null;

    public ?\DateTimeImmutable $created = null;

    public Agent $software;

    /** @var list<Agent> */
    public array $agents = [];

    /** @var list<AltRecordId> */
    public array $altRecordIds = [];

    /** @var list<MetadataFile> */
    public array $descriptive = [];

    /** @var list<MetadataFile> */
    public array $preservation = [];

    /** @var list<MetadataFile> */
    public array $rights = [];

    /** @var array<string, Content> */
    public array $documentation = [];

    /** @var array<string, Content> */
    public array $schemas = [];

    /** @var array<string, RepresentationBuilder> */
    public array $representations = [];

    /** @var list<string> */
    public array $parentAips = [];

    /** @var list<string> */
    public array $childAips = [];

    /** A package to keep under submission/, e.g. the SIP of an AIP, copied as it is. */
    public ?PackageSource $submission = null;

    public function __construct(public readonly PackageType $type, public readonly string $objId)
    {
        if (trim($objId) === '') {
            throw new EarkIpException('A package needs an identifier (mets/@OBJID).');
        }
        $this->version = SpecificationVersion::default();
        $this->software = Agent::software('kraenzle-ritter/eark-ip', self::packageVersion());
        if ($type === PackageType::SIP) {
            $this->recordStatus = 'NEW';
        }
    }

    public static function sip(string $objId): self
    {
        return new self(PackageType::SIP, $objId);
    }

    public static function aip(string $objId): self
    {
        return new self(PackageType::AIP, $objId);
    }

    public static function dip(string $objId): self
    {
        return new self(PackageType::DIP, $objId);
    }

    public function specification(): Specification
    {
        return Specification::forPackageType($this->type);
    }

    public function version(SpecificationVersion $version): self
    {
        $this->specification()->assertSupports($version);
        $this->version = $version;

        return $this;
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** mets/@TYPE, a term of the Content Category vocabulary, or "Other" with a name. */
    public function contentCategory(string $category, ?string $other = null): self
    {
        $this->contentCategory = $category;
        $this->otherContentCategory = $other;

        return $this;
    }

    /** mets/@csip:CONTENTINFORMATIONTYPE, e.g. "citsehpj_v2_0", or "OTHER" with a name. */
    public function contentInformationType(string $type, ?string $other = null): self
    {
        $this->contentInformationType = $type;
        $this->otherContentInformationType = $other;

        return $this;
    }

    public function recordStatus(?string $status): self
    {
        $this->recordStatus = $status;

        return $this;
    }

    public function created(\DateTimeImmutable $created): self
    {
        $this->created = $created;

        return $this;
    }

    /** The software that creates the package (CSIP10–CSIP16). */
    public function software(string $name, string $version): self
    {
        $this->software = Agent::software($name, $version);

        return $this;
    }

    public function agent(Agent $agent): self
    {
        $this->agents[] = $agent;

        return $this;
    }

    public function archivalCreator(string $name, string $type = 'ORGANIZATION', ?string $identificationCode = null): self
    {
        return $this->agent(Agents::archivalCreator($name, $type, $identificationCode));
    }

    public function submitter(string $name, string $type = 'ORGANIZATION', ?string $identificationCode = null): self
    {
        return $this->agent(Agents::submitter($name, $type, $identificationCode));
    }

    /** @param  list<string>  $contactDetails */
    public function contactPerson(string $name, array $contactDetails = []): self
    {
        return $this->agent(Agents::contactPerson($name, $contactDetails));
    }

    public function preservationAgent(string $name, ?string $identificationCode = null): self
    {
        return $this->agent(Agents::preservation($name, $identificationCode));
    }

    /** metsHdr/altRecordID, e.g. "SUBMISSIONAGREEMENT" or "REFERENCECODE" (SIP5–SIP8). */
    public function altRecordId(string $type, string $value): self
    {
        $this->altRecordIds[] = new AltRecordId($type, $value);

        return $this;
    }

    public function submissionAgreement(string $reference): self
    {
        return $this->altRecordId('SUBMISSIONAGREEMENT', $reference);
    }

    public function referenceCode(string $code): self
    {
        return $this->altRecordId('REFERENCECODE', $code);
    }

    public function descriptiveMetadata(MetadataFile $file): self
    {
        $this->descriptive[] = $file;

        return $this;
    }

    public function preservationMetadata(MetadataFile $file): self
    {
        $this->preservation[] = $file;

        return $this;
    }

    /** PREMIS 3 built with PremisBuilder, as preservation metadata. */
    public function premis(PremisBuilder $premis, string $name = 'premis.xml'): self
    {
        return $this->preservationMetadata(new MetadataFile(new StringContent($premis->toXml(), $name), 'PREMIS', mdTypeVersion: '3.0', name: $name));
    }

    public function rightsMetadata(MetadataFile $file): self
    {
        $this->rights[] = $file;

        return $this;
    }

    public function documentation(Content|string $content, ?string $name = null): self
    {
        $content = is_string($content) ? new FileContent($content) : $content;
        $this->documentation[$name ?? $content->suggestedName()] = $content;

        return $this;
    }

    public function schema(Content|string $content, ?string $name = null): self
    {
        $content = is_string($content) ? new FileContent($content) : $content;
        $this->schemas[$name ?? $content->suggestedName()] = $content;

        return $this;
    }

    /** @param  (callable(RepresentationBuilder): mixed)|null  $configure */
    public function representation(string $name, ?callable $configure = null): RepresentationBuilder
    {
        $representation = $this->representations[$name] ??= new RepresentationBuilder($name);
        if ($configure !== null) {
            $configure($representation);
        }

        return $representation;
    }

    public function parentAip(string $identifier): self
    {
        $this->parentAips[] = $identifier;

        return $this;
    }

    public function childAip(string $identifier): self
    {
        $this->childAips[] = $identifier;

        return $this;
    }

    private static function packageVersion(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('kraenzle-ritter/eark-ip')) {
            return InstalledVersions::getPrettyVersion('kraenzle-ritter/eark-ip') ?? 'dev';
        }

        return 'dev';
    }
}
