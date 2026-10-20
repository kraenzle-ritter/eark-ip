<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Tests\Validation;

use DOMDocument;
use DOMElement;
use DOMXPath;
use KraenzleRitter\EarkIp\Xml\Xml;

/** Copies a reference package and changes it, to produce one specific violation. */
trait MutatesPackages
{
    protected function copyFixture(string $name): string
    {
        $target = $this->temporaryDirectory($name);
        $source = self::fixture('packages/'.$name);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $item->isDir() ? @mkdir($target.'/'.$relative, 0777, true) : copy($item->getPathname(), $target.'/'.$relative);
        }

        return $target;
    }

    /** @param  callable(DOMXPath, DOMDocument): void  $change */
    protected function editXml(string $file, callable $change): void
    {
        $document = Xml::load((string) file_get_contents($file));
        $change(Xml::xpath($document), $document);
        file_put_contents($file, $document->saveXML());
    }

    protected static function element(DOMXPath $xpath, string $query): DOMElement
    {
        $element = $xpath->query($query)?->item(0);
        assert($element instanceof DOMElement, "No element for {$query}");

        return $element;
    }

    protected static function setAttribute(DOMXPath $xpath, string $query, string $name, string $value): void
    {
        self::element($xpath, $query)->setAttribute($name, $value);
    }

    protected static function removeAttribute(DOMXPath $xpath, string $query, string $name, ?string $namespace = null): void
    {
        $element = self::element($xpath, $query);
        $namespace === null ? $element->removeAttribute($name) : $element->removeAttributeNS($namespace, $name);
    }

    protected static function remove(DOMXPath $xpath, string $query): void
    {
        foreach (iterator_to_array($xpath->query($query) ?: []) as $node) {
            $node->parentNode?->removeChild($node);
        }
    }
}
