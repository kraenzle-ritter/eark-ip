<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** linkingObjectIdentifier of an event, with its role ("source", "outcome"). */
final class PremisLink
{
    public function __construct(
        public readonly string $identifier,
        public readonly ?string $role,
    ) {}
}
