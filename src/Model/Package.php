<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

use KraenzleRitter\EarkIp\PackageType;
use KraenzleRitter\EarkIp\Specification;
use KraenzleRitter\EarkIp\SpecificationVersion;

/** An E-ARK information package as read from a folder or a ZIP. */
final class Package
{
    /**
     * @param  list<Representation>  $representations
     * @param  list<string>  $parentAips  identifiers from the structMap «parent AIP»
     * @param  list<string>  $childAips  identifiers from the structMap «child AIPs»
     */
    public function __construct(
        public readonly string $name,
        public readonly MetsDocument $mets,
        public readonly ?PackageType $type,
        public readonly SpecificationVersion $version,
        public readonly bool $versionDetected,
        public readonly array $representations = [],
        public readonly array $parentAips = [],
        public readonly array $childAips = [],
        public readonly ?Package $submission = null,
        public readonly string $location = '',
    ) {}

    public function objId(): ?string
    {
        return $this->mets->objId;
    }

    public function specification(): Specification
    {
        return $this->type === null ? Specification::CSIP : Specification::forPackageType($this->type);
    }

    public function header(): ?MetsHeader
    {
        return $this->mets->header;
    }

    public function representation(string $name): ?Representation
    {
        foreach ($this->representations as $representation) {
            if ($representation->name === $name) {
                return $representation;
            }
        }

        return null;
    }
}
