<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/**
 * The PREMIS a package carries, from every PREMIS file its METS
 * documents point to, merged. Files that are missing or cannot be read
 * are listed in $notes; reading goes on without them.
 */
final class Premis
{
    /**
     * @param  list<PremisObject>  $objects
     * @param  list<PremisEvent>  $events
     * @param  list<PremisAgent>  $agents
     * @param  list<string>  $files  package paths of the PREMIS files read
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly array $objects = [],
        public readonly array $events = [],
        public readonly array $agents = [],
        public readonly array $files = [],
        public readonly array $notes = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->objects === [] && $this->events === [];
    }
}
