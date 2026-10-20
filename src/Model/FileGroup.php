<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** mets/fileSec/fileGrp. */
final class FileGroup
{
    /** @param  list<FileEntry>  $files */
    public function __construct(
        public readonly ?string $id,
        public readonly ?string $use,
        public readonly array $files = [],
        public readonly ?string $contentInformationType = null,
        public readonly ?string $otherContentInformationType = null,
        public readonly ?string $admId = null,
    ) {}

    public function isRepresentations(): bool
    {
        return $this->use !== null && str_starts_with($this->use, 'Representations');
    }
}
