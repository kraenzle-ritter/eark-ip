<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation;

interface RuleSet
{
    public function apply(ValidationContext $context): void;
}
