<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Catalog;

/**
 * One requirement of an E-ARK specification, e.g. CSIP68 «File
 * mimetype», or one of this package's own checks (EARKIP-…).
 */
final class Requirement
{
    public function __construct(
        public readonly string $id,
        public readonly string $specification,
        public readonly Level $level,
        public readonly string $name,
        public readonly ?string $location = null,
        public readonly ?string $cardinality = null,
        public readonly string $description = '',
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            specification: (string) $data['specification'],
            level: Level::from((string) $data['level']),
            name: (string) $data['name'],
            location: isset($data['location']) ? (string) $data['location'] : null,
            cardinality: isset($data['cardinality']) ? (string) $data['cardinality'] : null,
            description: (string) ($data['description'] ?? ''),
        );
    }
}
