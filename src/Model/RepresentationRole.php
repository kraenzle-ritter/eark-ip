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

    /*
     * Why the function was proposed, as a code with parameters, for an
     * application that shows the reason in its own language; `reason` is
     * the English text made from them by reasonText().
     */

    /** Source of a migration or normalization; `outcome`: the representation of its outcome. */
    public const REASON_MIGRATION_SOURCE = 'migration-source';

    /** Outcome of a migration or normalization; `source`: the representation of its source. */
    public const REASON_MIGRATION_OUTCOME = 'migration-outcome';

    /** One step of a chain of derivations; `step`: 0 original, 1 master, 2 and more access. */
    public const REASON_CHAIN_STEP = 'chain-step';

    /** Derived from the original; `original`: the representation it comes from. */
    public const REASON_DERIVED_FROM_ORIGINAL = 'derived-from-original';

    /** Source of the master; `master`: the representation of the master. */
    public const REASON_SOURCE_OF_MASTER = 'source-of-master';

    /** Source of derived files; `derived`: the representation they are in. */
    public const REASON_SOURCE_OF_DERIVED = 'source-of-derived';

    /** Derived from files elsewhere; `source`: the representation they come from. */
    public const REASON_DERIVED_FROM = 'derived-from';

    /** The name says so; `name`: the name of the representation. */
    public const REASON_NAMED = 'named';

    /** A second master; `first`: the master kept, `previous`: the reason it would have had, as `code` and `params`. */
    public const REASON_FURTHER_MASTER = 'further-master';

    /** A second access copy; `first` and `previous` as for a further master. */
    public const REASON_FURTHER_ACCESS = 'further-access';

    /** The first representation, taken as master because nothing names one. */
    public const REASON_FIRST_REPRESENTATION = 'first-representation';

    /** Nothing says what it is for. */
    public const REASON_UNSPECIFIED = 'unspecified';

    /**
     * @param  bool  $derived  PREMIS describes its files as derived from files outside it
     * @param  string|null  $derivedFrom  the representation they come from, null when it is not in the package or not known
     * @param  string|null  $reasonCode  one of the REASON_* constants
     * @param  array<string, mixed>  $reasonParams  the values the code's text names
     */
    public function __construct(
        public readonly string $name,
        public readonly RepresentationFunction $function,
        public readonly string $basis,
        public readonly string $reason,
        public readonly bool $derived = false,
        public readonly ?string $derivedFrom = null,
        public readonly ?string $reasonCode = null,
        public readonly array $reasonParams = [],
    ) {}

    /**
     * The English text of a reason.
     *
     * @param  array<string, mixed>  $params
     */
    public static function reasonText(string $code, array $params = []): string
    {
        $value = fn (string $key): string => is_scalar($params[$key] ?? null) ? (string) $params[$key] : '?';
        $previous = function () use ($params): string {
            $previous = $params['previous'] ?? null;

            return is_array($previous) && is_string($previous['code'] ?? null)
                ? self::reasonText($previous['code'], is_array($previous['params'] ?? null) ? $previous['params'] : [])
                : '?';
        };

        return match ($code) {
            self::REASON_MIGRATION_SOURCE => "source of a migration or normalization whose outcome is in {$value('outcome')}",
            self::REASON_MIGRATION_OUTCOME => "outcome of a migration or normalization of files in {$value('source')}",
            self::REASON_CHAIN_STEP => "step {$value('step')} of a chain of derivations",
            self::REASON_DERIVED_FROM_ORIGINAL => "derived from the original in {$value('original')}",
            self::REASON_SOURCE_OF_MASTER => "source of the master in {$value('master')}",
            self::REASON_SOURCE_OF_DERIVED => "source of files derived in {$value('derived')}",
            self::REASON_DERIVED_FROM => "derived from files in {$value('source')}",
            self::REASON_NAMED => "named {$value('name')}",
            self::REASON_FURTHER_MASTER => "a further master after {$value('first')}; only one is allowed ({$previous()})",
            self::REASON_FURTHER_ACCESS => "a further access copy after {$value('first')}; only one is allowed ({$previous()})",
            self::REASON_FIRST_REPRESENTATION => 'first representation; nothing names a master',
            self::REASON_UNSPECIFIED => 'nothing says what it is for',
            default => $code,
        };
    }

    /** @return array{name: string, function: string, basis: string, reason: string, reason_code: string|null, reason_params: array<string, mixed>, derived: bool, derived_from: string|null} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'function' => $this->function->value,
            'basis' => $this->basis,
            'reason' => $this->reason,
            'reason_code' => $this->reasonCode,
            'reason_params' => $this->reasonParams,
            'derived' => $this->derived,
            'derived_from' => $this->derivedFrom,
        ];
    }
}
