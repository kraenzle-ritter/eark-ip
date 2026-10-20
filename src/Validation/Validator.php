<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation;

use KraenzleRitter\EarkIp\Catalog\RequirementCatalog;
use KraenzleRitter\EarkIp\Exception\XmlException;
use KraenzleRitter\EarkIp\PackageType;
use KraenzleRitter\EarkIp\Reader\MetsReader;
use KraenzleRitter\EarkIp\Reader\VersionDetector;
use KraenzleRitter\EarkIp\Resources;
use KraenzleRitter\EarkIp\Source\Limits;
use KraenzleRitter\EarkIp\Source\PackageSource;
use KraenzleRitter\EarkIp\Source\Sources;
use KraenzleRitter\EarkIp\Specification;
use KraenzleRitter\EarkIp\SpecificationVersion;
use KraenzleRitter\EarkIp\Validation\Rules\AipRules;
use KraenzleRitter\EarkIp\Validation\Rules\DipRules;
use KraenzleRitter\EarkIp\Validation\Rules\FileSecRules;
use KraenzleRitter\EarkIp\Validation\Rules\HeaderRules;
use KraenzleRitter\EarkIp\Validation\Rules\MetadataRules;
use KraenzleRitter\EarkIp\Validation\Rules\MetsRootRules;
use KraenzleRitter\EarkIp\Validation\Rules\ReferenceRules;
use KraenzleRitter\EarkIp\Validation\Rules\SipRules;
use KraenzleRitter\EarkIp\Validation\Rules\StructMapRules;
use KraenzleRitter\EarkIp\Validation\Rules\StructureRules;
use KraenzleRitter\EarkIp\Xml\Xml;

/**
 * Validates an E-ARK package against CSIP and the specification of its
 * type (SIP, AIP, DIP), in the version its profile names or the one
 * given.
 *
 *     $report = (new Validator)->validate('/path/to/package.zip');
 *     $report->isValid();
 */
final class Validator
{
    public function __construct(private readonly ?Limits $limits = null) {}

    public function validate(
        string|PackageSource $package,
        ?SpecificationVersion $version = null,
        ?PackageType $type = null,
    ): ValidationReport {
        $source = Sources::open($package, $this->limits);

        if (! $source->hasFile('METS.xml')) {
            $version ??= SpecificationVersion::default();
            $context = new ValidationContext($source, $version, $type, RequirementCatalog::for($version));
            $context->fail('CSIPSTR4', 'The package root folder has no METS.xml.', new Location('/'));

            return $this->report($context, null, false);
        }

        $packageMets = $this->load($source, 'METS.xml', null, $errors);
        if ($packageMets === null) {
            $version ??= SpecificationVersion::default();
            $context = new ValidationContext($source, $version, $type, RequirementCatalog::for($version));
            $context->checked('CSIPSTR4');
            foreach ($errors as $error) {
                $context->fail('EARKIP-XML', $error, new Location('METS.xml'));
            }
            // The folder structure can be judged without METS.
            (new StructureRules)->apply($context);

            return $this->report($context, null, false);
        }

        $detectedVersion = VersionDetector::version($packageMets->model);
        $version ??= $detectedVersion ?? SpecificationVersion::default();
        $type ??= VersionDetector::claimedType($packageMets->model);

        [$representations, $unreadable] = $this->representations($source);
        $context = new ValidationContext($source, $version, $type, RequirementCatalog::for($version), [$packageMets, ...$representations]);
        $context->checked('CSIPSTR4');
        $context->checked('EARKIP-XML');
        $context->checked('EARKIP-REPRESENTATION');
        foreach ($unreadable as $file => $fileErrors) {
            foreach ($fileErrors as $error) {
                $context->fail('EARKIP-XML', $error, new Location($file));
            }
            $context->fail('EARKIP-REPRESENTATION', "{$file} cannot be read.", new Location($file));
        }

        $this->schema($context);
        foreach ($this->rules($this->specifications($type, $version)) as $rules) {
            $rules->apply($context);
        }

        return $this->report($context, $packageMets->model->objId, $detectedVersion !== null);
    }

    /**
     * The METS documents of the representations, and the errors of those
     * that cannot be read.
     *
     * @return array{list<MetsUnderTest>, array<string, list<string>>}
     */
    private function representations(PackageSource $source): array
    {
        $documents = [];
        $unreadable = [];
        foreach ($source->files() as $file) {
            if (! preg_match('#^representations/([^/]+)/METS\.xml$#', $file, $match)) {
                continue;
            }
            $mets = $this->load($source, $file, $match[1], $errors);
            if ($mets === null) {
                $unreadable[$file] = $errors;

                continue;
            }
            $documents[] = $mets;
        }

        return [$documents, $unreadable];
    }

    /**
     * @param  list<string>|null  $errors
     *
     * @param-out list<string>  $errors
     */
    private function load(PackageSource $source, string $path, ?string $representation, ?array &$errors): ?MetsUnderTest
    {
        $errors = [];
        try {
            $document = Xml::load($source->contents($path), $path);
        } catch (XmlException $exception) {
            $errors = $exception->errors === [] ? [$exception->getMessage()] : $exception->errors;

            return null;
        }

        return new MetsUnderTest($path, $document, (new MetsReader)->read($document, $path), $representation);
    }

    private function schema(ValidationContext $context): void
    {
        $schema = Resources::metsSchema($context->version);
        foreach ($context->documents as $mets) {
            $context->checked('EARKIP-XSD');
            foreach (Xml::schemaErrors($mets->document, $schema) as $error) {
                $context->fail('EARKIP-XSD', $error, new Location($mets->path));
            }
        }
    }

    /** @return list<string> */
    private function specifications(?PackageType $type, SpecificationVersion $version): array
    {
        $specification = $type === null ? Specification::CSIP : Specification::forPackageType($type);
        $names = ['CSIP'];
        if ($specification !== Specification::CSIP && $specification->supports($version)) {
            $names[] = $specification->value;
        }
        $names[] = 'EARKIP';

        return $names;
    }

    /**
     * @param  list<string>  $specifications
     * @return list<RuleSet>
     */
    private function rules(array $specifications): array
    {
        $rules = [
            new MetsRootRules,
            new HeaderRules,
            new MetadataRules,
            new FileSecRules,
            new StructMapRules,
            new StructureRules,
        ];
        if (in_array('SIP', $specifications, true)) {
            $rules[] = new SipRules;
        }
        if (in_array('AIP', $specifications, true)) {
            $rules[] = new AipRules;
        }
        if (in_array('DIP', $specifications, true)) {
            $rules[] = new DipRules;
        }
        $rules[] = new ReferenceRules;

        return $rules;
    }

    private function report(ValidationContext $context, ?string $objId, bool $versionDetected): ValidationReport
    {
        $specifications = $this->specifications($context->type, $context->version);

        return new ValidationReport(
            location: $context->source->location(),
            objId: $objId,
            type: $context->type,
            version: $context->version,
            versionDetected: $versionDetected,
            specifications: array_values(array_filter($specifications, fn (string $name) => $name !== 'EARKIP')),
            findings: $context->findings($specifications),
        );
    }
}
