<?php

declare(strict_types=1);

namespace KraenzleRitter\EarkIp\Xml;

use DOMDocument;
use DOMNode;
use DOMXPath;
use KraenzleRitter\EarkIp\Exception\XmlException;

/**
 * Loads XML from packages that come from elsewhere: no network, no
 * entity substitution, no DOCTYPE at all (METS, PREMIS and EAD never
 * need one), and parse errors collected instead of printed.
 */
final class Xml
{
    private const OPTIONS = LIBXML_NONET | LIBXML_COMPACT;

    public static function load(string $xml, string $name = 'document'): DOMDocument
    {
        if (trim($xml) === '') {
            throw new XmlException("{$name} is empty.");
        }

        $document = new DOMDocument;
        $loaded = self::withErrors(fn () => $document->loadXML($xml, self::OPTIONS), $errors);

        if (! $loaded || $document->documentElement === null) {
            throw new XmlException("{$name} is not well-formed XML.", $errors);
        }

        if ($document->doctype !== null) {
            throw new XmlException("{$name} declares a DOCTYPE, which is not allowed.");
        }

        return $document;
    }

    public static function xpath(DOMNode $node): DOMXPath
    {
        $document = $node instanceof DOMDocument ? $node : $node->ownerDocument;
        assert($document instanceof DOMDocument);

        $xpath = new DOMXPath($document);
        foreach (Ns::prefixes() as $prefix => $namespace) {
            $xpath->registerNamespace($prefix, $namespace);
        }

        return $xpath;
    }

    /**
     * Validate against an XSD shipped with this package. Returns the
     * schema errors; an empty list means valid.
     *
     * @return list<string>
     */
    public static function schemaErrors(DOMDocument $document, string $schemaPath): array
    {
        self::withErrors(fn () => $document->schemaValidate($schemaPath, LIBXML_SCHEMA_CREATE), $errors);

        return $errors;
    }

    /**
     * Run a libxml call with internal error handling and hand back the
     * errors it produced.
     *
     * @template T
     *
     * @param  callable(): T  $call
     * @param  list<string>|null  $errors
     *
     * @param-out list<string>  $errors
     *
     * @return T
     */
    public static function withErrors(callable $call, ?array &$errors = null): mixed
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $result = $call();
            $errors = array_map(
                fn (\LibXMLError $error) => sprintf('Line %d: %s', $error->line, trim($error->message)),
                libxml_get_errors(),
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $result;
    }
}
