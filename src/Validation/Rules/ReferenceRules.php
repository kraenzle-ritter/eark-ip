<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation\Rules;

use KraenzleRitter\EarkIp\Validation\Location;
use KraenzleRitter\EarkIp\Validation\RuleSet;
use KraenzleRitter\EarkIp\Validation\ValidationContext;

/**
 * EARKIP-UNREFERENCED: every file is referenced from some METS document.
 * Runs after the rules that resolve references. Representation METS
 * files count as referenced when the package METS points to them.
 */
final class ReferenceRules implements RuleSet
{
    public function apply(ValidationContext $context): void
    {
        $context->checked('EARKIP-UNREFERENCED');
        foreach ($context->source->files() as $file) {
            if ($file === 'METS.xml' || $context->isReferenced($file)) {
                continue;
            }
            $context->fail('EARKIP-UNREFERENCED', "{$file} is not referenced from any METS document.", new Location($file));
        }
    }
}
