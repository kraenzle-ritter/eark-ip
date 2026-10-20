<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation;

use KraenzleRitter\EarkIp\Catalog\Level;

/** How much a violation weighs: a violated MUST is an error, a SHOULD a warning, a MAY a note. */
enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Info = 'info';

    public static function forLevel(Level $level): self
    {
        return match ($level) {
            Level::MUST => self::Error,
            Level::SHOULD => self::Warning,
            Level::MAY => self::Info,
        };
    }
}
