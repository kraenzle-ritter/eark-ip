<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation;

use KraenzleRitter\EarkIp\Catalog\Requirement;
use KraenzleRitter\EarkIp\Catalog\RequirementCatalog;
use KraenzleRitter\EarkIp\PackageType;
use KraenzleRitter\EarkIp\Source\PackageSource;
use KraenzleRitter\EarkIp\SpecificationVersion;

/**
 * State of one validation run: the package, its METS documents, and
 * what every rule found so far.
 */
final class ValidationContext
{
    /** @var array<string, true> */
    private array $checked = [];

    /** @var array<string, list<Violation>> */
    private array $violations = [];

    /** @var array<string, list<string>> */
    private array $notes = [];

    /** @var array<string, string> */
    private array $notTestable = [];

    /** @var array<string, true> */
    private array $notApplicable = [];

    /** @var array<string, true> package paths some METS document points to */
    private array $referenced = [];

    /** @var array<string, string> "algorithm:path" => checksum */
    private array $checksums = [];

    /** @param  list<MetsUnderTest>  $documents  package METS first */
    public function __construct(
        public readonly PackageSource $source,
        public readonly SpecificationVersion $version,
        public readonly ?PackageType $type,
        public readonly RequirementCatalog $catalog,
        public readonly array $documents = [],
    ) {}

    public function packageMets(): ?MetsUnderTest
    {
        return $this->documents[0] ?? null;
    }

    /** @return list<MetsUnderTest> */
    public function representationMets(): array
    {
        return array_values(array_filter($this->documents, fn (MetsUnderTest $mets) => ! $mets->isPackageMets()));
    }

    public function requirement(string $id): Requirement
    {
        return $this->catalog->get($id);
    }

    /** Record that a requirement was checked, whether or not it held. */
    public function checked(string $id): void
    {
        $this->checked[$id] = true;
    }

    public function fail(string $id, string $message, ?Location $location = null, ?Severity $severity = null): void
    {
        $this->checked[$id] = true;
        $this->violations[$id][] = new Violation($message, $location, $severity);
    }

    /** Check a condition; returns it so callers can stop following up on a failure. */
    public function expect(string $id, bool $holds, string $message, ?Location $location = null, ?Severity $severity = null): bool
    {
        $holds ? $this->checked($id) : $this->fail($id, $message, $location, $severity);

        return $holds;
    }

    public function notTestable(string $id, string $reason): void
    {
        $this->notTestable[$id] = $reason;
    }

    public function note(string $id, string $note): void
    {
        $this->notes[$id][] = $note;
    }

    public function markReferenced(string $path): void
    {
        $this->referenced[$path] = true;
    }

    public function isReferenced(string $path): bool
    {
        return isset($this->referenced[$path]);
    }

    /** Checksum of a package file, computed once per algorithm. */
    public function checksum(string $path, string $algorithm): string
    {
        return $this->checksums[$algorithm.':'.$path] ??= $this->source->hash($path, $algorithm);
    }

    /**
     * One finding per requirement of the given specifications, in
     * catalogue order.
     *
     * @param  list<string>  $specifications
     * @return array<string, Finding>
     */
    public function findings(array $specifications): array
    {
        $findings = [];
        foreach ($this->catalog->all() as $requirement) {
            if (! in_array($requirement->specification, $specifications, true)) {
                continue;
            }
            $id = $requirement->id;
            $notes = $this->notes[$id] ?? [];
            $outcome = match (true) {
                isset($this->violations[$id]) => Outcome::Failed,
                isset($this->checked[$id]) => Outcome::Passed,
                isset($this->notTestable[$id]) => Outcome::NotTestable,
                isset($this->notApplicable[$id]) => Outcome::NotApplicable,
                default => Outcome::NotChecked,
            };
            if ($outcome === Outcome::NotTestable) {
                $notes[] = $this->notTestable[$id];
            }
            $findings[$id] = new Finding($requirement, $outcome, $this->violations[$id] ?? [], $notes);
        }

        return $findings;
    }

    /**
     * Mark requirements as not applicable, e.g. rightsMD rules in a
     * package without rights metadata. Ignored for requirements that
     * were checked.
     */
    public function notApplicable(string ...$ids): void
    {
        foreach ($ids as $id) {
            $this->notApplicable[$id] = true;
        }
    }
}
