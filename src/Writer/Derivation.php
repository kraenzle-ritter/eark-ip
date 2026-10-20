<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

use KraenzleRitter\EarkIp\Model\MetadataReference;
use KraenzleRitter\EarkIp\Model\MetadataSection;
use KraenzleRitter\EarkIp\Model\MetsDocument;
use KraenzleRitter\EarkIp\Model\Package;
use KraenzleRitter\EarkIp\Source\PackageSource;

/**
 * Copies what a package describes into a builder for a derived package:
 * an AIP from a SIP, a DIP from an AIP. Files are streamed from the
 * source package, nothing is unpacked.
 */
final class Derivation
{
    /** @param  list<string>|null  $representations  names to take over, null for all */
    public static function copy(
        Package $from,
        PackageSource $source,
        PackageBuilder $to,
        ?array $representations = null,
        bool $preservation = true,
        bool $onlyCurrent = false,
    ): void {
        $mets = $from->mets;
        if ($mets->label !== null) {
            $to->label($mets->label);
        }
        if ($mets->type !== null) {
            $to->contentCategory($mets->type, $mets->otherType);
        }
        if ($mets->contentInformationType !== null) {
            $to->contentInformationType($mets->contentInformationType, $mets->otherContentInformationType);
        }
        foreach ($mets->header->agents ?? [] as $agent) {
            if ($agent->otherType !== 'SOFTWARE') {
                $to->agent($agent);
            }
        }
        foreach ($mets->header->altRecordIds ?? [] as $id) {
            $to->altRecordId($id->type, $id->value);
        }

        foreach (self::metadata($mets, $source, MetadataSection::Descriptive, $onlyCurrent) as $file) {
            $to->descriptiveMetadata($file);
        }
        if ($preservation) {
            foreach (self::metadata($mets, $source, MetadataSection::Provenance, $onlyCurrent) as $file) {
                $to->preservationMetadata($file);
            }
        }
        foreach (self::metadata($mets, $source, MetadataSection::Rights, $onlyCurrent) as $file) {
            $to->rightsMetadata($file);
        }
        foreach (self::filesIn($source, 'documentation/') as $path) {
            $to->documentation(new SourceContent($source, $path), basename($path));
        }
        foreach (self::filesIn($source, 'schemas/') as $path) {
            $to->schema(new SourceContent($source, $path), basename($path));
        }

        foreach ($from->representations as $representation) {
            if ($representations !== null && ! in_array($representation->name, $representations, true)) {
                continue;
            }
            $builder = $to->representation($representation->name);
            if ($representation->mets?->contentInformationType !== null) {
                $builder->contentInformationType($representation->mets->contentInformationType, $representation->mets->otherContentInformationType);
            }
            $prefix = $representation->path.'/data/';
            $mimeTypes = [];
            $representationMets = $representation->mets;
            foreach ($representationMets?->files() ?? [] as $file) {
                $path = $representationMets?->resolve($file->href);
                if ($path !== null) {
                    $mimeTypes[$path] = $file->mimeType;
                }
            }
            foreach ($representation->dataFiles() as $path) {
                $builder->file(new SourceContent($source, $path), substr($path, strlen($prefix)), $mimeTypes[$path] ?? null);
            }
            if ($representation->mets !== null) {
                foreach (self::metadata($representation->mets, $source, MetadataSection::Descriptive, $onlyCurrent) as $file) {
                    $builder->descriptiveMetadata($file);
                }
                if ($preservation) {
                    foreach (self::metadata($representation->mets, $source, MetadataSection::Provenance, $onlyCurrent) as $file) {
                        $builder->preservationMetadata($file);
                    }
                }
            }
            foreach (self::filesIn($source, $representation->path.'/documentation/') as $path) {
                $builder->documentation(new SourceContent($source, $path), basename($path));
            }
            foreach (self::filesIn($source, $representation->path.'/schemas/') as $path) {
                $builder->schema(new SourceContent($source, $path), basename($path));
            }
        }
    }

    /** @return list<MetadataFile> */
    private static function metadata(MetsDocument $mets, PackageSource $source, MetadataSection $section, bool $onlyCurrent): array
    {
        $files = [];
        foreach ($mets->metadata($section) as $reference) {
            if ($onlyCurrent && $reference->status !== null && $reference->status !== 'CURRENT') {
                continue;
            }
            $path = $mets->resolve($reference->href);
            if ($path === null || ! $source->hasFile($path)) {
                continue;
            }
            $files[] = self::file($reference, $source, $path);
        }

        return $files;
    }

    private static function file(MetadataReference $reference, PackageSource $source, string $path): MetadataFile
    {
        return new MetadataFile(
            new SourceContent($source, $path),
            $reference->mdType ?? 'OTHER',
            $reference->otherMdType,
            $reference->mdTypeVersion,
            basename($path),
            'CURRENT',
            $reference->mimeType ?? 'text/xml',
        );
    }

    /** @return list<string> */
    private static function filesIn(PackageSource $source, string $prefix): array
    {
        return array_values(array_filter(
            $source->files(),
            fn (string $file) => str_starts_with($file, $prefix) && ! str_contains(substr($file, strlen($prefix)), '/'),
        ));
    }
}
