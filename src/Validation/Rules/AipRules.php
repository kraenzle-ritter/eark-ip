<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation\Rules;

use KraenzleRitter\EarkIp\Specification;
use KraenzleRitter\EarkIp\Validation\RuleSet;
use KraenzleRitter\EarkIp\Validation\ValidationContext;
use KraenzleRitter\EarkIp\Xml\Ns;

/** AIPM1–AIPM7 of the AIP METS profile. */
final class AipRules implements RuleSet
{
    public function apply(ValidationContext $context): void
    {
        $mets = $context->packageMets();
        if ($mets === null) {
            return;
        }
        $root = $mets->root();

        $context->notTestable('AIPM1', 'Whether mets/@OBJID stays the same over the life-cycle of the AIP cannot be seen in one package.');

        $profile = (string) $mets->attribute($root, 'PROFILE');
        $accepted = Specification::AIP->acceptedProfileUrls($context->version);
        $context->expect('AIPM2', in_array($profile, $accepted, true), "mets/@PROFILE must be \"{$accepted[0]}\", not \"{$profile}\".", $mets->at($root));

        $header = $mets->first('/mets:mets/mets:metsHdr');
        $context->expect('AIPM3', $mets->attribute($header, 'OAISPACKAGETYPE', Ns::CSIP) === 'AIP', 'metsHdr/@csip:OAISPACKAGETYPE must be "AIP".', $mets->at($header ?? $root));

        $dmdSecs = $mets->elements('/mets:mets/mets:dmdSec');
        if ($dmdSecs === []) {
            $context->notApplicable('AIPM4');
        } else {
            $context->expect('AIPM4', $mets->elements("/mets:mets/mets:dmdSec[@STATUS = 'CURRENT']") !== [], 'No dmdSec has @STATUS "CURRENT".', $mets->at($dmdSecs[0]));
        }

        $provenance = $mets->elements('/mets:mets/mets:amdSec/mets:digiprovMD/mets:mdRef');
        if (! $context->expect('AIPM5', $provenance !== [], 'The AIP has no digital provenance metadata (amdSec/digiprovMD/mdRef).', $mets->at($root))) {
            $context->notApplicable('AIPM6', 'AIPM7');

            return;
        }

        $premis = array_values(array_filter($provenance, fn ($mdRef) => $mets->attribute($mdRef, 'MDTYPE') === 'PREMIS'));
        if (! $context->expect('AIPM6', $premis !== [], 'No digiprovMD/mdRef has @MDTYPE "PREMIS".', $mets->at($provenance[0]))) {
            $context->notApplicable('AIPM7');

            return;
        }
        $version3 = array_filter($premis, fn ($mdRef) => str_starts_with((string) $mets->attribute($mdRef, 'MDTYPEVERSION'), '3'));
        $context->expect('AIPM7', $version3 !== [], 'The PREMIS metadata is not marked as version 3 (@MDTYPEVERSION).', $mets->at($premis[0]));
    }
}
