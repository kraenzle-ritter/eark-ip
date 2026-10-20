<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** mets/structMap//div/mptr: a pointer to another METS document or package. */
final class MetsPointer
{
    public function __construct(
        public readonly ?string $href,
        public readonly ?string $title = null,
        public readonly ?string $locType = 'URL',
        public readonly ?string $otherLocType = null,
        public readonly ?string $id = null,
    ) {}
}
