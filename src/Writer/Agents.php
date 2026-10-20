<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

use KraenzleRitter\EarkIp\Model\Agent;
use KraenzleRitter\EarkIp\Model\AgentNote;

/**
 * The SIP agents in the form the SIP profile and commons-ip expect
 * (see SipRules for how they are told apart).
 */
final class Agents
{
    public static function archivalCreator(string $name, string $type = 'ORGANIZATION', ?string $identificationCode = null): Agent
    {
        return new Agent('ARCHIVIST', $type, $name, self::code($identificationCode));
    }

    public static function submitter(string $name, string $type = 'ORGANIZATION', ?string $identificationCode = null): Agent
    {
        return new Agent('CREATOR', $type, $name, self::code($identificationCode));
    }

    /** @param  list<string>  $contactDetails  e.g. phone, e-mail */
    public static function contactPerson(string $name, array $contactDetails = []): Agent
    {
        return new Agent('OTHER', 'INDIVIDUAL', $name, array_map(fn (string $note) => new AgentNote($note), $contactDetails), otherRole: 'SUBMITTER');
    }

    public static function preservation(string $name, ?string $identificationCode = null): Agent
    {
        return new Agent('PRESERVATION', 'ORGANIZATION', $name, self::code($identificationCode));
    }

    /** @return list<AgentNote> */
    private static function code(?string $code): array
    {
        return $code === null ? [] : [new AgentNote($code, 'IDENTIFICATIONCODE')];
    }
}
