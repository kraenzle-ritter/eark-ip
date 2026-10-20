<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** mets/metsHdr/agent. */
final class Agent
{
    /** @param  list<AgentNote>  $notes */
    public function __construct(
        public readonly string $role,
        public readonly string $type,
        public readonly string $name,
        public readonly array $notes = [],
        public readonly ?string $otherRole = null,
        public readonly ?string $otherType = null,
    ) {}

    public static function software(string $name, string $version): self
    {
        return new self('CREATOR', 'OTHER', $name, [new AgentNote($version, 'SOFTWARE VERSION')], otherType: 'SOFTWARE');
    }

    /** First note of the given @csip:NOTETYPE, e.g. "SOFTWARE VERSION" or "IDENTIFICATIONCODE". */
    public function note(string $type): ?string
    {
        foreach ($this->notes as $note) {
            if ($note->type === $type) {
                return $note->text;
            }
        }

        return null;
    }
}
