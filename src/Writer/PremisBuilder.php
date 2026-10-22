<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Writer;

use DOMDocument;
use DOMElement;
use KraenzleRitter\EarkIp\Exception\EarkIpException;
use KraenzleRitter\EarkIp\Xml\Ns;

/**
 * Builds PREMIS 3 preservation metadata: objects (files and
 * representations) with their relationships, events and agents, linked
 * by local identifiers.
 * What goes in is the caller's business; this class keeps the element
 * order of the PREMIS schema.
 *
 *     $premis = (new PremisBuilder)
 *         ->agent('agate', 'agate', 'software', '1.4.0')
 *         ->file('rep1/data/a.tif', size: 1024, sha256: '…', puid: 'fmt/353')
 *         ->event('ev-1', 'normalization', new DateTimeImmutable, agents: ['agate' => 'executing program'], objects: ['rep1/data/a.tif' => 'outcome']);
 */
final class PremisBuilder
{
    /** @var array<string, array<string, mixed>> */
    private array $objects = [];

    /** @var array<string, array<string, mixed>> */
    private array $events = [];

    /** @var array<string, array<string, mixed>> */
    private array $agents = [];

    /**
     * A file object. Give the checksum in any algorithm PREMIS knows,
     * e.g. ['SHA-256' => '…', 'MD5' => '…'].
     *
     * @param  array<string, string>  $fixity
     */
    public function file(
        string $id,
        ?int $size = null,
        ?string $sha256 = null,
        ?string $puid = null,
        ?string $formatName = null,
        ?string $formatVersion = null,
        ?string $originalName = null,
        array $fixity = [],
    ): self {
        if ($sha256 !== null) {
            $fixity = ['SHA-256' => $sha256] + $fixity;
        }
        $this->objects[$id] = ['type' => 'file', 'size' => $size, 'fixity' => $fixity, 'puid' => $puid, 'formatName' => $formatName, 'formatVersion' => $formatVersion, 'originalName' => $originalName];

        return $this;
    }

    /** A file object for a file on disk: size and SHA-256 are computed. */
    public function fileFromPath(string $id, string $path, ?string $puid = null, ?string $formatName = null): self
    {
        if (! is_file($path)) {
            throw new EarkIpException("File not found: {$path}");
        }

        return $this->file($id, (int) filesize($path), (string) hash_file('sha256', $path), $puid, $formatName, originalName: basename($path));
    }

    public function representation(string $id, ?string $originalName = null): self
    {
        $this->objects[$id] = ['type' => 'representation', 'originalName' => $originalName];

        return $this;
    }

    /** An intellectual entity, e.g. the package or the SIP an AIP was built from. */
    public function intellectualEntity(string $id, ?string $originalName = null): self
    {
        $this->objects[$id] = ['type' => 'intellectualEntity', 'originalName' => $originalName];

        return $this;
    }

    /**
     * An event, e.g. "ingestion" or "normalization" (Library of Congress
     * event type vocabulary).
     *
     * @param  array<string, string>  $agents  agent ID => role, e.g. "executing program"
     * @param  array<string, string>  $objects  object ID => role, e.g. "source" or "outcome"
     */
    public function event(
        string $id,
        string $type,
        \DateTimeImmutable $dateTime,
        ?string $detail = null,
        ?string $outcome = null,
        array $agents = [],
        array $objects = [],
    ): self {
        $this->events[$id] = ['type' => $type, 'dateTime' => $dateTime, 'detail' => $detail, 'outcome' => $outcome, 'agents' => $agents, 'objects' => $objects];

        return $this;
    }

    /**
     * A relationship of a described object to another, e.g. derivation /
     * "has source" from an access copy to its master (Library of
     * Congress vocabularies relationshipType and relationshipSubType).
     */
    public function relationship(string $from, string $type, string $subType, string $to): self
    {
        if (! isset($this->objects[$from])) {
            throw new EarkIpException("Relationship from {$from}, which is not described.");
        }
        $this->objects[$from]['relationships'][] = ['type' => $type, 'subType' => $subType, 'to' => $to];

        return $this;
    }

    /** An agent: "software", "organization" or "person". */
    public function agent(string $id, string $name, string $type = 'software', ?string $version = null, ?string $note = null): self
    {
        $this->agents[$id] = ['name' => $name, 'type' => $type, 'version' => $version, 'note' => $note];

        return $this;
    }

    public function toXml(): string
    {
        foreach ($this->events as $id => $event) {
            foreach (array_keys($event['agents']) as $agent) {
                if (! isset($this->agents[$agent])) {
                    // AIP16, AIP18: agents linked from events must be described.
                    throw new EarkIpException("Event {$id} names agent {$agent}, which is not described.");
                }
            }
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElementNS(Ns::PREMIS, 'premis');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', Ns::XSI);
        $root->setAttribute('version', '3.0');
        $document->appendChild($root);

        foreach ($this->objects as $id => $object) {
            $this->object($document, $root, $id, $object);
        }
        foreach ($this->events as $id => $event) {
            $this->eventElement($document, $root, $id, $event);
        }
        foreach ($this->agents as $id => $agent) {
            $element = $this->add($document, $root, 'agent');
            $this->identifier($document, $element, 'agentIdentifier', 'agent', $id);
            $this->add($document, $element, 'agentName', $agent['name']);
            $this->add($document, $element, 'agentType', $agent['type']);
            if ($agent['version'] !== null) {
                $this->add($document, $element, 'agentVersion', $agent['version']);
            }
            if ($agent['note'] !== null) {
                $this->add($document, $element, 'agentNote', $agent['note']);
            }
        }

        return (string) $document->saveXML();
    }

    /** @param  array<string, mixed>  $object */
    private function object(DOMDocument $document, DOMElement $root, string $id, array $object): void
    {
        $element = $this->add($document, $root, 'object');
        $element->setAttributeNS(Ns::XSI, 'xsi:type', $object['type']);
        $this->identifier($document, $element, 'objectIdentifier', 'object', $id);

        if ($object['type'] === 'file') {
            $characteristics = $this->add($document, $element, 'objectCharacteristics');
            $this->add($document, $characteristics, 'compositionLevel', '0');
            foreach ($object['fixity'] as $algorithm => $digest) {
                $fixity = $this->add($document, $characteristics, 'fixity');
                $this->add($document, $fixity, 'messageDigestAlgorithm', (string) $algorithm);
                $this->add($document, $fixity, 'messageDigest', (string) $digest);
            }
            if ($object['size'] !== null) {
                $this->add($document, $characteristics, 'size', (string) $object['size']);
            }
            $format = $this->add($document, $characteristics, 'format');
            if ($object['formatName'] !== null || $object['puid'] === null) {
                $designation = $this->add($document, $format, 'formatDesignation');
                $this->add($document, $designation, 'formatName', $object['formatName'] ?? 'unknown');
                if ($object['formatVersion'] !== null) {
                    $this->add($document, $designation, 'formatVersion', $object['formatVersion']);
                }
            }
            if ($object['puid'] !== null) {
                $registry = $this->add($document, $format, 'formatRegistry');
                $this->add($document, $registry, 'formatRegistryName', 'PRONOM');
                $this->add($document, $registry, 'formatRegistryKey', $object['puid']);
            }
        }
        if ($object['originalName'] !== null) {
            $this->add($document, $element, 'originalName', $object['originalName']);
        }
        foreach ($object['relationships'] ?? [] as $relationship) {
            $related = $this->add($document, $element, 'relationship');
            $this->add($document, $related, 'relationshipType', $relationship['type']);
            $this->add($document, $related, 'relationshipSubType', $relationship['subType']);
            $identifier = $this->add($document, $related, 'relatedObjectIdentifier');
            $this->add($document, $identifier, 'relatedObjectIdentifierType', 'local');
            $this->add($document, $identifier, 'relatedObjectIdentifierValue', $relationship['to']);
        }
    }

    /** @param  array<string, mixed>  $event */
    private function eventElement(DOMDocument $document, DOMElement $root, string $id, array $event): void
    {
        $element = $this->add($document, $root, 'event');
        $this->identifier($document, $element, 'eventIdentifier', 'event', $id);
        $this->add($document, $element, 'eventType', $event['type']);
        $this->add($document, $element, 'eventDateTime', $event['dateTime']->format(DATE_ATOM));
        if ($event['detail'] !== null) {
            $this->add($document, $this->add($document, $element, 'eventDetailInformation'), 'eventDetail', $event['detail']);
        }
        if ($event['outcome'] !== null) {
            $this->add($document, $this->add($document, $element, 'eventOutcomeInformation'), 'eventOutcome', $event['outcome']);
        }
        foreach ($event['agents'] as $agent => $role) {
            $link = $this->add($document, $element, 'linkingAgentIdentifier');
            $this->add($document, $link, 'linkingAgentIdentifierType', 'local');
            $this->add($document, $link, 'linkingAgentIdentifierValue', (string) $agent);
            $this->add($document, $link, 'linkingAgentRole', $role);
        }
        foreach ($event['objects'] as $object => $role) {
            $link = $this->add($document, $element, 'linkingObjectIdentifier');
            $this->add($document, $link, 'linkingObjectIdentifierType', 'local');
            $this->add($document, $link, 'linkingObjectIdentifierValue', (string) $object);
            $this->add($document, $link, 'linkingObjectRole', $role);
        }
    }

    private function identifier(DOMDocument $document, DOMElement $parent, string $name, string $prefix, string $value): void
    {
        $identifier = $this->add($document, $parent, $name);
        $this->add($document, $identifier, $prefix.'IdentifierType', 'local');
        $this->add($document, $identifier, $prefix.'IdentifierValue', $value);
    }

    private function add(DOMDocument $document, DOMElement $parent, string $name, ?string $text = null): DOMElement
    {
        $element = $document->createElementNS(Ns::PREMIS, $name);
        if ($text !== null) {
            $element->appendChild($document->createTextNode($text));
        }
        $parent->appendChild($element);

        return $element;
    }
}
