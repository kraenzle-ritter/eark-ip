<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Catalog;

use KraenzleRitter\EarkIp\Exception\EarkIpException;
use KraenzleRitter\EarkIp\Resources;
use KraenzleRitter\EarkIp\SpecificationVersion;

/**
 * All requirements of one specification version: those of the METS
 * profiles (CSIP, SIP, AIP, DIP), the folder structure requirements
 * CSIPSTR1–16 and this package's own checks.
 */
final class RequirementCatalog
{
    /** @var array<string, self> */
    private static array $loaded = [];

    /** @param  array<string, Requirement>  $requirements */
    private function __construct(
        public readonly SpecificationVersion $version,
        private readonly array $requirements,
    ) {}

    public static function for(SpecificationVersion $version): self
    {
        return self::$loaded[$version->value] ??= self::load($version);
    }

    public function get(string $id): Requirement
    {
        return $this->requirements[$id]
            ?? throw new EarkIpException("Requirement {$id} is not part of E-ARK {$this->version->value}.");
    }

    public function has(string $id): bool
    {
        return isset($this->requirements[$id]);
    }

    /** @return list<Requirement> */
    public function all(?string $specification = null): array
    {
        return array_values(array_filter(
            $this->requirements,
            fn (Requirement $requirement) => $specification === null || $requirement->specification === $specification,
        ));
    }

    private static function load(SpecificationVersion $version): self
    {
        $requirements = [];
        foreach ([
            Resources::path('structure-requirements.json'),
            Resources::specPath($version, 'requirements.json'),
            Resources::path('eark-ip-requirements.json'),
        ] as $file) {
            /** @var list<array<string, mixed>> $entries */
            $entries = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            foreach ($entries as $entry) {
                $requirement = Requirement::fromArray($entry);
                $requirements[$requirement->id] = $requirement;
            }
        }

        return new self($version, $requirements);
    }
}
