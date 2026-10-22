<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** premis:event, e.g. migration or normalization, with the objects it links. */
final class PremisEvent
{
    /** @param  list<PremisLink>  $objects */
    public function __construct(
        public readonly ?string $identifier,
        public readonly string $type,
        public readonly ?string $dateTime,
        public readonly ?string $detail,
        public readonly ?string $outcome,
        public readonly array $objects,
        public readonly string $premisFile,
    ) {}

    /**
     * Identifiers linked with the given role (case-insensitive).
     *
     * @return list<string>
     */
    public function linked(string $role): array
    {
        $identifiers = [];
        foreach ($this->objects as $link) {
            if ($link->role !== null && strcasecmp(trim($link->role), $role) === 0) {
                $identifiers[] = $link->identifier;
            }
        }

        return $identifiers;
    }
}
