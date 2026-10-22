<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** premis:object: a file, representation, bitstream or intellectual entity. */
final class PremisObject
{
    /**
     * @param  string|null  $category  xsi:type without prefix, e.g. "file" or "representation"
     * @param  list<string>  $identifiers  objectIdentifierValue
     * @param  list<string>  $contentLocations  storage/contentLocation/contentLocationValue
     * @param  list<PremisRelationship>  $relationships
     * @param  string  $premisFile  package path of the PREMIS file it was read from
     */
    public function __construct(
        public readonly ?string $category,
        public readonly array $identifiers,
        public readonly ?string $originalName,
        public readonly array $contentLocations,
        public readonly array $relationships,
        public readonly string $premisFile,
    ) {}
}
