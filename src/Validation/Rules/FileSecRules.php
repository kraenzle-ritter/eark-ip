<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation\Rules;

use DOMElement;
use KraenzleRitter\EarkIp\Catalog\Vocabulary;
use KraenzleRitter\EarkIp\PackageType;
use KraenzleRitter\EarkIp\Validation\MetsUnderTest;
use KraenzleRitter\EarkIp\Validation\RuleSet;
use KraenzleRitter\EarkIp\Validation\Severity;
use KraenzleRitter\EarkIp\Validation\ValidationContext;
use KraenzleRitter\EarkIp\Xml\Ns;

/** CSIP58–CSIP79, CSIP113, CSIP114: the file section and the files it lists. */
final class FileSecRules implements RuleSet
{
    /** File group names of the package METS (vocabulary FileGrpAndStructMapDivisionLabel). */
    private const PACKAGE_GROUPS = ['CSIP60' => 'Documentation', 'CSIP113' => 'Schemas', 'CSIP114' => 'Representations'];

    public function apply(ValidationContext $context): void
    {
        foreach ($context->documents as $mets) {
            $fileSec = $mets->first('/mets:mets/mets:fileSec');
            if (! $context->expect('CSIP58', $fileSec !== null, "{$mets->path} has no fileSec.", $mets->at($mets->root()))) {
                continue;
            }
            assert($fileSec instanceof DOMElement);
            $context->expect('CSIP59', $mets->filled($fileSec, 'ID'), 'mets/fileSec/@ID is missing.', $mets->at($fileSec));

            if ($mets->isPackageMets()) {
                $this->packageGroups($context, $mets, $fileSec);
            }
            foreach ($mets->elements('.//mets:fileGrp', $fileSec) as $group) {
                $this->group($context, $mets, $group);
            }
            foreach ($mets->elements('.//mets:file', $fileSec) as $file) {
                $this->file($context, $mets, $file);
            }
        }

        $context->notApplicable('CSIP61', 'CSIP62', 'CSIP63', 'CSIP73', 'CSIP74', 'CSIP75');
    }

    /**
     * Shared with mdRef: MIMETYPE present, shaped type/subtype, a
     * registered top-level type, and a subtype IANA lists (CSIP26,
     * CSIP40, CSIP53, CSIP68). CSIP allows another list by agreement, so
     * an unregistered subtype is a warning; x-, vnd. and prs. subtypes
     * pass.
     */
    public static function mimeType(ValidationContext $context, MetsUnderTest $mets, DOMElement $element, string $id, string $path): void
    {
        $value = trim((string) $mets->attribute($element, 'MIMETYPE'));
        if (! $context->expect($id, $value !== '', "{$path}/@MIMETYPE is missing or empty.", $mets->at($element))) {
            return;
        }
        if (! $context->expect(
            $id,
            preg_match('#^[a-z0-9][a-z0-9!\#$&^_.+-]*/[a-z0-9][a-z0-9!\#$&^_.+-]*(\s*;.*)?$#i', $value) === 1,
            "{$path}/@MIMETYPE \"{$value}\" is not a media type of the form type/subtype.",
            $mets->at($element),
        )) {
            return;
        }
        if (strlen($value) > 256) {
            $context->fail($id, "{$path}/@MIMETYPE is longer than 256 characters.", $mets->at($element), Severity::Warning);
        }

        $type = strtolower(trim(explode(';', $value)[0]));
        [$top, $subtype] = explode('/', $type, 2);
        if (! $context->expect($id, MediaTypes::isTopLevel($top), "{$path}/@MIMETYPE \"{$value}\" has no registered top-level type.", $mets->at($element))) {
            return;
        }
        if (! preg_match('/^(x-|vnd\.|prs\.)/', $subtype) && ! MediaTypes::isRegistered($type)) {
            $context->fail($id, "{$path}/@MIMETYPE \"{$value}\" is not in the IANA media type list.", $mets->at($element), Severity::Warning);
        }
    }

    private function packageGroups(ValidationContext $context, MetsUnderTest $mets, DOMElement $fileSec): void
    {
        $uses = array_map(fn (DOMElement $group) => (string) $mets->attribute($group, 'USE'), $mets->elements('mets:fileGrp', $fileSec));

        foreach (self::PACKAGE_GROUPS as $id => $name) {
            $present = array_filter($uses, fn (string $use) => $name === 'Representations' ? str_starts_with($use, $name) : $use === $name);
            // The profile says MUST, but a package without documentation cannot have a
            // non-empty Documentation group (CSIP66); the DILCIS corpus treats this as a warning.
            $context->expect($id, $present !== [], "The package METS has no fileGrp with @USE \"{$name}\".", $mets->at($fileSec), Severity::Warning);
        }
    }

    private function group(ValidationContext $context, MetsUnderTest $mets, DOMElement $group): void
    {
        $use = $mets->attribute($group, 'USE');
        $context->expect('CSIP65', $mets->filled($group, 'ID'), 'mets/fileSec/fileGrp/@ID is missing.', $mets->at($group));
        $context->expect('CSIP66', $mets->elements('.//mets:file', $group) !== [], "fileGrp \"{$use}\" contains no file.", $mets->at($group));

        if ($context->expect('CSIP64', trim((string) $use) !== '', 'mets/fileSec/fileGrp/@USE is missing.', $mets->at($group))
            && $mets->isPackageMets()
            && $group->parentNode instanceof DOMElement && $group->parentNode->localName === 'fileSec') {
            $this->packageUse($context, $mets, $group, (string) $use);
        }

        $admId = $mets->attribute($group, 'ADMID');
        if ($admId !== null) {
            $this->administrativeIds($context, $mets, $group, $admId);
        }

        // CSIP62 (SHOULD): a representation group states its content information
        // type, above all in a MIXED package. Groups for documentation or schemas
        // describe no content, so they need none.
        $type = $mets->attribute($group, 'CONTENTINFORMATIONTYPE', Ns::CSIP);
        if ($mets->isPackageMets() && str_starts_with((string) $use, 'Representations')) {
            $context->expect('CSIP62', $type !== null, "fileGrp \"{$use}\" describes a representation but has no @csip:CONTENTINFORMATIONTYPE.", $mets->at($group));
        }
        if ($type !== null) {
            $context->expect(
                'CSIP62',
                Vocabulary::matches($context->version, Vocabulary::CONTENT_INFORMATION_TYPE, $type),
                "fileGrp/@csip:CONTENTINFORMATIONTYPE \"{$type}\" is not a term of the Content Information Type vocabulary.",
                $mets->at($group),
                Severity::Error,
            );
        }
        ContentInformationType::checkOther(
            $context,
            'CSIP63',
            $mets,
            $group,
            $type,
            $mets->attribute($group, 'OTHERCONTENTINFORMATIONTYPE', Ns::CSIP),
            'mets/fileSec/fileGrp',
        );
    }

    /** CSIP64 for the package METS: @USE starts with a vocabulary term and names a folder of the package. */
    private function packageUse(ValidationContext $context, MetsUnderTest $mets, DOMElement $group, string $use): void
    {
        $terms = Vocabulary::terms($context->version, 'CSIPVocabularyFileGrpAndStructMapDivisionLabel');
        if ($context->type === PackageType::AIP) {
            // The AIP keeps the original SIP; its examples and commons-ip name the group so.
            $terms[] = 'Submission';
        }
        $known = array_filter($terms, fn (string $term) => stripos($use, $term) === 0);
        if (! $context->expect('CSIP64', $known !== [], "fileGrp/@USE \"{$use}\" does not begin with ".implode(', ', $terms).'.', $mets->at($group))) {
            return;
        }

        $folder = strtolower(trim($use, '/'));
        $folders = array_map('strtolower', $context->source->directories());
        $context->expect('CSIP64', in_array($folder, $folders, true), "fileGrp/@USE \"{$use}\" does not name a folder of the package.", $mets->at($group));
    }

    private function administrativeIds(ValidationContext $context, MetsUnderTest $mets, DOMElement $group, string $admId): void
    {
        $known = [];
        foreach ($mets->elements('/mets:mets/mets:amdSec/*[@ID]') as $section) {
            $known[(string) $mets->attribute($section, 'ID')] = true;
        }
        foreach (preg_split('/\s+/', trim($admId)) ?: [] as $reference) {
            $context->expect('CSIP61', isset($known[$reference]), "fileGrp/@ADMID \"{$reference}\" is not the ID of administrative metadata.", $mets->at($group), Severity::Warning);
        }
    }

    private function file(ValidationContext $context, MetsUnderTest $mets, DOMElement $file): void
    {
        $context->expect('CSIP67', $mets->filled($file, 'ID'), 'mets/fileSec/fileGrp/file/@ID is missing.', $mets->at($file));
        self::mimeType($context, $mets, $file, 'CSIP68', 'file');
        $context->expect('CSIP69', $mets->filled($file, 'SIZE'), 'file/@SIZE is missing.', $mets->at($file));
        $context->expect('CSIP70', $mets->filled($file, 'CREATED'), 'file/@CREATED is missing.', $mets->at($file));
        $context->expect('CSIP71', $mets->filled($file, 'CHECKSUM'), 'file/@CHECKSUM is missing.', $mets->at($file));
        $context->expect('CSIP72', $mets->filled($file, 'CHECKSUMTYPE'), 'file/@CHECKSUMTYPE is missing.', $mets->at($file));
        foreach (['CSIP73' => 'OWNERID', 'CSIP74' => 'ADMID', 'CSIP75' => 'DMDID'] as $id => $attribute) {
            if ($mets->attribute($file, $attribute) !== null) {
                $context->checked($id);
            }
        }

        $locations = $mets->elements('mets:FLocat', $file);
        if (! $context->expect('CSIP76', count($locations) === 1, $locations === [] ? 'file has no FLocat.' : 'file has more than one FLocat.', $mets->at($file))) {
            if ($locations === []) {
                return;
            }
        }
        $location = $locations[0];
        $context->expect('CSIP77', $mets->attribute($location, 'LOCTYPE') === 'URL', 'FLocat/@LOCTYPE must be "URL".', $mets->at($location));
        $context->expect('CSIP78', $mets->attribute($location, 'type', Ns::XLINK) === 'simple', 'FLocat/@xlink:type must be "simple".', $mets->at($location));

        $href = $mets->attribute($location, 'href', Ns::XLINK);
        if (! $context->expect('CSIP79', trim((string) $href) !== '', 'FLocat/@xlink:href is missing.', $mets->at($location))) {
            return;
        }
        $path = Integrity::locate($context, $mets, $location, $href, 'CSIP79');
        if ($path !== null) {
            Integrity::size($context, $mets, $file, $path, $mets->attribute($file, 'SIZE'), 'CSIP69');
            Integrity::checksum($context, $mets, $file, $path, $mets->attribute($file, 'CHECKSUM'), $mets->attribute($file, 'CHECKSUMTYPE'), 'CSIP71');
        }
    }
}
