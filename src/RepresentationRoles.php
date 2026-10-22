<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp;

use KraenzleRitter\EarkIp\Model\Package;
use KraenzleRitter\EarkIp\Model\Premis;
use KraenzleRitter\EarkIp\Model\PremisObject;
use KraenzleRitter\EarkIp\Model\RepresentationRole;

/**
 * Proposes what each representation of a package is for. CSIP leaves
 * this open, so the proposal rests on PREMIS when it says something,
 * else on the names, else on the order. The first rule that applies:
 *
 * 1. A migration or normalization event with sources in A and outcomes
 *    in B: A is the original, B the master.
 * 2. A derivation relationship (has source / is source of), B derived
 *    from A: B is the master when A is the original, else A is the
 *    master and B the access copy. A chain of three is original,
 *    master, access.
 * 3. The name: access, web, display, derivative, nutzung → access;
 *    original, submission, delivered, source → original; master,
 *    preservation → master.
 * 4. Otherwise the first representation is the master, the rest
 *    unknown.
 *
 * There is exactly one master and at most one access copy; any
 * further one becomes unknown.
 */
final class RepresentationRoles
{
    private const NAMES = [
        'access' => ['access', 'web', 'display', 'derivative', 'nutzung'],
        'original' => ['original', 'submission', 'delivered', 'source'],
        'master' => ['master', 'preservation'],
    ];

    private const CONVERSIONS = ['migration', 'normalization', 'normalisation'];

    /** @var list<string> */
    private array $names = [];

    /** @var array<string, string> identifier => representation */
    private array $located = [];

    /** @return list<RepresentationRole> in natural order of the names */
    public static function propose(Package $package, Premis $premis): array
    {
        return (new self)->run($package, $premis);
    }

    /** @return list<RepresentationRole> */
    private function run(Package $package, Premis $premis): array
    {
        $this->names = array_map(fn ($representation) => $representation->name, $package->representations);
        usort($this->names, 'strnatcmp');
        if ($this->names === []) {
            return [];
        }

        foreach ($premis->objects as $object) {
            $representation = $this->representationOf($object);
            if ($representation !== null) {
                foreach ($object->identifiers as $identifier) {
                    $this->located[$identifier] = $representation;
                }
            }
        }

        [$events, $relationships, $derived] = $this->edges($premis);

        /** @var array<string, array{RepresentationFunction, string, string}> $roles */
        $roles = [];
        foreach ($events as [$from, $to]) {
            $roles[$from] ??= [RepresentationFunction::Original, RepresentationRole::BASIS_EVENT, "source of a migration or normalization whose outcome is in {$to}"];
            $roles[$to] ??= [RepresentationFunction::Master, RepresentationRole::BASIS_EVENT, "outcome of a migration or normalization of files in {$from}"];
        }
        $this->relationships($relationships, $roles);
        foreach ($this->names as $name) {
            if (! isset($roles[$name]) && ($function = $this->byName($name)) !== null) {
                $roles[$name] = [$function, RepresentationRole::BASIS_NAME, "named {$name}"];
            }
        }
        $this->limit($roles);

        $proposal = [];
        foreach ($this->names as $name) {
            [$function, $basis, $reason] = $roles[$name] ?? [RepresentationFunction::Unknown, RepresentationRole::BASIS_DEFAULT, 'nothing says what it is for'];
            $proposal[] = new RepresentationRole($name, $function, $basis, $reason, array_key_exists($name, $derived), $derived[$name] ?? null);
        }

        return $proposal;
    }

    /**
     * Edges between representations, source first, from events and from
     * relationships; and, per representation, where its derived files
     * come from (null when from outside the package).
     *
     * @return array{list<array{string, string}>, list<array{string, string}>, array<string, string|null>}
     */
    private function edges(Premis $premis): array
    {
        $events = [];
        $relationships = [];
        $derived = [];
        $note = function (?string $from, string $to) use (&$derived): void {
            if ($from !== $to && (! array_key_exists($to, $derived) || $derived[$to] === null)) {
                $derived[$to] = $from;
            }
        };

        foreach ($premis->events as $event) {
            if (! in_array(strtolower(trim($event->type)), self::CONVERSIONS, true)) {
                continue;
            }
            foreach ($event->linked('outcome') as $outcome) {
                $to = $this->locate($outcome);
                if ($to === null) {
                    continue;
                }
                foreach ($event->linked('source') as $source) {
                    $from = $this->locate($source);
                    $note($from, $to);
                    if ($from !== null && $from !== $to) {
                        $events[] = [$from, $to];
                    }
                }
            }
        }

        foreach ($premis->objects as $object) {
            $here = $this->representationOf($object);
            foreach ($object->relationships as $relationship) {
                if (strcasecmp(trim($relationship->type), 'derivation') !== 0) {
                    continue;
                }
                $subType = strtolower(trim((string) $relationship->subType));
                foreach ($relationship->related as $identifier) {
                    $there = $this->locate($identifier);
                    [$from, $to] = match ($subType) {
                        'has source' => [$there, $here],
                        'is source of' => [$here, $there],
                        default => [null, null],
                    };
                    if ($to === null) {
                        continue;
                    }
                    $note($from, $to);
                    if ($from !== null && $from !== $to) {
                        $relationships[] = [$from, $to];
                    }
                }
            }
        }

        return [array_values(array_unique($events, SORT_REGULAR)), array_values(array_unique($relationships, SORT_REGULAR)), $derived];
    }

    /**
     * Rule 2.
     *
     * @param  list<array{string, string}>  $edges
     * @param  array<string, array{RepresentationFunction, string, string}>  $roles
     */
    private function relationships(array $edges, array &$roles): void
    {
        $depth = $this->depths($edges);
        if ($depth === []) {
            return;
        }
        if (max($depth) >= 2) {
            foreach ($depth as $name => $level) {
                $function = match (true) {
                    $level === 0 => RepresentationFunction::Original,
                    $level === 1 => RepresentationFunction::Master,
                    default => RepresentationFunction::Access,
                };
                $roles[$name] ??= [$function, RepresentationRole::BASIS_RELATIONSHIP, "step {$level} of a chain of derivations"];
            }

            return;
        }

        foreach ($edges as [$from, $to]) {
            $fromFunction = $roles[$from][0] ?? null;
            $toFunction = $roles[$to][0] ?? null;
            if ($fromFunction === RepresentationFunction::Original) {
                $roles[$to] ??= [RepresentationFunction::Master, RepresentationRole::BASIS_RELATIONSHIP, "derived from the original in {$from}"];
            } elseif ($toFunction === RepresentationFunction::Master) {
                $roles[$from] ??= [RepresentationFunction::Original, RepresentationRole::BASIS_RELATIONSHIP, "source of the master in {$to}"];
            } else {
                $roles[$from] ??= [RepresentationFunction::Master, RepresentationRole::BASIS_RELATIONSHIP, "source of files derived in {$to}"];
                $roles[$to] ??= [RepresentationFunction::Access, RepresentationRole::BASIS_RELATIONSHIP, "derived from files in {$from}"];
            }
        }
    }

    /**
     * Longest distance from a representation nothing derives from; a
     * cycle is cut after as many steps as there are representations.
     *
     * @param  list<array{string, string}>  $edges
     * @return array<string, int>
     */
    private function depths(array $edges): array
    {
        $depth = [];
        foreach ($edges as [$from, $to]) {
            $depth[$from] = 0;
            $depth[$to] = 0;
        }
        for ($round = 0; $round < count($depth); $round++) {
            $changed = false;
            foreach ($edges as [$from, $to]) {
                if ($depth[$to] < $depth[$from] + 1) {
                    $depth[$to] = $depth[$from] + 1;
                    $changed = true;
                }
            }
            if (! $changed) {
                break;
            }
        }

        return $depth;
    }

    /**
     * Exactly one master, at most one access copy.
     *
     * @param  array<string, array{RepresentationFunction, string, string}>  $roles
     */
    private function limit(array &$roles): void
    {
        $master = null;
        $access = null;
        foreach ($this->names as $name) {
            $function = $roles[$name][0] ?? null;
            if ($function === RepresentationFunction::Master) {
                if ($master === null) {
                    $master = $name;
                } else {
                    $roles[$name] = [RepresentationFunction::Unknown, $roles[$name][1], "a further master after {$master}; only one is allowed ({$roles[$name][2]})"];
                }
            }
            if ($function === RepresentationFunction::Access) {
                if ($access === null) {
                    $access = $name;
                } else {
                    $roles[$name] = [RepresentationFunction::Unknown, $roles[$name][1], "a further access copy after {$access}; only one is allowed ({$roles[$name][2]})"];
                }
            }
        }
        if ($master !== null) {
            return;
        }

        // The first one nothing was said about, else the first that is
        // not an original, else the first.
        $first = $this->first($roles, fn (?RepresentationFunction $function) => $function === null || $function === RepresentationFunction::Unknown)
            ?? $this->first($roles, fn (?RepresentationFunction $function) => $function !== RepresentationFunction::Original)
            ?? $this->names[0];
        $roles[$first] = [RepresentationFunction::Master, RepresentationRole::BASIS_DEFAULT, 'first representation; nothing names a master'];
    }

    /**
     * @param  array<string, array{RepresentationFunction, string, string}>  $roles
     * @param  callable(?RepresentationFunction): bool  $matches
     */
    private function first(array $roles, callable $matches): ?string
    {
        foreach ($this->names as $name) {
            if ($matches($roles[$name][0] ?? null)) {
                return $name;
            }
        }

        return null;
    }

    private function byName(string $name): ?RepresentationFunction
    {
        $name = strtolower($name);
        foreach (self::NAMES as $function => $words) {
            foreach ($words as $word) {
                if (str_contains($name, $word)) {
                    return RepresentationFunction::from($function);
                }
            }
        }

        return null;
    }

    /** The representation an object belongs to (see design D2), or null. */
    private function representationOf(PremisObject $object): ?string
    {
        foreach ([...$object->identifiers, ...$object->contentLocations, ...array_filter([$object->originalName])] as $value) {
            if (($name = $this->fromPath($value)) !== null) {
                return $name;
            }
        }
        if ($object->category === 'representation') {
            foreach ([...$object->identifiers, ...array_filter([$object->originalName])] as $value) {
                if (in_array($value, $this->names, true)) {
                    return $value;
                }
            }
        }
        if (in_array($object->category, ['file', 'bitstream'], true)) {
            return $this->fromPath($object->premisFile);
        }

        return null;
    }

    /** An identifier from an event or relationship: a described object, else a path. */
    private function locate(string $identifier): ?string
    {
        return $this->located[$identifier] ?? $this->fromPath($identifier) ?? (in_array($identifier, $this->names, true) ? $identifier : null);
    }

    private function fromPath(string $value): ?string
    {
        $value = str_replace('\\', '/', preg_replace('#^file:(//)?#i', '', trim($value)) ?? '');
        if (preg_match('#(?:^|/)representations/([^/]+)(?:/|$)#', $value, $match) && in_array($match[1], $this->names, true)) {
            return $match[1];
        }

        return null;
    }
}
