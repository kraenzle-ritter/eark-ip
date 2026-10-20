<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Validation;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use KraenzleRitter\EarkIp\Model\MetsDocument;
use KraenzleRitter\EarkIp\Xml\Xml;

/** One METS document of the package under validation, with DOM and model side by side. */
final class MetsUnderTest
{
    public readonly DOMXPath $xpath;

    public function __construct(
        public readonly string $path,
        public readonly DOMDocument $document,
        public readonly MetsDocument $model,
        public readonly ?string $representation = null,
    ) {
        $this->xpath = Xml::xpath($document);
    }

    public function isPackageMets(): bool
    {
        return $this->representation === null;
    }

    public function root(): DOMElement
    {
        $root = $this->document->documentElement;
        assert($root instanceof DOMElement);

        return $root;
    }

    /** @return list<DOMElement> */
    public function elements(string $query, ?DOMNode $context = null): array
    {
        $elements = [];
        foreach ($this->xpath->query($query, $context) ?: [] as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    public function first(string $query, ?DOMNode $context = null): ?DOMElement
    {
        return $this->elements($query, $context)[0] ?? null;
    }

    /** The attribute value, or null when the attribute is absent. */
    public function attribute(?DOMElement $element, string $name, ?string $namespace = null): ?string
    {
        if ($element === null) {
            return null;
        }
        if ($namespace === null) {
            return $element->hasAttribute($name) ? $element->getAttribute($name) : null;
        }

        return $element->hasAttributeNS($namespace, $name) ? $element->getAttributeNS($namespace, $name) : null;
    }

    /** True when the attribute exists and is not blank. */
    public function filled(?DOMElement $element, string $name, ?string $namespace = null): bool
    {
        return trim((string) $this->attribute($element, $name, $namespace)) !== '';
    }

    public function at(?DOMNode $node = null): Location
    {
        return $node === null ? new Location($this->path) : Location::ofNode($this->path, $node);
    }
}
