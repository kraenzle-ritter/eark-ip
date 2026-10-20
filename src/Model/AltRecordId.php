<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** mets/metsHdr/altRecordID, e.g. a submission agreement or a reference code (SIP5–SIP8). */
final class AltRecordId
{
    public function __construct(
        public readonly string $type,
        public readonly string $value,
    ) {}
}
