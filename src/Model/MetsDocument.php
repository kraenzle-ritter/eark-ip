<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

use KraenzleRitter\EarkIp\Source\Path;

/**
 * One METS document of a package: the package METS.xml or the METS.xml
 * of a representation.
 */
final class MetsDocument
{
    /**
     * @param  list<MetadataReference>  $metadata
     * @param  list<FileGroup>  $fileGroups
     * @param  list<StructMap>  $structMaps
     */
    public function __construct(
        public readonly string $path,
        public readonly ?string $objId,
        public readonly ?string $profile,
        public readonly ?string $type = null,
        public readonly ?MetsHeader $header = null,
        public readonly array $metadata = [],
        public readonly array $fileGroups = [],
        public readonly array $structMaps = [],
        public readonly ?string $label = null,
        public readonly ?string $otherType = null,
        public readonly ?string $contentInformationType = null,
        public readonly ?string $otherContentInformationType = null,
    ) {}

    /** Folder of this document inside the package, "" for the package root. */
    public function directory(): string
    {
        return Path::directory($this->path);
    }

    /** @return list<MetadataReference> */
    public function metadata(?MetadataSection $section = null): array
    {
        return array_values(array_filter(
            $this->metadata,
            fn (MetadataReference $reference) => $section === null || $reference->section === $section,
        ));
    }

    /** @return list<FileEntry> */
    public function files(): array
    {
        $files = [];
        foreach ($this->fileGroups as $group) {
            array_push($files, ...$group->files);
        }

        return $files;
    }

    public function fileGroup(string $use): ?FileGroup
    {
        foreach ($this->fileGroups as $group) {
            if ($group->use === $use) {
                return $group;
            }
        }

        return null;
    }

    public function structMap(string $label): ?StructMap
    {
        foreach ($this->structMaps as $structMap) {
            if ($structMap->label === $label) {
                return $structMap;
            }
        }

        return null;
    }

    /** The package path of a relative href in this document, or null when it is external. */
    public function resolve(?string $href): ?string
    {
        return $href === null ? null : (Path::fromHref($href, $this->directory())[0] ?? null);
    }
}
