<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Reader;

use DOMDocument;
use DOMElement;
use KraenzleRitter\EarkIp\Exception\XmlException;
use KraenzleRitter\EarkIp\Model\MetadataReference;
use KraenzleRitter\EarkIp\Model\MetadataSection;
use KraenzleRitter\EarkIp\Model\MetsDocument;
use KraenzleRitter\EarkIp\Model\Package;
use KraenzleRitter\EarkIp\Model\Premis;
use KraenzleRitter\EarkIp\Model\PremisAgent;
use KraenzleRitter\EarkIp\Model\PremisEvent;
use KraenzleRitter\EarkIp\Model\PremisLink;
use KraenzleRitter\EarkIp\Model\PremisObject;
use KraenzleRitter\EarkIp\Model\PremisRelationship;
use KraenzleRitter\EarkIp\Source\Limits;
use KraenzleRitter\EarkIp\Source\PackageSource;
use KraenzleRitter\EarkIp\Source\Sources;
use KraenzleRitter\EarkIp\Xml\Ns;
use KraenzleRitter\EarkIp\Xml\Xml;

/**
 * Reads the PREMIS files the package METS and the representation METS
 * documents point to (digiprovMD or techMD with MDTYPE PREMIS,
 * PREMIS:OBJECT or PREMIS:EVENT). PREMIS 2 and 3. Objects, their
 * relationships, events with the objects they link and agents; the
 * rest of PREMIS is not modelled. Never fails on a single file: what
 * is missing or not readable becomes a note.
 */
final class PremisReader
{
    public const PREMIS_2 = 'info:lc/xmlns/premis-v2';

    private const TYPES = ['PREMIS', 'PREMIS:OBJECT', 'PREMIS:EVENT', 'PREMIS:AGENT', 'PREMIS:RIGHTS'];

    public function __construct(private readonly ?Limits $limits = null) {}

    public function read(Package $package, string|PackageSource $source): Premis
    {
        $source = Sources::open($source, $this->limits);

        $paths = $this->references($package->mets);
        foreach ($package->representations as $representation) {
            if ($representation->mets !== null) {
                $paths = array_merge($paths, $this->references($representation->mets));
            }
        }

        $objects = [];
        $events = [];
        $agents = [];
        $files = [];
        $notes = [];
        foreach (array_values(array_unique($paths)) as $path) {
            if (! $source->hasFile($path)) {
                $notes[] = "{$path} is referenced as PREMIS but not in the package.";

                continue;
            }
            try {
                $document = Xml::load($source->contents($path), $path);
            } catch (XmlException $exception) {
                $notes[] = $exception->getMessage();

                continue;
            }
            $root = $document->documentElement;
            if (! $root instanceof DOMElement || ! $this->isPremis($root)) {
                $notes[] = "{$path} is not PREMIS.";

                continue;
            }
            $files[] = $path;
            foreach ($this->elements($document, 'object') as $element) {
                $objects[] = $this->object($element, $path);
            }
            foreach ($this->elements($document, 'event') as $element) {
                $events[] = $this->event($element, $path);
            }
            foreach ($this->elements($document, 'agent') as $element) {
                $agents[] = new PremisAgent(
                    $this->text($element, 'agentIdentifier', 'agentIdentifierValue'),
                    $this->text($element, 'agentName'),
                    $this->text($element, 'agentType'),
                );
            }
        }

        return new Premis($objects, $events, $agents, $files, $notes);
    }

    /** @return list<string> package paths */
    private function references(MetsDocument $mets): array
    {
        $paths = [];
        foreach ([MetadataSection::Provenance, MetadataSection::Technical] as $section) {
            foreach ($mets->metadata($section) as $reference) {
                if ($this->isPremisReference($reference) && ($path = $mets->resolve($reference->href)) !== null) {
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }

    private function isPremisReference(MetadataReference $reference): bool
    {
        return in_array(strtoupper((string) $reference->type()), self::TYPES, true);
    }

    private function isPremis(DOMElement $root): bool
    {
        return in_array($root->namespaceURI, [Ns::PREMIS, self::PREMIS_2], true)
            && in_array($root->localName, ['premis', 'object', 'event', 'agent'], true);
    }

    /** @return list<DOMElement> the PREMIS elements of that name, root included */
    private function elements(DOMDocument $document, string $name): array
    {
        $elements = [];
        foreach ([Ns::PREMIS, self::PREMIS_2] as $namespace) {
            foreach ($document->getElementsByTagNameNS($namespace, $name) as $element) {
                // Only top-level entities: the root itself or children of <premis>.
                $parent = $element->parentNode;
                if ($element === $document->documentElement || ($parent instanceof DOMElement && $parent->localName === 'premis')) {
                    $elements[] = $element;
                }
            }
        }

        return $elements;
    }

    private function object(DOMElement $element, string $path): PremisObject
    {
        $type = $element->getAttributeNS(Ns::XSI, 'type');
        $category = $type === '' ? null : (str_contains($type, ':') ? substr($type, strpos($type, ':') + 1) : $type);

        $relationships = [];
        foreach ($this->children($element, 'relationship') as $relationship) {
            $related = [];
            // PREMIS 3: relatedObjectIdentifier, PREMIS 2: relatedObjectIdentification.
            foreach (['relatedObjectIdentifier', 'relatedObjectIdentification'] as $name) {
                foreach ($this->children($relationship, $name) as $identifier) {
                    $value = $this->text($identifier, 'relatedObjectIdentifierValue');
                    if ($value !== null) {
                        $related[] = $value;
                    }
                }
            }
            $relationships[] = new PremisRelationship(
                (string) $this->text($relationship, 'relationshipType'),
                $this->text($relationship, 'relationshipSubType'),
                $related,
            );
        }

        $contentLocations = [];
        foreach ($this->children($element, 'storage') as $storage) {
            foreach ($this->children($storage, 'contentLocation') as $location) {
                $value = $this->text($location, 'contentLocationValue');
                if ($value !== null) {
                    $contentLocations[] = $value;
                }
            }
        }

        return new PremisObject(
            $category,
            $this->texts($element, 'objectIdentifier', 'objectIdentifierValue'),
            $this->text($element, 'originalName'),
            $contentLocations,
            $relationships,
            $path,
        );
    }

    private function event(DOMElement $element, string $path): PremisEvent
    {
        $links = [];
        foreach ($this->children($element, 'linkingObjectIdentifier') as $link) {
            $value = $this->text($link, 'linkingObjectIdentifierValue');
            if ($value !== null) {
                $links[] = new PremisLink($value, $this->text($link, 'linkingObjectRole'));
            }
        }

        $outcome = null;
        foreach ($this->children($element, 'eventOutcomeInformation') as $information) {
            $outcome ??= $this->text($information, 'eventOutcome');
        }

        return new PremisEvent(
            $this->text($element, 'eventIdentifier', 'eventIdentifierValue'),
            (string) $this->text($element, 'eventType'),
            $this->text($element, 'eventDateTime'),
            // PREMIS 3: eventDetailInformation/eventDetail, PREMIS 2: eventDetail.
            $this->text($element, 'eventDetailInformation', 'eventDetail') ?? $this->text($element, 'eventDetail'),
            $outcome,
            $links,
            $path,
        );
    }

    /** @return list<DOMElement> */
    private function children(DOMElement $element, string $name): array
    {
        $children = [];
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name && in_array($child->namespaceURI, [Ns::PREMIS, self::PREMIS_2], true)) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /** Trimmed text of the first element along the path of child names, null when absent or empty. */
    private function text(DOMElement $element, string ...$names): ?string
    {
        return $this->texts($element, ...$names)[0] ?? null;
    }

    /** @return list<string> */
    private function texts(DOMElement $element, string ...$names): array
    {
        $current = [$element];
        foreach ($names as $name) {
            $next = [];
            foreach ($current as $node) {
                $next = array_merge($next, $this->children($node, $name));
            }
            $current = $next;
        }

        return array_values(array_filter(
            array_map(fn (DOMElement $node) => trim($node->textContent), $current),
            fn (string $text) => $text !== '',
        ));
    }
}
