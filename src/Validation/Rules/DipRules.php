<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation\Rules;

use KraenzleRitter\EarkIp\Specification;
use KraenzleRitter\EarkIp\Validation\RuleSet;
use KraenzleRitter\EarkIp\Validation\ValidationContext;
use KraenzleRitter\EarkIp\Xml\Ns;

/** DIP1–DIP4 of the DIP METS profile. */
final class DipRules implements RuleSet
{
    public function apply(ValidationContext $context): void
    {
        $mets = $context->packageMets();
        if ($mets === null) {
            return;
        }
        $root = $mets->root();

        $context->expect('DIP1', $mets->filled($root, 'OBJID'), 'mets/@OBJID is missing.', $mets->at($root));
        $context->note('DIP1', 'Whether the identifier differs from that of the SIP and AIP cannot be checked.');

        $profile = (string) $mets->attribute($root, 'PROFILE');
        $accepted = Specification::DIP->acceptedProfileUrls($context->version);
        $context->expect('DIP2', in_array($profile, $accepted, true), "mets/@PROFILE must be \"{$accepted[0]}\", not \"{$profile}\".", $mets->at($root));

        $header = $mets->first('/mets:mets/mets:metsHdr');
        $context->expect('DIP3', $mets->attribute($header, 'OAISPACKAGETYPE', Ns::CSIP) === 'DIP', 'metsHdr/@csip:OAISPACKAGETYPE must be "DIP".', $mets->at($header ?? $root));

        $dmdSecs = $mets->elements('/mets:mets/mets:dmdSec');
        if ($dmdSecs === []) {
            $context->notApplicable('DIP4');
        }
        foreach ($dmdSecs as $dmdSec) {
            $context->expect('DIP4', $mets->attribute($dmdSec, 'STATUS') === 'CURRENT', 'dmdSec/@STATUS should be "CURRENT" in a DIP.', $mets->at($dmdSec));
        }
    }
}
