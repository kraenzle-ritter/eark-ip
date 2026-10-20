<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/**
 * A metadata file referenced from METS: dmdSec/mdRef or
 * amdSec/<section>/mdRef.
 */
final class MetadataReference
{
    public function __construct(
        public readonly MetadataSection $section,
        public readonly ?string $sectionId,
        public readonly ?string $href,
        public readonly ?string $mdType,
        public readonly ?string $otherMdType = null,
        public readonly ?string $mdTypeVersion = null,
        public readonly ?string $mimeType = null,
        public readonly ?int $size = null,
        public readonly ?string $created = null,
        public readonly ?string $checksum = null,
        public readonly ?string $checksumType = null,
        public readonly ?string $status = null,
        public readonly ?string $sectionCreated = null,
        public readonly ?string $id = null,
        public readonly ?string $locType = 'URL',
        public readonly ?string $label = null,
    ) {}

    /** The metadata type, with OTHERMDTYPE when MDTYPE is "OTHER". */
    public function type(): ?string
    {
        return $this->mdType === 'OTHER' && $this->otherMdType !== null ? $this->otherMdType : $this->mdType;
    }
}
