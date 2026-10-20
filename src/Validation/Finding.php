<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation;

use KraenzleRitter\EarkIp\Catalog\Requirement;

/** The result of one requirement for one package. */
final class Finding
{
    /**
     * @param  list<Violation>  $violations
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly Requirement $requirement,
        public readonly Outcome $outcome,
        public readonly array $violations = [],
        public readonly array $notes = [],
    ) {}

    /** The heaviest severity of the violations; null when nothing failed. */
    public function severity(): ?Severity
    {
        if ($this->outcome !== Outcome::Failed) {
            return null;
        }

        $levels = [Severity::Info, Severity::Warning, Severity::Error];
        $heaviest = Severity::Info;
        foreach ($this->violations as $violation) {
            $severity = $violation->severity ?? Severity::forLevel($this->requirement->level);
            if (array_search($severity, $levels, true) > array_search($heaviest, $levels, true)) {
                $heaviest = $severity;
            }
        }

        return $heaviest;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->requirement->id,
            'specification' => $this->requirement->specification,
            'level' => $this->requirement->level->value,
            'name' => $this->requirement->name,
            'outcome' => $this->outcome->value,
            'severity' => $this->severity()?->value,
            'violations' => array_map(fn (Violation $violation) => [
                'message' => $violation->message,
                'severity' => ($violation->severity ?? Severity::forLevel($this->requirement->level))->value,
                'location' => $violation->location?->toArray(),
            ], $this->violations),
            'notes' => $this->notes,
        ];
    }
}
