<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation\Rules;

use DateTimeImmutable;
use DOMElement;
use KraenzleRitter\EarkIp\Catalog\Vocabulary;
use KraenzleRitter\EarkIp\Validation\MetsUnderTest;
use KraenzleRitter\EarkIp\Validation\RuleSet;
use KraenzleRitter\EarkIp\Validation\Severity;
use KraenzleRitter\EarkIp\Validation\ValidationContext;
use KraenzleRitter\EarkIp\Xml\Ns;

/** CSIP117, CSIP7–CSIP16: the METS header and the software agent, in every METS document. */
final class HeaderRules implements RuleSet
{
    public function apply(ValidationContext $context): void
    {
        foreach ($context->documents as $mets) {
            $header = $mets->first('/mets:mets/mets:metsHdr');
            if (! $context->expect('CSIP117', $header !== null, 'mets/metsHdr is missing.', $mets->at($mets->root()))) {
                continue;
            }
            assert($header instanceof DOMElement);
            $this->dates($context, $mets, $header);
            $this->packageType($context, $mets, $header);
            $this->softwareAgent($context, $mets, $header);
        }
    }

    private function dates(ValidationContext $context, MetsUnderTest $mets, DOMElement $header): void
    {
        $context->expect('CSIP7', $mets->filled($header, 'CREATEDATE'), 'mets/metsHdr/@CREATEDATE is missing.', $mets->at($header));

        $modified = $mets->attribute($header, 'LASTMODDATE');
        if (! $context->expect('CSIP8', $modified !== null, 'mets/metsHdr/@LASTMODDATE is missing.', $mets->at($header))) {
            return;
        }
        $date = DateTimeImmutable::createFromFormat(DATE_ATOM, (string) $modified)
            ?: (strtotime((string) $modified) !== false ? new DateTimeImmutable((string) $modified) : null);
        if ($date !== null) {
            $context->expect(
                'CSIP8',
                $date <= new DateTimeImmutable('+1 day'),
                "mets/metsHdr/@LASTMODDATE {$modified} lies in the future.",
                $mets->at($header),
                Severity::Error,
            );
        }
    }

    private function packageType(ValidationContext $context, MetsUnderTest $mets, DOMElement $header): void
    {
        $type = $mets->attribute($header, 'OAISPACKAGETYPE', Ns::CSIP);
        if (! $context->expect('CSIP9', $type !== null, 'mets/metsHdr/@csip:OAISPACKAGETYPE is missing.', $mets->at($header))) {
            return;
        }
        $context->expect(
            'CSIP9',
            Vocabulary::contains($context->version, Vocabulary::OAIS_PACKAGE_TYPE, (string) $type),
            "mets/metsHdr/@csip:OAISPACKAGETYPE \"{$type}\" is not a term of the OAIS Package Type vocabulary.",
            $mets->at($header),
        );
    }

    /**
     * The software agent CSIP requires: ROLE CREATOR, TYPE OTHER,
     * OTHERTYPE SOFTWARE, one name, one note with the version. Each
     * attribute is its own requirement, so the candidate is narrowed
     * step by step.
     */
    private function softwareAgent(ValidationContext $context, MetsUnderTest $mets, DOMElement $header): void
    {
        $agents = $mets->elements('mets:agent', $header);
        if (! $context->expect('CSIP10', $agents !== [], 'mets/metsHdr has no agent.', $mets->at($header))) {
            return;
        }

        $creators = array_values(array_filter($agents, fn (DOMElement $agent) => $mets->attribute($agent, 'ROLE') === 'CREATOR'));
        if (! $context->expect('CSIP11', $creators !== [], 'No mets/metsHdr/agent has @ROLE "CREATOR" (the software that created the package).', $mets->at($header))) {
            return;
        }

        $typed = array_values(array_filter($creators, fn (DOMElement $agent) => $mets->attribute($agent, 'TYPE') === 'OTHER'));
        if (! $context->expect('CSIP12', $typed !== [], 'The CREATOR agent has no @TYPE "OTHER".', $mets->at($creators[0]))) {
            return;
        }

        $software = array_values(array_filter($typed, fn (DOMElement $agent) => $mets->attribute($agent, 'OTHERTYPE') === 'SOFTWARE'));
        if (! $context->expect('CSIP13', $software !== [], 'The CREATOR agent has no @OTHERTYPE "SOFTWARE".', $mets->at($typed[0]))) {
            return;
        }

        $agent = $software[0];
        $names = $mets->elements('mets:name', $agent);
        $context->expect(
            'CSIP14',
            count($names) === 1 && trim($names[0]->textContent) !== '',
            $names === [] ? 'The software agent has no name.' : (count($names) > 1 ? 'The software agent has more than one name.' : 'The name of the software agent is empty.'),
            $mets->at($agent),
        );

        $notes = $mets->elements('mets:note', $agent);
        if (! $context->expect('CSIP15', $notes !== [], 'The software agent has no note with its version.', $mets->at($agent))) {
            return;
        }
        $context->expect('CSIP15', trim($notes[0]->textContent) !== '', 'The note of the software agent is empty.', $mets->at($notes[0]));
        if (count($notes) > 1) {
            $context->fail('CSIP15', 'The software agent has more than one note; CSIP expects one note with the software version.', $mets->at($agent), Severity::Warning);
        }

        $versioned = array_filter($notes, fn (DOMElement $note) => $mets->attribute($note, 'NOTETYPE', Ns::CSIP) === 'SOFTWARE VERSION');
        $context->expect('CSIP16', $versioned !== [], 'The note of the software agent has no @csip:NOTETYPE "SOFTWARE VERSION".', $mets->at($notes[0]));
    }
}
