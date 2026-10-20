<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation;

final class Violation
{
    /**
     * @param  Severity|null  $severity  set when the violation weighs less than the
     *                                   requirement's level, e.g. the SHOULD part of a MUST requirement
     */
    public function __construct(
        public readonly string $message,
        public readonly ?Location $location = null,
        public readonly ?Severity $severity = null,
    ) {}
}
