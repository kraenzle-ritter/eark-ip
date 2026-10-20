<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation\Rules;

use DOMElement;
use KraenzleRitter\EarkIp\Validation\MetsUnderTest;
use KraenzleRitter\EarkIp\Validation\RuleSet;
use KraenzleRitter\EarkIp\Validation\Severity;
use KraenzleRitter\EarkIp\Validation\ValidationContext;
use KraenzleRitter\EarkIp\Xml\Ns;

/**
 * CSIP80–CSIP119: the structural map labelled "CSIP". Every METS
 * document needs one (CSIP80–CSIP85); the divisions for metadata,
 * documentation, schemas and representations belong to the package METS.
 */
final class StructMapRules implements RuleSet
{
    /** Division label => [ID requirement, presence, file references, fptr target, fileGrp @USE]. */
    private const DIVISIONS = [
        'Documentation' => ['CSIP94', 'CSIP93', 'CSIP96', 'CSIP116'],
        'Schemas' => ['CSIP98', 'CSIP97', 'CSIP100', 'CSIP118'],
        'Representations' => ['CSIP102', 'CSIP101', 'CSIP104', 'CSIP119'],
    ];

    public function apply(ValidationContext $context): void
    {
        foreach ($context->documents as $mets) {
            $main = $this->structMap($context, $mets);
            if ($main !== null && $mets->isPackageMets()) {
                $this->metadataDivision($context, $mets, $main);
                foreach (self::DIVISIONS as $label => $ids) {
                    $this->division($context, $mets, $main, $label, ...$ids);
                }
                $this->representationDivisions($context, $mets, $main);
            }
        }

        $context->notTestable('CSIP95', 'A documentation division without @LABEL "Documentation" cannot be recognised reliably (see the DILCIS corpus, CSIP95).');
        $context->notTestable('CSIP99', 'A schema division without @LABEL "Schemas" cannot be recognised reliably.');
        $context->notTestable('CSIP103', 'A content division without @LABEL "Representations" cannot be recognised reliably.');
        $context->notApplicable('CSIP91', 'CSIP92', 'CSIP94', 'CSIP96', 'CSIP116', 'CSIP98', 'CSIP100', 'CSIP118', 'CSIP102', 'CSIP104', 'CSIP119', 'CSIP105', 'CSIP106', 'CSIP107', 'CSIP108', 'CSIP109', 'CSIP110', 'CSIP111', 'CSIP112');
    }

    /** CSIP80–CSIP85. Returns the main division. */
    private function structMap(ValidationContext $context, MetsUnderTest $mets): ?DOMElement
    {
        $maps = $mets->elements("/mets:mets/mets:structMap[@LABEL = 'CSIP']");
        if (! $context->expect('CSIP80', count($maps) === 1, $maps === []
            ? "{$mets->path} has no structMap with @LABEL \"CSIP\"."
            : "{$mets->path} has more than one structMap with @LABEL \"CSIP\".", $mets->at($mets->root()))) {
            if ($maps === []) {
                return null;
            }
        }
        $map = $maps[0];
        $context->checked('CSIP82');
        $context->expect('CSIP81', $mets->attribute($map, 'TYPE') === 'PHYSICAL', 'structMap[@LABEL="CSIP"]/@TYPE must be "PHYSICAL".', $mets->at($map));
        $context->expect('CSIP83', $mets->filled($map, 'ID'), 'structMap[@LABEL="CSIP"]/@ID is missing.', $mets->at($map));

        $divisions = $mets->elements('mets:div', $map);
        if (! $context->expect('CSIP84', count($divisions) === 1, 'structMap[@LABEL="CSIP"] must contain exactly one div.', $mets->at($map))) {
            if ($divisions === []) {
                return null;
            }
        }
        $context->expect('CSIP85', $mets->filled($divisions[0], 'ID'), 'The main div of the structMap has no @ID.', $mets->at($divisions[0]));

        return $divisions[0];
    }

    /** CSIP88–CSIP92. */
    private function metadataDivision(ValidationContext $context, MetsUnderTest $mets, DOMElement $main): void
    {
        $divisions = $mets->elements("mets:div[@LABEL = 'Metadata']", $main);
        foreach (['CSIP88', 'CSIP90'] as $id) {
            $context->expect($id, count($divisions) === 1, $divisions === []
                ? 'The structMap has no div with @LABEL "Metadata".'
                : 'The structMap has more than one div with @LABEL "Metadata".', $mets->at($main));
        }
        if ($divisions === []) {
            return;
        }
        $division = $divisions[0];
        $context->expect('CSIP89', $mets->filled($division, 'ID'), 'The Metadata div has no @ID.', $mets->at($division));

        $this->listsAll($context, $mets, $division, 'CSIP91', 'ADMID', '/mets:mets/mets:amdSec/*[@ID]', 'administrative metadata');
        $this->listsAll($context, $mets, $division, 'CSIP92', 'DMDID', '/mets:mets/mets:dmdSec[@ID]', 'descriptive metadata');
    }

    /** The Metadata div lists exactly the IDs of the metadata sections in @ADMID or @DMDID. */
    private function listsAll(ValidationContext $context, MetsUnderTest $mets, DOMElement $division, string $id, string $attribute, string $sections, string $kind): void
    {
        $expected = array_map(fn (DOMElement $section) => (string) $mets->attribute($section, 'ID'), $mets->elements($sections));
        $value = $mets->attribute($division, $attribute);
        if ($expected === [] && $value === null) {
            return;
        }
        if (! $context->expect($id, $value !== null, "The package has {$kind} but the Metadata div has no @{$attribute}.", $mets->at($division))) {
            return;
        }
        $listed = preg_split('/\s+/', trim((string) $value)) ?: [];
        foreach (array_diff($expected, $listed) as $missing) {
            $context->fail($id, "The Metadata div does not list {$missing} in @{$attribute}.", $mets->at($division));
        }
        foreach (array_diff($listed, $expected) as $unknown) {
            $context->fail($id, "The Metadata div lists {$unknown} in @{$attribute}, which is not {$kind} of the package.", $mets->at($division));
        }
    }

    /**
     * Documentation, Schemas, Representations: at most one division,
     * with an ID; every file group of that name referenced by an fptr;
     * every fptr pointing to such a group.
     */
    private function division(ValidationContext $context, MetsUnderTest $mets, DOMElement $main, string $label, string $idRequirement, string $presence, string $references, string $target): void
    {
        $groups = [];
        foreach ($mets->elements('/mets:mets/mets:fileSec/mets:fileGrp[@ID]') as $group) {
            $use = (string) $mets->attribute($group, 'USE');
            if ($label === 'Representations' ? str_starts_with($use, 'Representations') : $use === $label) {
                $groups[(string) $mets->attribute($group, 'ID')] = $use;
            }
        }

        $divisions = $mets->elements("mets:div[@LABEL = '{$label}']", $main);
        $context->expect($presence, $divisions !== [], "The structMap has no div with @LABEL \"{$label}\".", $mets->at($main));
        if (count($divisions) > 1) {
            $context->fail($presence, "The structMap has more than one div with @LABEL \"{$label}\".", $mets->at($main), Severity::Error);
        }

        $pointed = [];
        foreach ($divisions as $division) {
            $context->expect($idRequirement, $mets->filled($division, 'ID'), "The {$label} div has no @ID.", $mets->at($division));
            foreach ($mets->elements('mets:fptr', $division) as $fptr) {
                $fileId = (string) $mets->attribute($fptr, 'FILEID');
                $pointed[$fileId] = true;
                $context->expect($target, isset($groups[$fileId]), "fptr/@FILEID \"{$fileId}\" in the {$label} div does not point to a fileGrp with @USE \"{$label}\".", $mets->at($fptr));
            }
        }

        foreach ($groups as $groupId => $use) {
            $context->expect($references, isset($pointed[$groupId]), "fileGrp \"{$use}\" ({$groupId}) is not referenced from the {$label} div.", $mets->at($main));
        }
    }

    /**
     * CSIP105–CSIP112: one division per representation, labelled with
     * its path, with an mptr to the representation METS.
     */
    private function representationDivisions(ValidationContext $context, MetsUnderTest $mets, DOMElement $main): void
    {
        $groupIds = [];
        foreach ($mets->elements("/mets:mets/mets:fileSec/mets:fileGrp[starts-with(@USE, 'Representations')]") as $group) {
            $groupIds[(string) $mets->attribute($group, 'ID')] = (string) $mets->attribute($group, 'USE');
        }

        $titled = [];
        $labelled = [];
        foreach ($mets->elements('mets:div[mets:mptr]', $main) as $division) {
            $context->checked('CSIP105');
            $context->expect('CSIP106', $mets->filled($division, 'ID'), 'A representation div has no @ID.', $mets->at($division));
            $label = $mets->attribute($division, 'LABEL');
            if ($context->expect('CSIP107', trim((string) $label) !== '', 'A representation div has no @LABEL.', $mets->at($division))) {
                $labelled[strtolower(trim((string) $label, '/'))] = true;
                $metsPath = trim((string) $label, '/').'/METS.xml';
                $exists = $this->hasFileCaseInsensitive($context, $metsPath);
                $context->expect('CSIP107', $exists, "The representation div \"{$label}\" names {$metsPath}, which is not in the package.", $mets->at($division), Severity::Error);
            }

            $pointers = $mets->elements('mets:mptr', $division);
            $context->expect('CSIP109', count($pointers) === 1, 'A representation div must contain exactly one mptr.', $mets->at($division));
            foreach ($pointers as $mptr) {
                $title = $mets->attribute($mptr, 'title', Ns::XLINK);
                if ($context->expect('CSIP108', trim((string) $title) !== '', 'mptr/@xlink:title is missing.', $mets->at($mptr))) {
                    $titled[(string) $title] = true;
                    $context->expect('CSIP108', isset($groupIds[(string) $title]), "mptr/@xlink:title \"{$title}\" is not the ID of a fileGrp with @USE \"Representations…\".", $mets->at($mptr));
                }
                $context->expect('CSIP111', $mets->attribute($mptr, 'type', Ns::XLINK) === 'simple', 'mptr/@xlink:type must be "simple".', $mets->at($mptr));
                $context->expect('CSIP112', $mets->attribute($mptr, 'LOCTYPE') === 'URL', 'mptr/@LOCTYPE must be "URL".', $mets->at($mptr));

                $href = $mets->attribute($mptr, 'href', Ns::XLINK);
                if (! $context->expect('CSIP110', trim((string) $href) !== '', 'mptr/@xlink:href is missing.', $mets->at($mptr))) {
                    continue;
                }
                $path = $mets->model->resolve($href);
                if ($context->expect('CSIP110', $path !== null && $context->source->hasFile($path), "mptr/@xlink:href \"{$href}\" does not point to a file in the package.", $mets->at($mptr))) {
                    $context->markReferenced((string) $path);
                }
                if ($label !== null && $path !== null) {
                    $context->expect(
                        'CSIP110',
                        strcasecmp((string) $path, trim($label, '/').'/METS.xml') === 0,
                        "mptr/@xlink:href \"{$href}\" does not match the div @LABEL \"{$label}\".",
                        $mets->at($mptr),
                    );
                }
            }
        }

        foreach ($groupIds as $groupId => $use) {
            $context->expect('CSIP108', isset($titled[$groupId]), "fileGrp \"{$use}\" ({$groupId}) is not referenced by the @xlink:title of an mptr.", $mets->at($main));
        }

        foreach ($context->representationMets() as $representation) {
            $path = strtolower('representations/'.$representation->representation);
            $context->expect('CSIP105', isset($labelled[$path]), "There is no div with @LABEL \"Representations/{$representation->representation}\" for {$representation->path}.", $mets->at($main));
        }
    }

    private function hasFileCaseInsensitive(ValidationContext $context, string $path): bool
    {
        foreach ($context->source->files() as $file) {
            if (strcasecmp($file, $path) === 0) {
                return true;
            }
        }

        return false;
    }
}
