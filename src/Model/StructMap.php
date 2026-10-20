<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

/** mets/structMap. */
final class StructMap
{
    /** @param  list<Division>  $divisions */
    public function __construct(
        public readonly ?string $id,
        public readonly ?string $type,
        public readonly ?string $label,
        public readonly array $divisions = [],
    ) {}

    /**
     * The pointers in this structMap, at any depth.
     *
     * @return list<MetsPointer>
     */
    public function pointers(): array
    {
        /** @var list<MetsPointer> $pointers */
        $pointers = [];
        $walk = function (Division $division) use (&$walk, &$pointers): void {
            array_push($pointers, ...$division->pointers);
            foreach ($division->children as $child) {
                $walk($child);
            }
        };
        foreach ($this->divisions as $division) {
            $walk($division);
        }

        return $pointers;
    }
}
