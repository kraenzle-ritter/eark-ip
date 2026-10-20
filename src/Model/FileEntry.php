<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** mets/fileSec/fileGrp/file with its FLocat. */
final class FileEntry
{
    public function __construct(
        public readonly ?string $id,
        public readonly ?string $href,
        public readonly ?string $mimeType,
        public readonly ?int $size,
        public readonly ?string $created,
        public readonly ?string $checksum,
        public readonly ?string $checksumType,
        public readonly ?string $ownerId = null,
        public readonly ?string $admId = null,
        public readonly ?string $dmdId = null,
        public readonly ?string $use = null,
        public readonly ?string $formatName = null,
        public readonly ?string $formatVersion = null,
        public readonly ?string $formatRegistry = null,
        public readonly ?string $formatKey = null,
        public readonly ?string $locType = 'URL',
    ) {}
}
