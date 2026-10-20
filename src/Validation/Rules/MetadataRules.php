<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation\Rules;

use DOMElement;
use KraenzleRitter\EarkIp\Catalog\Vocabulary;
use KraenzleRitter\EarkIp\Validation\MetsUnderTest;
use KraenzleRitter\EarkIp\Validation\RuleSet;
use KraenzleRitter\EarkIp\Validation\Severity;
use KraenzleRitter\EarkIp\Validation\ValidationContext;
use KraenzleRitter\EarkIp\Xml\Ns;

/**
 * CSIP17–CSIP57: descriptive metadata (dmdSec), digital provenance
 * (digiprovMD) and rights (rightsMD), with the files they point to.
 * techMD and sourceMD have no CSIP numbers; their files are checked under
 * EARKIP-INTEGRITY.
 */
final class MetadataRules implements RuleSet
{
    /**
     * Requirement IDs per section, in the order: section ID, section
     * CREATED, STATUS, mdRef, LOCTYPE, xlink:type, xlink:href, MDTYPE,
     * MIMETYPE, SIZE, CREATED, CHECKSUM, CHECKSUMTYPE.
     */
    private const IDS = [
        'dmdSec' => ['CSIP18', 'CSIP19', 'CSIP20', 'CSIP21', 'CSIP22', 'CSIP23', 'CSIP24', 'CSIP25', 'CSIP26', 'CSIP27', 'CSIP28', 'CSIP29', 'CSIP30'],
        'digiprovMD' => ['CSIP33', null, 'CSIP34', 'CSIP35', 'CSIP36', 'CSIP37', 'CSIP38', 'CSIP39', 'CSIP40', 'CSIP41', 'CSIP42', 'CSIP43', 'CSIP44'],
        'rightsMD' => ['CSIP46', null, 'CSIP47', 'CSIP48', 'CSIP49', 'CSIP50', 'CSIP51', 'CSIP52', 'CSIP53', 'CSIP54', 'CSIP55', 'CSIP56', 'CSIP57'],
    ];

    public function apply(ValidationContext $context): void
    {
        foreach ($context->documents as $mets) {
            $this->presence($context, $mets);
            foreach ($mets->elements('/mets:mets/mets:dmdSec') as $section) {
                $this->section($context, $mets, $section, self::IDS['dmdSec']);
            }
            foreach ($mets->elements('/mets:mets/mets:amdSec/mets:digiprovMD') as $section) {
                $this->section($context, $mets, $section, self::IDS['digiprovMD']);
            }
            foreach ($mets->elements('/mets:mets/mets:amdSec/mets:rightsMD') as $section) {
                $context->checked('CSIP45');
                $this->section($context, $mets, $section, self::IDS['rightsMD']);
            }
            foreach ($mets->elements('/mets:mets/mets:amdSec/mets:techMD/mets:mdRef | /mets:mets/mets:amdSec/mets:sourceMD/mets:mdRef') as $mdRef) {
                $path = Integrity::locate($context, $mets, $mdRef, $mets->attribute($mdRef, 'href', Ns::XLINK), 'EARKIP-INTEGRITY');
                if ($path !== null) {
                    Integrity::size($context, $mets, $mdRef, $path, $mets->attribute($mdRef, 'SIZE'), 'EARKIP-INTEGRITY');
                    Integrity::checksum($context, $mets, $mdRef, $path, $mets->attribute($mdRef, 'CHECKSUM'), $mets->attribute($mdRef, 'CHECKSUMTYPE'), 'EARKIP-INTEGRITY');
                }
            }
        }

        foreach (self::IDS as $ids) {
            $context->notApplicable(...array_filter($ids));
        }
        $context->notApplicable('CSIP45', 'EARKIP-INTEGRITY');
    }

    /**
     * CSIP17, CSIP21, CSIP31, CSIP32: sections should exist, and must
     * exist for every file in metadata/descriptive and
     * metadata/preservation.
     */
    private function presence(ValidationContext $context, MetsUnderTest $mets): void
    {
        $base = $mets->model->directory() === '' ? '' : $mets->model->directory().'/';
        $descriptive = $this->filesBelow($context, $base.'metadata/descriptive/');
        $preservation = $this->filesBelow($context, $base.'metadata/preservation/');

        $dmdSecs = $mets->elements('/mets:mets/mets:dmdSec');
        $amdSecs = $mets->elements('/mets:mets/mets:amdSec');
        $digiprov = $mets->elements('/mets:mets/mets:amdSec/mets:digiprovMD');

        if ($mets->isPackageMets()) {
            $context->expect('CSIP17', $dmdSecs !== [], 'The package METS has no dmdSec (descriptive metadata).', $mets->at($mets->root()));
            $context->expect('CSIP31', $amdSecs !== [], 'The package METS has no amdSec (administrative metadata).', $mets->at($mets->root()));
            $context->expect('CSIP32', $digiprov !== [], 'The package METS has no amdSec/digiprovMD (provenance metadata).', $mets->at($mets->root()));
        }

        // The corpus reads CSIP31 and CSIP32 both ways: sections without metadata
        // files are suspicious too.
        if ($amdSecs !== [] && $this->filesBelow($context, $base.'metadata/') === []) {
            $context->fail('CSIP31', "{$mets->path} has an amdSec but {$base}metadata contains no files.", $mets->at($amdSecs[0]), Severity::Warning);
        }
        if ($digiprov !== [] && $preservation === []) {
            $context->fail('CSIP32', "{$mets->path} has a digiprovMD but {$base}metadata/preservation contains no files.", $mets->at($digiprov[0]), Severity::Warning);
        }

        if ($descriptive !== []) {
            $context->expect('CSIP17', $dmdSecs !== [], "{$base}metadata/descriptive contains files but {$mets->path} has no dmdSec.", $mets->at($mets->root()), Severity::Error);
            $context->expect('CSIP21', $mets->elements('/mets:mets/mets:dmdSec/mets:mdRef') !== [], "{$base}metadata/descriptive contains files but no dmdSec has an mdRef.", $mets->at($mets->root()), Severity::Error);
        }
        if ($preservation !== []) {
            $context->expect('CSIP31', $amdSecs !== [], "{$base}metadata/preservation contains files but {$mets->path} has no amdSec.", $mets->at($mets->root()), Severity::Error);
            $referenced = [];
            foreach ($mets->elements('/mets:mets/mets:amdSec/mets:digiprovMD/mets:mdRef') as $mdRef) {
                $path = $mets->model->resolve($mets->attribute($mdRef, 'href', Ns::XLINK));
                if ($path !== null) {
                    $referenced[$path] = true;
                }
            }
            foreach ($preservation as $file) {
                if (! isset($referenced[$file]) && ! isset($referenced[rawurldecode($file)])) {
                    $context->fail('CSIP32', "{$file} is not referenced by any amdSec/digiprovMD.", $mets->at($mets->root()), Severity::Error);
                }
            }
        }
    }

    /** @param  array<int, string|null>  $ids */
    private function section(ValidationContext $context, MetsUnderTest $mets, DOMElement $section, array $ids): void
    {
        [$sectionId, $sectionCreated, $status, $hasRef, $locType, $linkType, $href, $mdType, $mimeType, $size, $created, $checksum, $checksumType] = $ids;
        $name = $section->localName;

        $context->expect((string) $sectionId, $mets->filled($section, 'ID'), "{$name}/@ID is missing.", $mets->at($section));
        if ($sectionCreated !== null) {
            $context->expect($sectionCreated, $mets->filled($section, 'CREATED'), "{$name}/@CREATED is missing.", $mets->at($section));
        }

        $value = $mets->attribute($section, 'STATUS');
        if ($context->expect((string) $status, $value !== null, "{$name}/@STATUS is missing.", $mets->at($section))) {
            $context->expect(
                (string) $status,
                Vocabulary::contains($context->version, Vocabulary::STATUS, (string) $value),
                "{$name}/@STATUS \"{$value}\" is neither \"CURRENT\" nor \"SUPERSEDED\".",
                $mets->at($section),
                Severity::Error,
            );
        }

        $mdRefs = $mets->elements('mets:mdRef', $section);
        if (! $context->expect((string) $hasRef, $mdRefs !== [], "{$name} has no mdRef pointing to a metadata file.", $mets->at($section))) {
            return;
        }

        foreach ($mdRefs as $mdRef) {
            $context->expect((string) $locType, $mets->attribute($mdRef, 'LOCTYPE') === 'URL', "{$name}/mdRef/@LOCTYPE must be \"URL\".", $mets->at($mdRef));
            $context->expect((string) $linkType, $mets->attribute($mdRef, 'type', Ns::XLINK) === 'simple', "{$name}/mdRef/@xlink:type must be \"simple\".", $mets->at($mdRef));
            $context->expect((string) $mdType, $mets->filled($mdRef, 'MDTYPE'), "{$name}/mdRef/@MDTYPE is missing.", $mets->at($mdRef));
            FileSecRules::mimeType($context, $mets, $mdRef, (string) $mimeType, "{$name}/mdRef");
            $context->expect((string) $size, $mets->filled($mdRef, 'SIZE'), "{$name}/mdRef/@SIZE is missing.", $mets->at($mdRef));
            $context->expect((string) $created, $mets->filled($mdRef, 'CREATED'), "{$name}/mdRef/@CREATED is missing.", $mets->at($mdRef));
            $context->expect((string) $checksum, $mets->filled($mdRef, 'CHECKSUM'), "{$name}/mdRef/@CHECKSUM is missing.", $mets->at($mdRef));
            $context->expect((string) $checksumType, $mets->filled($mdRef, 'CHECKSUMTYPE'), "{$name}/mdRef/@CHECKSUMTYPE is missing.", $mets->at($mdRef));

            $location = $mets->attribute($mdRef, 'href', Ns::XLINK);
            if (! $context->expect((string) $href, trim((string) $location) !== '', "{$name}/mdRef/@xlink:href is missing.", $mets->at($mdRef))) {
                continue;
            }
            $path = Integrity::locate($context, $mets, $mdRef, $location, (string) $href);
            if ($path !== null) {
                Integrity::size($context, $mets, $mdRef, $path, $mets->attribute($mdRef, 'SIZE'), (string) $size);
                Integrity::checksum($context, $mets, $mdRef, $path, $mets->attribute($mdRef, 'CHECKSUM'), $mets->attribute($mdRef, 'CHECKSUMTYPE'), (string) $checksum);
            }
        }
    }

    /** @return list<string> */
    private function filesBelow(ValidationContext $context, string $prefix): array
    {
        // Hidden placeholders such as .gitkeep are not metadata.
        return array_values(array_filter(
            $context->source->files(),
            fn (string $file) => str_starts_with($file, $prefix) && ! str_starts_with(basename($file), '.'),
        ));
    }
}
