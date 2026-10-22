<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Model;

use KraenzleRitter\EarkIp\RepresentationFunction;

/** The function proposed for one representation, with what it rests on. */
final class RepresentationRole
{
    public const BASIS_EVENT = 'premis-event';

    public const BASIS_RELATIONSHIP = 'premis-relationship';

    public const BASIS_NAME = 'name';

    public const BASIS_DEFAULT = 'default';

    /**
     * @param  bool  $derived  PREMIS describes its files as derived from files outside it
     * @param  string|null  $derivedFrom  the representation they come from, null when it is not in the package or not known
     */
    public function __construct(
        public readonly string $name,
        public readonly RepresentationFunction $function,
        public readonly string $basis,
        public readonly string $reason,
        public readonly bool $derived = false,
        public readonly ?string $derivedFrom = null,
    ) {}

    /** @return array{name: string, function: string, basis: string, reason: string, derived: bool, derived_from: string|null} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'function' => $this->function->value,
            'basis' => $this->basis,
            'reason' => $this->reason,
            'derived' => $this->derived,
            'derived_from' => $this->derivedFrom,
        ];
    }
}
