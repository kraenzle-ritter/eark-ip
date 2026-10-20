<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation;

use KraenzleRitter\EarkIp\PackageType;
use KraenzleRitter\EarkIp\SpecificationVersion;

/**
 * The result of validating one package: one finding per requirement of
 * the specifications that apply. A package is valid when no MUST
 * requirement fails.
 */
final class ValidationReport
{
    public const FORMAT_VERSION = '1.0';

    /**
     * @param  array<string, Finding>  $findings  by requirement ID
     * @param  list<string>  $specifications  e.g. ["CSIP", "SIP"]
     */
    public function __construct(
        public readonly string $location,
        public readonly ?string $objId,
        public readonly ?PackageType $type,
        public readonly SpecificationVersion $version,
        public readonly bool $versionDetected,
        public readonly array $specifications,
        public readonly array $findings,
        public readonly string $validator = 'kraenzle-ritter/eark-ip',
        public readonly ?\DateTimeImmutable $date = null,
    ) {}

    public function isValid(): bool
    {
        return $this->errors() === [];
    }

    public function finding(string $id): ?Finding
    {
        return $this->findings[$id] ?? null;
    }

    /** @return list<Finding> */
    public function errors(): array
    {
        return $this->withSeverity(Severity::Error);
    }

    /** @return list<Finding> */
    public function warnings(): array
    {
        return $this->withSeverity(Severity::Warning);
    }

    /** @return list<Finding> */
    public function withOutcome(Outcome $outcome): array
    {
        return array_values(array_filter($this->findings, fn (Finding $finding) => $finding->outcome === $outcome));
    }

    /** @return array<string, int> number of findings per outcome, plus errors and warnings */
    public function summary(): array
    {
        $summary = [];
        foreach (Outcome::cases() as $outcome) {
            $summary[$outcome->value] = count($this->withOutcome($outcome));
        }
        $summary['errors'] = count($this->errors());
        $summary['warnings'] = count($this->warnings());

        return $summary;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'report_version' => self::FORMAT_VERSION,
            'validator' => $this->validator,
            'date' => ($this->date ?? new \DateTimeImmutable)->format(DATE_ATOM),
            'package' => [
                'location' => $this->location,
                'objid' => $this->objId,
                'type' => $this->type?->value,
            ],
            'specification' => [
                'names' => $this->specifications,
                'version' => $this->version->value,
                'version_detected' => $this->versionDetected,
            ],
            'valid' => $this->isValid(),
            'summary' => $this->summary(),
            'findings' => array_map(fn (Finding $finding) => $finding->toArray(), array_values($this->findings)),
        ];
    }

    public function toJson(int $flags = JSON_PRETTY_PRINT): string
    {
        return json_encode($this->toArray(), $flags | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @return list<Finding> */
    private function withSeverity(Severity $severity): array
    {
        return array_values(array_filter($this->findings, fn (Finding $finding) => $finding->severity() === $severity));
    }
}
