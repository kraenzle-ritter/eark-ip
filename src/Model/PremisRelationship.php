<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** premis:relationship of an object, e.g. derivation / has source. */
final class PremisRelationship
{
    /** @param  list<string>  $related  identifier values of the related objects */
    public function __construct(
        public readonly string $type,
        public readonly ?string $subType,
        public readonly array $related,
    ) {}
}
