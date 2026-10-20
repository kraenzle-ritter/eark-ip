<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation\Rules;

use KraenzleRitter\EarkIp\Model\MetadataSection;
use KraenzleRitter\EarkIp\Validation\Location;
use KraenzleRitter\EarkIp\Validation\RuleSet;
use KraenzleRitter\EarkIp\Validation\ValidationContext;

/** CSIPSTR1–CSIPSTR16: the folder structure (CSIPSTR4, METS.xml, is checked by the validator before anything else). */
final class StructureRules implements RuleSet
{
    private const KNOWN_ROOT_FOLDERS = ['metadata', 'representations', 'schemas', 'documentation'];

    private const KNOWN_REPRESENTATION_FOLDERS = ['data', 'metadata', 'schemas', 'documentation'];

    public function apply(ValidationContext $context): void
    {
        $source = $context->source;
        $root = new Location('/');

        $context->expect('CSIPSTR1', ! $source->isArchive() || $source->hasSingleRootFolder(), 'The archive does not unpack to a single root folder.', $root);
        $source->isArchive() ? $context->checked('CSIPSTR3') : $context->notApplicable('CSIPSTR3');

        $mets = $context->packageMets();
        if ($mets !== null && $mets->model->objId !== null) {
            $context->expect(
                'CSIPSTR2',
                Names::match($mets->model->objId, $source->name()),
                "The root folder \"{$source->name()}\" is not named after mets/@OBJID \"{$mets->model->objId}\".",
                $root,
            );
        }

        $context->expect('CSIPSTR5', $source->hasDirectory('metadata'), 'There is no metadata folder.', $root);
        $this->metadataFolders($context);

        $context->expect('CSIPSTR9', $source->hasDirectory('representations'), 'There is no representations folder.', $root);
        $this->representations($context);

        $this->schemas($context);
        $context->notTestable('CSIPSTR16', 'Whether a file is documentation cannot be told from its location alone.');

        $additional = array_filter(
            $source->directories(),
            fn (string $directory) => ! str_contains($directory, '/') && ! in_array(strtolower($directory), self::KNOWN_ROOT_FOLDERS, true),
        );
        $context->checked('CSIPSTR14');
        foreach ($additional as $directory) {
            $context->note('CSIPSTR14', "Additional folder {$directory}.");
        }
    }

    /** CSIPSTR6–CSIPSTR8: metadata files of the package METS lie in the recommended sub-folders. */
    private function metadataFolders(ValidationContext $context): void
    {
        $mets = $context->packageMets();
        if ($mets === null) {
            return;
        }
        $folders = [
            'CSIPSTR6' => [MetadataSection::Provenance, 'metadata/preservation/'],
            'CSIPSTR7' => [MetadataSection::Descriptive, 'metadata/descriptive/'],
        ];
        foreach ($folders as $id => [$section, $folder]) {
            $references = $mets->model->metadata($section);
            if ($references === []) {
                $context->notApplicable($id);

                continue;
            }
            foreach ($references as $reference) {
                $path = $mets->model->resolve($reference->href);
                if ($path === null) {
                    continue;
                }
                $context->expect($id, str_starts_with($path, $folder), "{$path} is not in {$folder}.", new Location($mets->path));
            }
        }

        $others = array_filter($mets->model->metadata(), fn ($reference) => ! in_array($reference->section, [MetadataSection::Provenance, MetadataSection::Descriptive], true));
        $others === [] ? $context->notApplicable('CSIPSTR8') : $context->checked('CSIPSTR8');
    }

    /** CSIPSTR10–CSIPSTR13. */
    private function representations(ValidationContext $context): void
    {
        $source = $context->source;
        if (! $source->hasDirectory('representations')) {
            $context->notApplicable('CSIPSTR10', 'CSIPSTR11', 'CSIPSTR12', 'CSIPSTR13');

            return;
        }

        $folders = array_values(array_filter($source->directories(), fn (string $directory) => preg_match('#^representations/[^/]+$#', $directory) === 1));
        $context->expect('CSIPSTR10', $folders !== [], 'The representations folder contains no representation folder.', new Location('representations'));
        foreach ($source->files() as $file) {
            if (preg_match('#^representations/[^/]+$#', $file)) {
                $context->fail('CSIPSTR10', "{$file} lies directly in the representations folder, outside a representation folder.", new Location($file));
            }
        }

        foreach ($folders as $folder) {
            $at = new Location($folder);
            $context->expect('CSIPSTR11', $source->hasDirectory($folder.'/data'), "{$folder} has no data folder.", $at);
            $context->expect('CSIPSTR12', $source->hasFile($folder.'/METS.xml'), "{$folder} has no METS.xml.", $at);
            $context->expect('CSIPSTR13', $source->hasDirectory($folder.'/metadata'), "{$folder} has no metadata folder.", $at);
            foreach ($source->directories() as $directory) {
                if (preg_match('#^'.preg_quote($folder, '#').'/([^/]+)$#', $directory, $match)
                    && ! in_array(strtolower($match[1]), self::KNOWN_REPRESENTATION_FOLDERS, true)) {
                    $context->note('CSIPSTR14', "Additional folder {$directory}.");
                }
            }
        }
    }

    /** CSIPSTR15: XML schemas lie in a schemas folder of the package or a representation. */
    private function schemas(ValidationContext $context): void
    {
        $context->expect('CSIPSTR15', $context->source->hasDirectory('schemas'), 'There is no schemas folder.', new Location('/'));
        foreach ($context->source->files() as $file) {
            // Files of a representation's data are content, even when they are schemas.
            if (preg_match('#^representations/[^/]+/data/#', $file) === 1) {
                continue;
            }
            if (str_ends_with(strtolower($file), '.xsd') && preg_match('#(^|/)schemas/#', $file) !== 1) {
                $context->fail('CSIPSTR15', "The schema {$file} is not in a schemas folder.", new Location($file));
            }
        }
    }
}
