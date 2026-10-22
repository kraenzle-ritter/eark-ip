<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** premis:agent. */
final class PremisAgent
{
    public function __construct(
        public readonly ?string $identifier,
        public readonly ?string $name,
        public readonly ?string $type,
    ) {}
}
