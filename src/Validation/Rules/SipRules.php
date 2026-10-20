<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation\Rules;

use DOMElement;
use KraenzleRitter\EarkIp\Catalog\Vocabulary;
use KraenzleRitter\EarkIp\Specification;
use KraenzleRitter\EarkIp\Validation\MetsUnderTest;
use KraenzleRitter\EarkIp\Validation\RuleSet;
use KraenzleRitter\EarkIp\Validation\Severity;
use KraenzleRitter\EarkIp\Validation\ValidationContext;
use KraenzleRitter\EarkIp\Xml\Ns;

/**
 * SIP1–SIP35. The agents are told apart by role and type: archival
 * creator ROLE "ARCHIVIST"; submitting agent ROLE "CREATOR" with TYPE
 * "ORGANIZATION" or "INDIVIDUAL" (as commons-ip reads it; the software
 * agent of CSIP has TYPE "OTHER"); contact person ROLE "OTHER" with
 * OTHERROLE "SUBMITTER", as in the example of the profile; preservation
 * agent ROLE "PRESERVATION".
 */
final class SipRules implements RuleSet
{
    public function apply(ValidationContext $context): void
    {
        $mets = $context->packageMets();
        if ($mets === null) {
            return;
        }
        $root = $mets->root();

        $label = $mets->attribute($root, 'LABEL');
        $label === null
            ? $context->notApplicable('SIP1')
            : $context->expect('SIP1', trim($label) !== '', 'mets/@LABEL is empty.', $mets->at($root), Severity::Info);

        $profile = (string) $mets->attribute($root, 'PROFILE');
        $expected = Specification::SIP->profileUrl($context->version);
        $context->expect('SIP2', $profile === $expected, "mets/@PROFILE must be \"{$expected}\" for E-ARK SIP {$context->version->value}, not \"{$profile}\".", $mets->at($root));

        $header = $mets->first('/mets:mets/mets:metsHdr');
        if ($header === null) {
            $context->fail('SIP4', 'mets/metsHdr is missing.', $mets->at($root));

            return;
        }
        $this->header($context, $mets, $header);
        $this->agents($context, $mets, $header);
        $this->fileFormats($context);
    }

    private function header(ValidationContext $context, MetsUnderTest $mets, DOMElement $header): void
    {
        $status = $mets->attribute($header, 'RECORDSTATUS');
        $status === null
            ? $context->notApplicable('SIP3')
            : $context->expect('SIP3', Vocabulary::contains($context->version, Vocabulary::RECORD_STATUS, $status), "metsHdr/@RECORDSTATUS \"{$status}\" is not a term of the RECORDSTATUS vocabulary.", $mets->at($header), Severity::Warning);

        $type = $mets->attribute($header, 'OAISPACKAGETYPE', Ns::CSIP);
        $context->expect('SIP4', $type === 'SIP', 'metsHdr/@csip:OAISPACKAGETYPE must be "SIP".', $mets->at($header));

        $types = ['SIP5' => 'SUBMISSIONAGREEMENT', 'SIP6' => 'PREVIOUSSUBMISSIONAGREEMENT', 'SIP7' => 'REFERENCECODE', 'SIP8' => 'PREVIOUSREFERENCECODE'];
        foreach ($types as $id => $value) {
            $ids = $mets->elements("mets:altRecordID[@TYPE = '{$value}']", $header);
            if ($ids === []) {
                $context->notApplicable($id);

                continue;
            }
            foreach ($ids as $element) {
                $context->expect($id, trim($element->textContent) !== '', "altRecordID[@TYPE=\"{$value}\"] is empty.", $mets->at($element), Severity::Warning);
            }
            if (in_array($id, ['SIP5', 'SIP7'], true) && count($ids) > 1) {
                $context->fail($id, "altRecordID[@TYPE=\"{$value}\"] occurs more than once.", $mets->at($header), Severity::Warning);
            }
        }
    }

    private function agents(ValidationContext $context, MetsUnderTest $mets, DOMElement $header): void
    {
        $agents = $mets->elements('mets:agent', $header);
        $role = fn (string $role) => array_values(array_filter($agents, fn (DOMElement $agent) => $mets->attribute($agent, 'ROLE') === $role));

        // Archival creator, SIP9–SIP14.
        $creators = $role('ARCHIVIST');
        $this->optionalAgent($context, $mets, $header, $creators, ['SIP9', 'SIP10', 'SIP11', 'SIP12', 'SIP13', 'SIP14'], 'archival creator', ['ORGANIZATION', 'INDIVIDUAL']);

        // Submitting agent, SIP15–SIP20: exactly one.
        $submitters = array_values(array_filter(
            $role('CREATOR'),
            fn (DOMElement $agent) => in_array($mets->attribute($agent, 'TYPE'), ['ORGANIZATION', 'INDIVIDUAL'], true),
        ));
        $context->expect('SIP15', count($submitters) === 1, $submitters === []
            ? 'There is no submitting agent (metsHdr/agent with @ROLE "CREATOR" and @TYPE "ORGANIZATION" or "INDIVIDUAL").'
            : 'There is more than one submitting agent.', $mets->at($header));
        if ($submitters !== []) {
            $this->agentDetails($context, $mets, $submitters[0], ['SIP16', 'SIP17', 'SIP18', 'SIP19', 'SIP20'], 'submitting agent', ['ORGANIZATION', 'INDIVIDUAL']);
        } else {
            $context->notApplicable('SIP16', 'SIP17', 'SIP18', 'SIP19', 'SIP20');
        }

        // Contact persons, SIP21–SIP25.
        $contacts = array_values(array_filter($role('OTHER'), fn (DOMElement $agent) => $mets->attribute($agent, 'OTHERROLE') === 'SUBMITTER'));
        $context->notTestable('SIP22', 'The profile names the contact person\'s role "CREATOR" but its example uses ROLE "OTHER" with OTHERROLE "SUBMITTER", and SIP15 allows only one CREATOR besides the software; contact persons are recognised by OTHERROLE "SUBMITTER".');
        if ($contacts === []) {
            $context->notApplicable('SIP21', 'SIP23', 'SIP24', 'SIP25');
        }
        foreach ($contacts as $contact) {
            $context->checked('SIP21');
            $context->expect('SIP23', $mets->attribute($contact, 'TYPE') === 'INDIVIDUAL', 'The contact person must have @TYPE "INDIVIDUAL".', $mets->at($contact));
            $this->name($context, $mets, $contact, 'SIP24', 'contact person');
            $mets->elements('mets:note', $contact) === [] ? $context->notApplicable('SIP25') : $context->checked('SIP25');
        }

        // Preservation agent, SIP26–SIP31.
        $this->optionalAgent($context, $mets, $header, $role('PRESERVATION'), ['SIP26', 'SIP27', 'SIP28', 'SIP29', 'SIP30', 'SIP31'], 'preservation agent', ['ORGANIZATION']);
    }

    /**
     * An agent that may occur at most once; when it does, its role,
     * type, name and identification note are checked.
     *
     * @param  list<DOMElement>  $agents
     * @param  list<string>  $ids  [wrapper, role, type, name, note, note type]
     * @param  list<string>  $types
     */
    private function optionalAgent(ValidationContext $context, MetsUnderTest $mets, DOMElement $header, array $agents, array $ids, string $name, array $types): void
    {
        if ($agents === []) {
            $context->notApplicable(...$ids);

            return;
        }
        $context->expect($ids[0], count($agents) === 1, "There is more than one {$name}.", $mets->at($header), Severity::Warning);
        $this->agentDetails($context, $mets, $agents[0], array_slice($ids, 1), $name, $types);
    }

    /**
     * @param  list<string>  $ids  [role, type, name, note, note type]
     * @param  list<string>  $types
     */
    private function agentDetails(ValidationContext $context, MetsUnderTest $mets, DOMElement $agent, array $ids, string $name, array $types): void
    {
        [$role, $type, $agentName, $note, $noteType] = $ids;
        $context->checked($role);
        $context->expect($type, in_array($mets->attribute($agent, 'TYPE'), $types, true), "The {$name} must have @TYPE ".implode(' or ', array_map(fn ($t) => "\"{$t}\"", $types)).'.', $mets->at($agent));
        $this->name($context, $mets, $agent, $agentName, $name);

        $notes = $mets->elements('mets:note', $agent);
        if ($notes === []) {
            $context->notApplicable($note, $noteType);

            return;
        }
        $context->checked($note);
        foreach ($notes as $element) {
            $context->expect($noteType, $mets->attribute($element, 'NOTETYPE', Ns::CSIP) === 'IDENTIFICATIONCODE', "The note of the {$name} must have @csip:NOTETYPE \"IDENTIFICATIONCODE\".", $mets->at($element));
        }
    }

    private function name(ValidationContext $context, MetsUnderTest $mets, DOMElement $agent, string $id, string $name): void
    {
        $element = $mets->first('mets:name', $agent);
        $context->expect($id, $element !== null && trim($element->textContent) !== '', "The {$name} has no name.", $mets->at($agent));
    }

    /** SIP32–SIP35: the optional file format attributes are not empty when present (both spellings of the SIP schema). */
    private function fileFormats(ValidationContext $context): void
    {
        $attributes = [
            'SIP32' => ['FILEFORMATNAME'],
            'SIP33' => ['FILEFORMATVERSION'],
            'SIP34' => ['FILEFORMATREGISTRY', 'FORMATREGISTRY'],
            'SIP35' => ['FILEFORMATKEY', 'FORMATREGISTRYKEY'],
        ];
        foreach ($attributes as $id => $names) {
            $found = false;
            foreach ($context->documents as $mets) {
                foreach ($mets->elements('/mets:mets/mets:fileSec//mets:file') as $file) {
                    foreach ($names as $attribute) {
                        $value = $mets->attribute($file, $attribute, Ns::SIP);
                        if ($value !== null) {
                            $found = true;
                            $context->expect($id, trim($value) !== '', "file/@sip:{$attribute} is empty.", $mets->at($file), Severity::Warning);
                        }
                    }
                }
            }
            if (! $found) {
                $context->notApplicable($id);
            }
        }
    }
}
