<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** mets/metsHdr/agent/note, typed with @csip:NOTETYPE. */
final class AgentNote
{
    public function __construct(
        public readonly string $text,
        public readonly ?string $type = null,
    ) {}
}
